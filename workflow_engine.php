<?php
require_once 'config/db.php';
require_once 'api/wpp_api.php';
require_once 'MKSolutionsAPI.php'; // Incluindo a nova classe da API do MK Solutions

class WorkflowEngine
{
    private $pdo;
    private $wppApi;
    private $mkApi; // Instância da API do MK Solutions

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        // Supondo que wpp_api.php define uma função ou classe para enviar mensagens
        // Se for uma classe, você pode instanciar aqui: $this->wppApi = new WppApi();

        // Carregar e instanciar a API do MK Solutions se houver uma integração ativa
        $mkConfig = $this->getIntegrationConfig('mksolutions');
        if ($mkConfig && $mkConfig['ativo']) {
            $config = json_decode($mkConfig['config_json'], true);
            if ($config && isset($config['url'], $config['usuario'], $config['senha'])) {
                $this->mkApi = new MKSolutionsAPI($config['url'], $config['usuario'], $config['senha']);
            } else {
                error_log("Configuração inválida para integração MK Solutions.");
            }
        }
    }

    /**
     * Busca a configuração de uma integração específica no banco de dados.
     * @param string $type O tipo da integração (ex: 'mksolutions').
     * @return array|false A configuração da integração ou false se não encontrada/ativa.
     */
    private function getIntegrationConfig(string $type)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT nome, tipo, config_json, ativo FROM integracoes WHERE tipo = :tipo AND ativo = TRUE LIMIT 1");
            $stmt->bindParam(':tipo', $type);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar configuração da integração {$type}: " . $e->getMessage());
            return false;
        }
    }

    public function processMessage(array $messageData)
    {
        $remetente = $messageData['from'] ?? '';
        $mensagem = $messageData['body'] ?? '';

        // 1. Buscar workflows ativos para o gatilho 'mensagem_recebida'
        $workflows = $this->getActiveWorkflows('mensagem_recebida');

        foreach ($workflows as $workflow) {
            if ($workflow['gatilho'] === 'visual') {
                // Novo motor para fluxos visuais
                $this->executeVisualWorkflow($workflow, $messageData);
            } else {
                // Lógica antiga para workflows baseados em JSON simples
                $condicoes = json_decode($workflow['condicoes_json'], true);
                if ($this->evaluateConditions($condicoes, $messageData)) {
                    $acoes = json_decode($workflow['acoes_json'], true);
                    $this->executeLegacyActions($acoes, $messageData);
                }
            }
        }
        return true;
    }

    private function executeVisualWorkflow($workflow, $initialContext) {
        $data = json_decode($workflow['acoes_json'], true);
        $nodes = [];
        foreach ($data['nodes'] as $node) {
            $nodes[$node['id']] = $node;
        }
        $connections = $data['connections'];

        // Encontrar o nó inicial (gatilho)
        $startNodeId = null;
        foreach ($nodes as $id => $node) {
            if ($node['type'] === 'trigger') {
                $startNodeId = $id;
                break;
            }
        }

        if (!$startNodeId) return; // Nenhum gatilho encontrado

        $currentNodeId = $startNodeId;
        $context = $initialContext; // O contexto carrega os dados durante o fluxo

        while ($currentNodeId) {
            $node = $nodes[$currentNodeId];
            $actionResult = $this->executeNodeAction($node, $context);
            
            // Atualiza o contexto com o resultado da ação
            if (is_array($actionResult)) {
                $context = array_merge($context, $actionResult);
            }

            // Encontrar o próximo nó
            $nextNodeId = null;
            foreach ($connections as $conn) {
                if ($conn['from'] === $currentNodeId) {
                    $nextNodeId = $conn['to'];
                    break; // Segue o primeiro caminho encontrado
                }
            }
            $currentNodeId = $nextNodeId;
        }
    }

    private function executeNodeAction($node, &$context) {
        $type = $node['subtype'] ?? 'unknown';
        $data = $node['data'] ?? [];

        switch ($type) {
            case 'send_message':
                $templateName = $data['template'] ?? null;
                $variables = $data['variables'] ?? [];

                if ($templateName) {
                    $messageContent = $this->getTemplatedMessageContent($templateName, $variables, $context);
                    if ($messageContent) {
                        sendMessage('painel', $context['from'], $messageContent);
                    }
                }
                return null;

            case 'mksolutions_consult_invoice':
                if (!$this->mkApi) {
                    error_log("MK Solutions API não configurada ou inativa.");
                    return null;
                }
                $documento = $this->resolveVariable($data['client_id'] ?? null, $context); // Pode ser CPF ou {{1}}
                $invoiceNumber = $this->resolveVariable($data['invoice_number'] ?? null, $context);

                if ($documento) {
                    // Primeiro, autentica
                    if (!$this->mkApi->authenticate()) {
                        error_log("MK Solutions Auth Error: " . $this->mkApi->getLastError());
                        return null;
                    }
                    
                    // Busca o cliente pelo documento para obter o CodigoCliente
                    $clientData = $this->mkApi->getClientByDocument($documento);
                    if ($clientData && isset($clientData['codigo'])) {
                        $codigoCliente = $clientData['codigo'];
                        $invoices = $this->mkApi->getInvoicesByClient($codigoCliente);
                        if ($invoices) {
                            // Filtrar e retornar a fatura mais relevante (ex: vencida ou próxima)
                            // Por simplicidade, vamos retornar a primeira fatura encontrada ou a mais relevante
                            $faturaEncontrada = null;
                            if ($invoiceNumber) {
                                foreach($invoices as $inv) {
                                    if ($inv['numero'] == $invoiceNumber) {
                                        $faturaEncontrada = $inv;
                                        break;
                                    }
                                }
                            } else {
                                // Lógica para encontrar a fatura mais relevante (vencida mais antiga, ou próxima)
                                // Exemplo simples: pega a primeira fatura não paga
                                foreach($invoices as $inv) {
                                    if ($inv['status'] !== 'PAGO') {
                                        $faturaEncontrada = $inv;
                                        break;
                                    }
                                }
                            }

                            if ($faturaEncontrada) {
                                // Gerar PIX para a fatura encontrada
                                $pixData = $this->mkApi->generatePix(null, $faturaEncontrada['codigo'], null);
                                if ($pixData) {
                                    return [
                                        'mk_client_data' => $clientData,
                                        'mk_invoice_data' => $faturaEncontrada,
                                        'mk_pix_copia_cola' => $pixData
                                    ];
                                } else {
                                    error_log("Erro ao gerar PIX: " . $this->mkApi->getLastError());
                                }
                            } else {
                                error_log("Nenhuma fatura relevante encontrada para o cliente {$documento}.");
                            }
                        } else {
                            error_log("Erro ao buscar faturas: " . $this->mkApi->getLastError());
                        }
                    } else {
                        error_log("Cliente não encontrado no MK Solutions: " . $this->mkApi->getLastError());
                    }
                } else {
                    error_log("Documento do cliente não fornecido para consulta MK Solutions.");
                }
                return null;

            case 'mksolutions_open_ticket':
                if (!$this->mkApi) {
                    error_log("MK Solutions API não configurada ou inativa.");
                    return null;
                }
                $clientId = $this->resolveVariable($data['client_id'] ?? null, $context);
                $subject = $this->resolveVariable($data['subject'] ?? null, $context);
                $description = $this->resolveVariable($data['description'] ?? null, $context);

                if ($clientId && $subject && $description) {
                    if (!$this->mkApi->authenticate()) {
                        error_log("MK Solutions Auth Error: " . $this->mkApi->getLastError());
                        return null;
                    }
                    // TODO: Implementar o método openTicket na MKSolutionsAPI.php
                    // e chamar aqui. Ex: $result = $this->mkApi->openTicket($clientId, $subject, $description);
                    error_log("Ação 'Abrir Chamado MK Solutions' não implementada na MKSolutionsAPI.php");
                    return null; // Retornar o resultado da ação
                } else {
                    error_log("Dados incompletos para abrir chamado MK Solutions.");
                }
                return null;

            // Adicionar outros cases para outras ações aqui
        }
        return null;
    }

    /**
     * Resolve uma variável do template usando o contexto.
     * Se a variável for do tipo {{X}}, busca no contexto. Caso contrário, retorna o valor literal.
     */
    private function resolveVariable($value, $context)
    {
        if (is_string($value) && preg_match('/^\{\{(\d+)\}\}$/', $value, $matches)) {
            // É uma variável do tipo {{1}}, {{2}} etc.
            // Mapear para chaves do contexto se necessário, ou usar diretamente
            // Por exemplo, se {{1}} for o CPF, e o contexto tiver 'cpf'
            // Isso depende de como as variáveis são mapeadas no seu sistema
            // Por enquanto, vamos retornar o próprio valor da variável para ser resolvido no template
            return $value; 
        } elseif (is_string($value) && preg_match('/^\{\{([a-zA-Z0-9_]+)\}\}$/', $value, $matches)) {
            // Variáveis nomeadas como {{variavel_do_contexto}}
            $varName = $matches[1];
            return $context[$varName] ?? $value; // Retorna do contexto ou o próprio placeholder
        }
        return $value; // Retorna o valor literal
    }

    /**
     * Obtém o conteúdo de uma mensagem de template, substituindo variáveis.
     * @param string $templateName Nome do template.
     * @param array $variables Variáveis passadas para o template (ex: ['1' => 'Valor1']).
     * @param array $context Contexto atual do workflow para variáveis dinâmicas.
     * @return string|false Conteúdo da mensagem com variáveis substituídas ou false se template não encontrado.
     */
    private function getTemplatedMessageContent(string $templateName, array $variables, array $context)
    {
        $stmt = $this->pdo->prepare("SELECT conteudo FROM DBA_MODELOS_MSG WHERE nome = :templateName");
        $stmt->bindParam(':templateName', $templateName);
        $stmt->execute();
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            $content = json_decode($template['conteudo'], true); // Decodifica o JSON do modelo
            $message = $content['body'] ?? '';

            // Substituir variáveis numéricas {{1}}, {{2}} etc.
            foreach ($variables as $key => $val) {
                $message = str_replace("{{" . $key . "}}", $val, $message);
            }

            // Substituir variáveis do contexto (ex: {{remetente}}, {{mk_client_data.nome}})
            foreach ($context as $key => $value) {
                if (is_string($value)) {
                    $message = str_replace("{{" . $key . "}}", $value, $message);
                } elseif (is_array($value)) {
                    // Lidar com variáveis aninhadas (ex: {{mk_client_data.nome}})
                    foreach ($value as $subKey => $subValue) {
                        if (is_string($subValue)) {
                            $message = str_replace("{{" . $key . "." . $subKey . "}}", $subValue, $message);
                        }
                    }
                }
            }
            return $message;
        }
        return false;
    }

    // --- Funções Legadas (para workflows antigos) ---

    private function getActiveWorkflows(string $gatilho)
    {
        // Modificado para buscar ambos os tipos de gatilho
        $stmt = $this->pdo->prepare("SELECT * FROM workflows WHERE (gatilho = :gatilho OR gatilho = 'visual') AND ativo = TRUE ORDER BY id ASC");
        $stmt->bindParam(':gatilho', $gatilho);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function evaluateConditions(array $condicoes, array $messageData):
    {
        $mensagem = strtolower($messageData['body'] ?? '');
        if (isset($condicoes['contem_palavra'])) {
            foreach ($condicoes['contem_palavra'] as $palavra) {
                if (strpos($mensagem, strtolower($palavra)) === false) return false;
            }
        }
        return true;
    }

    private function executeLegacyActions(array $acoes, array $messageData)
    {
        foreach ($acoes as $action) {
            if ($action['tipo'] === 'enviar_mensagem') {
                $this->sendTemplatedMessage($messageData['from'], $action['template']);
            }
        }
    }

    private function sendTemplatedMessage(string $to, string $templateName)
    {
        $stmt = $this->pdo->prepare("SELECT conteudo FROM DBA_MODELOS_MSG WHERE nome = :templateName");
        $stmt->bindParam(':templateName', $templateName);
        $stmt->execute();
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($template) {
            sendMessage('painel', $to, $template['conteudo']);
        }
    }
}