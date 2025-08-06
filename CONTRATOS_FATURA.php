<?php
// Remover display_errors e error_reporting para produção, usar apenas log
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

include_once "config/return_tokem.php"; // Inclui o token
include_once "config/conn.php";         // Inclui a conexão com o banco de dados

// Define o caminho para o arquivo de log
$log_date = date('Y-m-d');
define('LOG_FILE', __DIR__ . '/storage/contratos_fatura_' . $log_date . '.log');

/**
 * Função para registrar mensagens no arquivo de log.
 * @param string $message A mensagem a ser logada.
 * @param string $level O nível da mensagem (INFO, WARNING, ERROR).
 */
function log_message($message, $level = 'INFO')
{
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

log_message("Iniciando execução de CONTRATOS_FATURA.php", "INFO");

// Configurações da API
$api_url = "http://138.118.247.66:8080/mk/WSMKFaturasAbertas.rule?sys=MK0";

// Verifica se o token foi retornado corretamente
if (empty($tokem_retornado)) {
    log_message("Erro: Token não encontrado! Verifique config/return_tokem.php", "ERROR");
    exit;
}
log_message("Token de API obtido com sucesso.", "INFO");

// Consulta todas as réguas ativas
try {
    $sql = "SELECT `id`, `intervalo`, `hora`, `modelo_mensagem`, `status`, `REGUA_ID`, `data_criacao` 
            FROM `REGUAS_CRIADAS` WHERE status = 'ATIVO'";
    $stmt = $conn->query($sql);


    if ($stmt->rowCount() > 0) {
        log_message("" . $stmt->rowCount() . " réguas ativas encontradas.", "INFO");
        // Processa cada régua ativa
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $intervalo = $row['intervalo']; // Pode ser positivo (fatura a vencer) ou negativo (fatura atrasada)
            $hora = $row['hora']; // Hora para ajuste, se necessário
            $regua_id = $row['REGUA_ID'];
            $modeloMsg = $row['modelo_mensagem'];
            $dataCriacao = $row['data_criacao'];

            log_message("Processando régua ID: {$regua_id}, Intervalo: {$intervalo}, Hora: {$hora}", "INFO");
            log_message("DEBUG: Valor do intervalo para esta régua: {$intervalo}", "DEBUG"); // Adicionado para depuração

            // Verifica se a hora atual é uma hora antes da hora da régua
            $horaAtual = new DateTime();
            log_message("Hora atual do sistema: " . $horaAtual->format('H:i:s'), "DEBUG");

            // Verifica o formato da hora e cria o objeto DateTime corretamente
            if (!empty($hora)) {
                // Determina o formato correto da hora baseado no conteúdo
                if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
                    $horaRegua = DateTime::createFromFormat('H:i', $hora);
                } else if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
                    $horaRegua = DateTime::createFromFormat('H:i:s', $hora);
                } else {
                    log_message("Regua {$regua_id}: Formato de hora não reconhecido ({$hora}). Pulando régua.", "WARNING");
                    continue;
                }

                if ($horaRegua === false) {
                    log_message("Regua {$regua_id}: Erro ao processar hora ({$hora}). Pulando régua.", "ERROR");
                    continue;
                }

                // Cria uma cópia da hora da régua e subtrai 1 hora
                $horaLimiteExecucao = clone $horaRegua;
                $horaLimiteExecucao->modify("-1 hour");

                // Compara apenas horas e minutos
                if ($horaAtual->format('H:i') != $horaLimiteExecucao->format('H:i')) {
                    log_message("Regua {$regua_id}: Aguardando horário de execução (" .
                        $horaLimiteExecucao->format('H:i') . " - uma hora antes de " .
                        $horaRegua->format('H:i') . "). Pulando régua.", "INFO");
                    continue;
                }

                log_message("Regua {$regua_id}: Executando uma hora antes do horário programado (" .
                    $horaLimiteExecucao->format('H:i') . " para régua das " .
                    $horaRegua->format('H:i') . ").", "INFO");
            } else {
                log_message("Regua {$regua_id}: Valor de hora vazio. Pulando régua.", "WARNING");
                continue;
            }

            // Calcula a data de vencimento baseada no intervalo da régua.
            $dataFatura = date('Y-m-d', strtotime("$intervalo days"));
            log_message("Regua {$regua_id}: Data de vencimento calculada: {$dataFatura} (Intervalo: {$intervalo} dias).", "INFO");

            // Monta a URL para buscar faturas com vencimento na data calculada
            $data_venc_url = "{$api_url}&token={$tokem_retornado}&dt_venc_inicio={$dataFatura}&dt_venc_fim={$dataFatura}";
            log_message("Regua {$regua_id}: URL da API para faturas abertas: {$data_venc_url}", "DEBUG");

            // Obtém os dados da API
            $response = get_api_data($data_venc_url);

            if ($response !== false) {
                $response_data = json_decode($response, true);

                if (isset($response_data['ListaFaturas']) && !empty($response_data['ListaFaturas'])) {
                    log_message("Regua {$regua_id}: " . count($response_data['ListaFaturas']) . " faturas abertas encontradas.", "INFO");
                    foreach ($response_data['ListaFaturas'] as $fatura) {
                        $cd_fatura_api = $fatura['cd_fatura'] ?? 'N/A';
                        log_message("Regua {$regua_id}: Processando fatura da API: {$cd_fatura_api}", "DEBUG");

                        $dataFaturaAtual = $dataFatura; // Data de vencimento da régua

                        // Cria objetos DateTime para comparar a data atual com a data da fatura
                        $hoje = new DateTime();
                        $dataFaturaObj = new DateTime($dataFaturaAtual);

                        // Calcula a diferença em dias. Se a fatura estiver vencida, o diff será negativo.
                        $diff = $hoje->diff($dataFaturaObj);
                        $diffDays = (int)$diff->format('%a'); // Número absoluto de dias
                        $isOverdue = ($dataFaturaObj < $hoje); // Verifica se a fatura está vencida

                        // Inicializa todos os campos de status como 'Não'
                        $FATURA_HOJE = 'Não';
                        $FATURA_5_DIAS = 'Não';
                        $FATURA_ATRASADA_5_DIAS = 'Não';
                        $FATURA_ATRASADA_12_DIAS = 'Não';
                        $FATURA_ATRASADA_20_DIAS = 'Não';
                        $FATURA_ATRASADA_30_DIAS = 'Não';
                        $FATURA_ATRASADA_42_DIAS = 'Não';
                        $FATURA_ATRASADA_52_DIAS = 'Não';
                        $FATURA_ATRASADA_62_DIAS = 'Não';

                        // ✅ CORREÇÃO: Lógica para preencher os campos de status baseada no intervalo da régua
                        // O $intervalo vem da tabela REGUAS_CRIADAS e já indica o status desejado.
                        if ($intervalo == 0) {
                            $FATURA_HOJE = 'Sim';
                        } elseif ($intervalo == 5) {
                            $FATURA_5_DIAS = 'Sim';
                        } elseif ($intervalo == -5) {
                            $FATURA_ATRASADA_5_DIAS = 'Sim';
                        } elseif ($intervalo == -12) {
                            $FATURA_ATRASADA_12_DIAS = 'Sim';
                        } elseif ($intervalo == -20) {
                            $FATURA_ATRASADA_20_DIAS = 'Sim';
                        } elseif ($intervalo == -30) {
                            $FATURA_ATRASADA_30_DIAS = 'Sim';
                        } elseif ($intervalo == -42) {
                            $FATURA_ATRASADA_42_DIAS = 'Sim';
                        } elseif ($intervalo == -52) {
                            $FATURA_ATRASADA_52_DIAS = 'Sim';
                        } elseif ($intervalo == -62) {
                            $FATURA_ATRASADA_62_DIAS = 'Sim';
                        }

                        // Monta o array com os dados da fatura
                        $dados_fatura = [
                            'cd_fatura' => $fatura['cd_fatura'],
                            'nome' => $fatura['nome'],
                            'valor' => $fatura['valor'],
                            'data_vencimento' => $dataFaturaAtual,
                            'regua_id' => $regua_id,
                            'FATURA_HOJE' => $FATURA_HOJE,
                            'FATURA_5_DIAS' => $FATURA_5_DIAS,
                            'FATURA_ATRASADA_5_DIAS' => $FATURA_ATRASADA_5_DIAS,
                            'FATURA_ATRASADA_12_DIAS' => $FATURA_ATRASADA_12_DIAS,
                            'FATURA_ATRASADA_20_DIAS' => $FATURA_ATRASADA_20_DIAS,
                            'FATURA_ATRASADA_30_DIAS' => $FATURA_ATRASADA_30_DIAS,
                            'FATURA_ATRASADA_42_DIAS' => $FATURA_ATRASADA_42_DIAS,
                            'FATURA_ATRASADA_52_DIAS' => $FATURA_ATRASADA_52_DIAS,
                            'FATURA_ATRASADA_62_DIAS' => $FATURA_ATRASADA_62_DIAS,
                            'enviado' => 'Não',
                            'data_atual' => date('Y-m-d'),
                            'created_at' => date('Y-m-d H:i:s')
                        ];

                        // Insere ou atualiza a fatura no banco de dados
                        inserir_ou_atualizar_fatura($dados_fatura, $conn);
                    }
                } else {
                    log_message("Regua {$regua_id}: Nenhuma fatura encontrada na resposta da API para a data {$dataFatura}.", "INFO");
                }
            } else {
                log_message("Regua {$regua_id}: Erro ao obter dados da API para a data {$dataFatura}. Resposta da API inválida ou vazia.", "ERROR");
            }
        }
    } else {
        log_message("Nenhuma régua ativa encontrada na tabela REGUAS_CRIADAS.", "INFO");
    }
} catch (PDOException $e) {
    log_message("Erro na consulta das réguas: " . $e->getMessage(), "ERROR");
}

/**
 * Função para fazer a requisição cURL e retornar os dados da API.
 * @param string $url A URL da API.
 * @return string|false A resposta da API ou false em caso de erro.
 */
function get_api_data($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        log_message("Erro cURL ao chamar API ({$url}): " . curl_error($ch), "ERROR");
        curl_close($ch);
        return false;
    }

    if ($http_code !== 200) {
        log_message("Erro HTTP {$http_code} ao chamar API ({$url}). Resposta: {$response}", "ERROR");
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return $response;
}

/**
 * Função para inserir ou atualizar uma fatura no banco de dados.
 * @param array $fatura Dados da fatura.
 * @param PDO $conn Objeto de conexão PDO.
 */
function inserir_ou_atualizar_fatura($fatura, $conn)
{
    // Verifica se a fatura já existe no banco de dados
    $check = $conn->prepare("SELECT COUNT(*) FROM FATURAS_A_VENCER
    WHERE CD_FATURA = :cd_fatura
    AND DATA_VENCIMENTO = :data_vencimento");
    $check->execute([
        ':cd_fatura' => $fatura['cd_fatura'],
        ':data_vencimento' => $fatura['data_vencimento']
    ]);
    $exists = $check->fetchColumn();

    if ($exists > 0) {
        // Atualiza a fatura existente
        $update_sql = "UPDATE FATURAS_A_VENCER SET
    NOME = :nome,
    VALOR = :valor,
    REGUA_ID = :regua_id,
    FATURA_HOJE = :FATURA_HOJE,
    FATURA_5_DIAS = :FATURA_5_DIAS,
    FATURA_ATRASADA_5_DIAS = :FATURA_ATRASADA_5_DIAS,
    FATURA_ATRASADA_12_DIAS = :FATURA_ATRASADA_12_DIAS,
    FATURA_ATRASADA_20_DIAS = :FATURA_ATRASADA_20_DIAS,
    FATURA_ATRASADA_30_DIAS = :FATURA_ATRASADA_30_DIAS,
    FATURA_ATRASADA_42_DIAS = :FATURA_ATRASADA_42_DIAS,
    FATURA_ATRASADA_52_DIAS = :FATURA_ATRASADA_52_DIAS,
    FATURA_ATRASADA_62_DIAS = :FATURA_ATRASADA_62_DIAS,
    ENVIADO = :enviado,
    DATA_ATUAL = :data_atual
    WHERE CD_FATURA = :cd_fatura AND DATA_VENCIMENTO = :data_vencimento";

        $stmt = $conn->prepare($update_sql);
        try {
            $stmt->execute([
                ':cd_fatura' => $fatura['cd_fatura'],
                ':nome' => $fatura['nome'],
                ':valor' => $fatura['valor'],
                ':data_vencimento' => $fatura['data_vencimento'],
                ':regua_id' => $fatura['regua_id'],
                ':FATURA_HOJE' => $fatura['FATURA_HOJE'],
                ':FATURA_5_DIAS' => $fatura['FATURA_5_DIAS'],
                ':FATURA_ATRASADA_5_DIAS' => $fatura['FATURA_ATRASADA_5_DIAS'],
                ':FATURA_ATRASADA_12_DIAS' => $fatura['FATURA_ATRASADA_12_DIAS'],
                ':FATURA_ATRASADA_20_DIAS' => $fatura['FATURA_ATRASADA_20_DIAS'],
                ':FATURA_ATRASADA_30_DIAS' => $fatura['FATURA_ATRASADA_30_DIAS'],
                ':FATURA_ATRASADA_42_DIAS' => $fatura['FATURA_ATRASADA_42_DIAS'],
                ':FATURA_ATRASADA_52_DIAS' => $fatura['FATURA_ATRASADA_52_DIAS'],
                ':FATURA_ATRASADA_62_DIAS' => $fatura['FATURA_ATRASADA_62_DIAS'],
                ':enviado' => $fatura['enviado'],
                ':data_atual' => $fatura['data_atual']
            ]);
            log_message("Fatura {$fatura['cd_fatura']} para {$fatura['data_vencimento']} atualizada com sucesso na tabela FATURAS_A_VENCER.", "INFO");
        } catch (PDOException $e) {
            log_message("Erro ao atualizar fatura {$fatura['cd_fatura']}: " . $e->getMessage(), "ERROR");
        }
    } else {
        // Insere a nova fatura
        $insert_sql = "INSERT INTO FATURAS_A_VENCER (
    CD_FATURA, NOME, VALOR, DATA_VENCIMENTO, REGUA_ID,
    FATURA_HOJE, FATURA_5_DIAS,
    FATURA_ATRASADA_5_DIAS, FATURA_ATRASADA_12_DIAS,
    FATURA_ATRASADA_20_DIAS, FATURA_ATRASADA_30_DIAS,
    FATURA_ATRASADA_42_DIAS, FATURA_ATRASADA_52_DIAS,
    FATURA_ATRASADA_62_DIAS, ENVIADO, DATA_ATUAL, CREATED_AT
    ) VALUES (
    :cd_fatura, :nome, :valor, :data_vencimento, :regua_id,
    :FATURA_HOJE, :FATURA_5_DIAS,
    :FATURA_ATRASADA_5_DIAS, :FATURA_ATRASADA_12_DIAS,
    :FATURA_ATRASADA_20_DIAS, :FATURA_ATRASADA_30_DIAS,
    :FATURA_ATRASADA_42_DIAS, :FATURA_ATRASADA_52_DIAS,
    :FATURA_ATRASADA_62_DIAS, :enviado, :data_atual, :created_at
    )";

        $stmt = $conn->prepare($insert_sql);

        try {
            $stmt->execute([
                ':cd_fatura' => $fatura['cd_fatura'],
                ':nome' => $fatura['nome'],
                ':valor' => $fatura['valor'],
                ':data_vencimento' => $fatura['data_vencimento'],
                ':regua_id' => $fatura['regua_id'],
                ':FATURA_HOJE' => $fatura['FATURA_HOJE'],
                ':FATURA_5_DIAS' => $fatura['FATURA_5_DIAS'],
                ':FATURA_ATRASADA_5_DIAS' => $fatura['FATURA_ATRASADA_5_DIAS'],
                ':FATURA_ATRASADA_12_DIAS' => $fatura['FATURA_ATRASADA_12_DIAS'],
                ':FATURA_ATRASADA_20_DIAS' => $fatura['FATURA_ATRASADA_20_DIAS'],
                ':FATURA_ATRASADA_30_DIAS' => $fatura['FATURA_ATRASADA_30_DIAS'],
                ':FATURA_ATRASADA_42_DIAS' => $fatura['FATURA_ATRASADA_42_DIAS'],
                ':FATURA_ATRASADA_52_DIAS' => $fatura['FATURA_ATRASADA_52_DIAS'],
                ':FATURA_ATRASADA_62_DIAS' => $fatura['FATURA_ATRASADA_62_DIAS'],
                ':enviado' => $fatura['enviado'],
                ':data_atual' => $fatura['data_atual'],
                ':created_at' => $fatura['created_at']
            ]);
            log_message("Fatura {$fatura['cd_fatura']} inserida com sucesso na tabela FATURAS_A_VENCER.", "INFO");
        } catch (PDOException $e) {
            log_message("Erro ao inserir fatura {$fatura['cd_fatura']}: " . $e->getMessage(), "ERROR");
        }
    }
}

/**
 * ✅ NOVA FUNÇÃO: Função para excluir uma fatura do banco de dados.
 * @param PDO $conn Objeto de conexão PDO.
 * @param int $id_fatura ID da fatura a ser excluída.
 * @return bool True se a exclusão foi bem-sucedida, false caso contrário.
 */
function deleteFatura($conn, $id_fatura)
{
    try {
        $sql = "DELETE FROM FATURAS_A_VENCER WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":id", $id_fatura, PDO::PARAM_INT);

        if ($stmt->execute()) {
            log_message("Fatura com ID {$id_fatura} excluída com sucesso da tabela FATURAS_A_VENCER.", "INFO");
            return true;
        } else {
            log_message("Erro ao excluir fatura com ID {$id_fatura}. Nenhuma linha afetada.", "WARNING");
            return false;
        }
    } catch (PDOException $e) {
        log_message("Erro no PDO ao excluir fatura ID {$id_fatura}: " . $e->getMessage(), "ERROR");
        return false;
    }
}

/**
 * Função para atualizar a descrição de uma fatura no banco de dados.
 * @param PDO $conn Objeto de conexão PDO.
 * @param int $id_fatura ID da fatura a ser atualizada.
 * @param string $descricao A nova descrição da fatura.
 */
function atualizar_descricao_fatura($conn, $id_fatura, $descricao)
{
    try {
        $sql = "UPDATE FATURAS_A_VENCER SET DESCRICAO = :descricao WHERE ID = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':descricao', $descricao, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id_fatura, PDO::PARAM_INT);

        if ($stmt->execute()) {
            log_message("Descrição da fatura ID {$id_fatura} atualizada com sucesso.", "INFO");
        } else {
            log_message("Erro ao atualizar descrição da fatura ID {$id_fatura}. Nenhuma linha afetada.", "WARNING");
        }
    } catch (PDOException $e) {
        log_message("Erro no PDO ao atualizar descrição da fatura ID {$id_fatura}: " . $e->getMessage(), "ERROR");
    }
}

// ✅ OTIMIZAÇÃO: Unificação do pós-processamento de faturas
log_message("Iniciando pós-processamento unificado de faturas.", "INFO");

$query_unificada = "SELECT * FROM FATURAS_A_VENCER";
$stmt_unificado = $conn->prepare($query_unificada);
$stmt_unificado->execute();
$todos_registros = $stmt_unificado->fetchAll(PDO::FETCH_ASSOC);

if (empty($todos_registros)) {
    log_message("Nenhum registro encontrado em FATURAS_A_VENCER para pós-processamento.", "INFO");
} else {
    log_message("" . count($todos_registros) . " registros encontrados para pós-processamento unificado.", "INFO");
}

foreach ($todos_registros as $registro) {
    $id_fatura = $registro['ID'];
    $cd_fatura = $registro['CD_FATURA'];
    $codigo_cliente = $registro['CODIGO_CLIENTE'];
    $celular = $registro['CELULAR'];
    $ld = $registro['LD'];

    log_message("Processando fatura ID {$id_fatura} (CD_FATURA: {$cd_fatura}).", "INFO");

    // ETAPA 1: Verificar e atualizar informações de contato, se necessário
    if (is_null($codigo_cliente) || is_null($celular) || is_null($ld) || $celular == '55') {
        log_message("Fatura {$cd_fatura}: Informações de contato ausentes ou incompletas. Consultando API WSMKLDViaSMS.", "INFO");
        $url_contato_api = "http://138.118.247.66:8080/mk/WSMKLDViaSMS.rule?sys=MK0&token="
            . $tokem_retornado . "&cd_fatura=" . urlencode($cd_fatura);
        
        $response_contato = get_api_data($url_contato_api);
        $api_data_contato = $response_contato ? json_decode($response_contato, true) : null;

        if ($api_data_contato && isset($api_data_contato['DadosFatura']) && !empty($api_data_contato['DadosFatura'])) {
            $resultado = $api_data_contato['DadosFatura'][0];
            $codigo_cliente_novo = $resultado['codigopessoa'] ?? null;
            $celular_novo = $resultado['celular'] ?? null;
            $ld_novo = $resultado['ld'] ?? null;

            // Atualiza as variáveis locais para uso na Etapa 2
            $codigo_cliente = $codigo_cliente_novo;
            
            $update_query = "UPDATE FATURAS_A_VENCER SET CODIGO_CLIENTE = :codigo_cliente, CELULAR = :celular, LD = :ld WHERE ID = :id";
            $stmt_update = $conn->prepare($update_query);
            try {
                $stmt_update->execute([
                    ':codigo_cliente' => $codigo_cliente_novo,
                    ':celular' => $celular_novo,
                    ':ld' => $ld_novo,
                    ':id' => $id_fatura
                ]);
                log_message("Fatura {$cd_fatura}: Informações de contato atualizadas.", "INFO");
            } catch (PDOException $e) {
                log_message("Fatura {$cd_fatura}: Erro ao atualizar informações de contato: " . $e->getMessage(), "ERROR");
                continue; // Pula para a próxima fatura se não conseguir atualizar
            }

        } elseif ($api_data_contato && ($api_data_contato['Mensagem'] ?? '') === 'Fatura não localizada.') {
            log_message("Fatura {$cd_fatura} não localizada pela API de contato. Excluindo registro.", "WARNING");
            deleteFatura($conn, $id_fatura);
            continue; // Pula para a próxima fatura
        } else {
            log_message("Fatura {$cd_fatura}: Não foi possível obter informações de contato válidas. Pulando para a próxima.", "WARNING");
            continue; // Pula para a próxima fatura
        }
    }

    // ETAPA 2: Verificar descrição (MULTA/EQUIPAMENTO)
    if (empty($codigo_cliente)) {
        log_message("Fatura {$cd_fatura}: Impossível verificar descrição pois o código do cliente é nulo. Pulando.", "WARNING");
        continue;
    }

    log_message("Fatura {$cd_fatura}: Verificando descrição para multas ou equipamentos.", "INFO");
    $url_pendentes_api = "http://138.118.247.66:8080/mk/WSMKFaturasPendentes.rule?sys=MK0&token="
        . $tokem_retornado . "&cd_cliente=" . urlencode($codigo_cliente);

    $response_pendentes = get_api_data($url_pendentes_api);
    $api_data_pendentes = $response_pendentes ? json_decode($response_pendentes, true) : null;

    if ($api_data_pendentes && isset($api_data_pendentes['FaturasPendentes']) && !empty($api_data_pendentes['FaturasPendentes'])) {
        $fatura_encontrada = null;
        foreach ($api_data_pendentes['FaturasPendentes'] as $fatura_api) {
            if ((string)($fatura_api['codfatura'] ?? '') === (string)$cd_fatura) {
                $fatura_encontrada = $fatura_api;
                break;
            }
        }

        if ($fatura_encontrada) {
            $descricao = $fatura_encontrada['descricao'] ?? '';
            if (stripos($descricao, "MULTA") !== false || stripos($descricao, "EQUIPAMENTO") !== false) {
                log_message("Fatura {$cd_fatura} contém 'MULTA' ou 'EQUIPAMENTO'. Excluindo...", "WARNING");
                deleteFatura($conn, $id_fatura);
            } else {
                log_message("Fatura {$cd_fatura} não é de multa/equipamento. Atualizando descrição.", "INFO");
                atualizar_descricao_fatura($conn, $id_fatura, $descricao);
            }
        } else {
            log_message("Fatura {$cd_fatura} não encontrada na lista de faturas pendentes do cliente {$codigo_cliente}.", "WARNING");
        }
    } else {
        log_message("Fatura {$cd_fatura}: Não foi possível obter faturas pendentes para o cliente {$codigo_cliente}.", "ERROR");
    }

    log_message("Pausa de 1 segundo após processar fatura {$id_fatura}.", "DEBUG");
    sleep(1);
}

log_message("Execução de CONTRATOS_FATURA.php finalizada.", "INFO");
log_message("###########################################################.", "INFO");
