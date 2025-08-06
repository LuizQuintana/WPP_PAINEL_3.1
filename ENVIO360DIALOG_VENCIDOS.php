<?php
// ==================== CONFIGURAÇÃO INICIAL ====================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define o fuso horário para Brasil
date_default_timezone_set('America/Sao_Paulo'); // Para horário de Brasília

// Gera um ID único para esta execução do script
$execId = uniqid('exec_', true);
$tempoInicial = microtime(true);



// Função auxiliar para debug
function debug_print($message, $data = null, $level = 'INFO')
{
    // Define constante para habilitar/desabilitar debug
    if (!defined('DEBUG_ENABLED')) define('DEBUG_ENABLED', true);

    // Se debug estiver desabilitado, não faz nada
    if (!DEBUG_ENABLED) return;

    $timestamp = date('Y-m-d H:i:s');

    // Formato simples para logs em texto: [NÍVEL] [TIMESTAMP] MENSAGEM
    echo "[$level] [$timestamp] $message";

    if ($data !== null) {
        echo "\n"; // Nova linha para melhor legibilidade
        if (is_array($data) || is_object($data)) {
            // Usar print_r para arrays e objetos
            print_r($data);
        } else if (is_bool($data)) {
            // Mostrar booleanos como texto
            echo ($data ? 'true' : 'false');
        } else {
            // Strings, números, etc
            echo $data;
        }
    }

    echo "\n"; // Nova linha no final

    // Força a saída
    if (ob_get_level() > 0) {
        ob_flush();
        flush();
    }
}



// Inicializa variáveis de contagem global
$totalReguasProcessadas = 0;
$totalFaturasProcessadas = 0;
$totalEnviosSucesso = 0;
$totalEnviosFalha = 0;

debug_print("Iniciando execução com ID: " . $execId);

if (file_exists(__DIR__ . "/config/config_360.php")) {
    require_once __DIR__ . "/config/config_360.php";
    debug_print("Arquivo config_360.php carregado com sucesso");
} else {
    debug_print("ERRO: Arquivo config.php não encontrado - criando valores padrão temporários");
    // Valores temporários para desenvolvimento
}

debug_print("Tentando incluir conn.php");
if (file_exists(__DIR__ . "/conn.php")) {
    include_once __DIR__ . "/conn.php";
    debug_print("Arquivo conn.php incluído");
} else {
    die("ERRO FATAL: Arquivo conn.php não encontrado");
}

debug_print("Tentando incluir function_auth_fortics.php");
if (file_exists(__DIR__ . "/function_auth_fortics.php")) {
    include_once __DIR__ . "/function_auth_fortics.php";
    debug_print("Arquivo function_auth_fortics.php incluído");
} else {
    die("ERRO FATAL: Arquivo function_auth_fortics.php não encontrado");
}

// Criar tabela de bloqueio de script se não existir
$conn->exec("CREATE TABLE IF NOT EXISTS SCRIPT_LOCKS (
    lock_name VARCHAR(50) PRIMARY KEY,
    acquired_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL
)");

// Função para obter o bloqueio do script
function acquireScriptLock($conn)
{
    $lockName = 'script_envio_faturas_lock';
    $expiry = 5; // minutos

    // Tenta obter o bloqueio
    $sql = "INSERT INTO SCRIPT_LOCKS (lock_name, acquired_at, expires_at)
            VALUES (:lock_name, NOW(), DATE_ADD(NOW(), INTERVAL :expiry MINUTE))
            ON DUPLICATE KEY UPDATE 
                acquired_at = IF(expires_at < NOW(), NOW(), acquired_at),
                expires_at = IF(expires_at < NOW(), DATE_ADD(NOW(), INTERVAL :expiry MINUTE), expires_at)";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':lock_name', $lockName);
    $stmt->bindParam(':expiry', $expiry, PDO::PARAM_INT);
    $stmt->execute();

    // Verifica se conseguiu o bloqueio
    $check = $conn->prepare("SELECT acquired_at, expires_at, 
                            IF(acquired_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE), true, false) as has_lock 
                            FROM SCRIPT_LOCKS WHERE lock_name = :lock_name");
    $check->bindParam(':lock_name', $lockName);
    $check->execute();
    $result = $check->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return false;
    }

    $hasLock = (bool)$result['has_lock'];
    debug_print("Verificação de bloqueio:", [
        'acquired_at' => $result['acquired_at'],
        'expires_at' => $result['expires_at'],
        'has_lock' => $hasLock ? 'Sim' : 'Não'
    ]);

    return $hasLock;
}

// Verifica se pode executar o script
if (!acquireScriptLock($conn)) {
    debug_print("Outra instância do script está em execução. Encerrando.", null, 'WARNING');
    exit;
}

// Limpa bloqueios de envios antigos com mais frequência 
$conn->exec("DELETE FROM ENVIOS_EM_ANDAMENTO WHERE TIMESTAMP < NOW() - INTERVAL 10 MINUTE");
debug_print("Registros antigos de ENVIOS_EM_ANDAMENTO (>10min) foram limpos");

try {
    debug_print("Obtendo token de autenticação Fortics");
    $authData = getAuthToken();
    $token_fortics = $authData['token'];
    $user_id_fortics = $authData['id']; // se precisar
    debug_print("Token obtido com sucesso", ['token' => substr($token_fortics, 0, 10) . '...', 'user_id' => $user_id_fortics]);

    // Não enviar nos finais de semana
    $diaSemana = date('w');
    debug_print("Verificando dia da semana: " . $diaSemana . " (0=Domingo, 6=Sábado)");
    if ($diaSemana == 0 || $diaSemana == 6) {
        debug_print("Envio desabilitado nos finais de semana. Encerrando script.");
        exit;
    }


    // Lista de feriados fixos (formato: dd/mm/aaaa)
    $feriados = [
        '01/01/2025', // Confraternização Universal
        '03/03/2025', // Carnaval (segunda)
        '04/03/2025', // Carnaval (terça)
        '18/04/2025', // Paixão de Cristo
        '21/04/2025', // Tiradentes
        '01/05/2025', // Dia do Trabalho
        '19/06/2025', // Corpus Christi
        '07/09/2025', // Independência do Brasil
        '12/10/2025', // Nossa Senhora Aparecida
        '02/11/2025', // Finados
        '15/11/2025', // Proclamação da República
        '20/11/2025', // Consciência Negra
        '24/12/2025', // Véspera de Natal
        '25/12/2025', // Natal
        '31/12/2025', // Véspera de Ano Novo
    ];

    // Data de hoje
    $dataHoje = date('d/m/Y');
    debug_print("Verificando feriados para a data: " . $dataHoje);

    // Verificação de feriado
    if (in_array($dataHoje, $feriados)) {
        debug_print("Envio desabilitado em feriados. Encerrando script.");
        exit;
    }

    debug_print("Dia útil. Continuando com a execução.");
    // ==================== PROCESSAMENTO DAS RÉGUAS ====================
    debug_print("Iniciando busca de réguas ativas");

    // Seleciona todas as réguas ativas
    $sqlReguas = "SELECT id, nome, intervalo, hora, modelo_mensagem, status, REGUA_ID, data_criacao 
                    FROM REGUAS_CRIADAS 
                    WHERE status = 'Ativo'";

    // Verificando conexão com banco de dados antes de executar query
    debug_print("Verificando conexão com banco de dados");
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception("Conexão com banco de dados não está disponível ou não é um objeto PDO válido");
    }

    debug_print("Executando query de réguas: " . $sqlReguas);
    $stmtReguas = $conn->prepare($sqlReguas);
    $stmtReguas->execute();
    $reguasAtivas = $stmtReguas->fetchAll(PDO::FETCH_ASSOC);

    debug_print("Número de réguas encontradas: " . count($reguasAtivas));

    if (!$reguasAtivas) {
        debug_print("Nenhuma régua ativa encontrada. Encerrando script.");
        exit;
    }

    debug_print("Lista completa de réguas ativas:", $reguasAtivas);

    $currentTime = date('H:i');
    debug_print("Hora atual: " . $currentTime);

    $reguasParaProcessar = 0;
    foreach ($reguasAtivas as $regua) {
        debug_print("Processando régua:", $regua);

        $reguaId         = $regua['REGUA_ID'];
        $nomeregua       = $regua['nome'];
        $intervalo       = (int)$regua['intervalo'];
        $hora            = $regua['hora'];          // Esperado no formato "H:i:s" ou "H:i"
        $modeloMensagem  = $regua['modelo_mensagem'];

        // Reinicializa contadores para cada régua
        $contadorEnvios = 0;
        $enviosSucesso = 0;
        $enviosFalha = 0;
        $faturasProcessadas = array(); // Array para controlar as faturas já processadas nesta execução

        // Obtém os dados de configuração do arquivo config.php
        $templateNamespace = TEMPLATE_NAMESPACE;
        $languageCode      = LANGUAGE_CODE;

        // Converte os horários para DateTime usando somente a parte "H:i"
        $horaRegua = DateTime::createFromFormat('H:i', substr($hora, 0, 5));
        $currentTimeObj = DateTime::createFromFormat('H:i', substr($currentTime, 0, 5));

        if (!$horaRegua || !$currentTimeObj) {
            debug_print("Erro ao converter horários. Pulando régua.");
            continue;
        }

        // Calcula a diferença em minutos entre o horário programado e o horário atual
        $diffInMinutes = abs($horaRegua->getTimestamp() - $currentTimeObj->getTimestamp()) / 60;

        debug_print("Verificando horário da régua: " . $horaRegua->format('H:i') . " vs hora atual: " . $currentTimeObj->format('H:i'));
        debug_print("Diferença em minutos: " . $diffInMinutes);
        debug_print("Processando régua se a diferença for <= 15 minutos.");

        // Se a diferença for maior que 15 minutos, pula esta régua
        if ($diffInMinutes > 15) {
            debug_print("Horário fora da tolerância de 15 minutos. Pulando régua.");
            continue;
        }

        // Se o intervalo não é pós-vencimento (intervalo negativo), ignora esta régua
        debug_print("Verificando intervalo da régua: " . $intervalo);
        if ($intervalo > 0) {
            debug_print("Régua '{$nomeregua}' ignorada por não ser pós-vencimento ({$intervalo}).");
            continue;
        }

        $reguasParaProcessar++;
        $totalReguasProcessadas++;
        debug_print("Régua selecionada para processamento!");
        echo "<hr><strong>Processando a régua: {$nomeregua}</strong><br>";
        echo "Intervalo: {$intervalo} | Hora: {$hora} | Modelo: {$modeloMensagem}<br>";

        // ==================== BUSCA FATURAS ====================
        debug_print("Buscando faturas para o intervalo " . $intervalo);

        // Identificar faturas que seriam filtradas por estarem na tabela ERRO_CONTACT
        $sqlFaturasIgnoradas = "SELECT FAV.ID, FAV.NOME, FAV.CELULAR, FAV.CD_FATURA, FAV.VALOR, FAV.DATA_VENCIMENTO,
                CURDATE() as DATA_HOJE,
                DATEDIFF(FAV.DATA_VENCIMENTO, CURDATE()) as DIFF_DIAS
                FROM FATURAS_A_VENCER FAV
                JOIN ERRO_CONTACT EC ON FAV.CD_FATURA = EC.FATURA 
                WHERE FAV.ENVIADO = 'NAO'
                AND DATEDIFF(FAV.DATA_VENCIMENTO, CURDATE()) = :intervalo
                AND EC.STATUS = 'NAO'";

        $stmtFaturasIgnoradas = $conn->prepare($sqlFaturasIgnoradas);
        $stmtFaturasIgnoradas->bindParam(':intervalo', $intervalo, PDO::PARAM_INT);
        $stmtFaturasIgnoradas->execute();
        $faturasIgnoradas = $stmtFaturasIgnoradas->fetchAll(PDO::FETCH_ASSOC);

        // Registrar as faturas ignoradas no log
        if (count($faturasIgnoradas) > 0) {
            debug_print("Encontradas " . count($faturasIgnoradas) . " faturas ignoradas por estarem na tabela ERRO_CONTACT");

            foreach ($faturasIgnoradas as $fatura) {
                $logFiltradaQuery = "INSERT INTO LOG_FATURAS_FILTRADAS 
                    (ID_FATURA, CD_FATURA, NOME, CELULAR, VALOR, DATA_VENCIMENTO, DIFF_DIAS, MOTIVO, DATA_LOG) 
                    VALUES (:id_fatura, :cd_fatura, :nome, :celular, :valor, :data_vencimento, :diff_dias, :motivo, NOW())";
                $logFiltradaStmt = $conn->prepare($logFiltradaQuery);
                $logFiltradaStmt->bindParam(':id_fatura', $fatura['ID']);
                $logFiltradaStmt->bindParam(':cd_fatura', $fatura['CD_FATURA']);
                $logFiltradaStmt->bindParam(':nome', $fatura['NOME']);
                $logFiltradaStmt->bindParam(':celular', $fatura['CELULAR']);
                $logFiltradaStmt->bindParam(':valor', $fatura['VALOR']);
                $logFiltradaStmt->bindParam(':data_vencimento', $fatura['DATA_VENCIMENTO']);
                $logFiltradaStmt->bindParam(':diff_dias', $fatura['DIFF_DIAS']);
                $logFiltradaStmt->bindValue(':motivo', 'Contato na tabela ERRO_CONTACT com status NAO');
                $logFiltradaStmt->execute();
            }
        }

        // Busca faturas válidas (excluindo as que estão na tabela ERRO_CONTACT)
        $sqlFaturas = "SELECT FAV.ID, FAV.NOME, FAV.CELULAR, FAV.CD_FATURA, FAV.VALOR, FAV.DATA_VENCIMENTO,
        CURDATE() as DATA_HOJE,
        DATEDIFF(FAV.DATA_VENCIMENTO, CURDATE()) as DIFF_DIAS
        FROM FATURAS_A_VENCER FAV
        WHERE FAV.ENVIADO = 'NAO'
        AND DATEDIFF(FAV.DATA_VENCIMENTO, CURDATE()) = :intervalo
        AND NOT EXISTS (
        SELECT 1
        FROM ERRO_CONTACT EC
        WHERE EC.FATURA = FAV.CD_FATURA
        AND EC.STATUS = 'NAO'
        )
        AND NOT EXISTS (
        SELECT 1
        FROM ENVIOS_EM_ANDAMENTO EA
        WHERE EA.FATURA = FAV.CD_FATURA
        AND EA.TIMESTAMP > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        )";

        debug_print("SQL Faturas: " . $sqlFaturas);
        $stmtFaturas = $conn->prepare($sqlFaturas);
        $stmtFaturas->bindParam(':intervalo', $intervalo, PDO::PARAM_INT);
        $stmtFaturas->execute();
        $faturas = $stmtFaturas->fetchAll(PDO::FETCH_ASSOC);

        debug_print("Número de faturas encontradas: " . count($faturas));

        if (!$faturas) {
            debug_print("Nenhuma fatura encontrada para o intervalo {$intervalo}.");
            continue;
        }

        debug_print("Lista das primeiras 5 faturas encontradas (ou menos se houver menos):", array_slice($faturas, 0, 5));
        // ==================== PARA CADA FATURA, ENVIA A MENSAGEM ====================
        foreach ($faturas as $fatura) {
            $totalFaturasProcessadas++;
            debug_print("Processando fatura:", $fatura);

            $idFatura    = $fatura['ID'];
            $nomeCliente = $fatura['NOME'];
            $telefone    = preg_replace('/\D/', '', $fatura['CELULAR']);
            $cdFatura    = $fatura['CD_FATURA'];
            $valor       = number_format((float)$fatura['VALOR'], 2, ',', '.');
            $vencimento  = date('d/m/Y', strtotime($fatura['DATA_VENCIMENTO']));

            // Converte a data de vencimento para o formato 'Y-m-d' que será usado no banco
            $date = DateTime::createFromFormat('d/m/Y', $vencimento);
            $vencFormat = $date ? $date->format('Y-m-d') : null;
            debug_print("Data de vencimento convertida: " . $vencFormat);

            if (substr($telefone, 0, 2) !== '55') {
                $telefone = '55' . $telefone;
                debug_print("Telefone formatado com prefixo 55: " . $telefone);
            }

            if (in_array($cdFatura, $faturasProcessadas)) {
                debug_print("Fatura {$cdFatura} já foi processada nesta execução. Pulando...");
                continue;
            }

            // INÍCIO DO BLOQUEIO E VERIFICAÇÃO COM TRANSAÇÃO
            $conn->beginTransaction();
            try {
                // Verificação completa se já foi enviado hoje para esta fatura
                $checkQuery = "SELECT COUNT(*) FROM (
                    SELECT FATURA FROM LOG_ENVIOS 
                    WHERE FATURA = :fatura 
                    AND DATE(DATA_ENVIO) = CURDATE()
                    UNION 
                    SELECT FATURA FROM LOG_ENVIOS_FALHA
                    WHERE FATURA = :fatura 
                    AND DATE(DATA_ENVIO) = CURDATE()
                    UNION
                    SELECT FATURA FROM ENVIOS_EM_ANDAMENTO
                    WHERE FATURA = :fatura 
                    AND TIMESTAMP > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ) AS combined_checks";

                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->bindParam(':fatura', $cdFatura);
                $checkStmt->execute();
                $enviosHoje = (int)$checkStmt->fetchColumn();

                debug_print("Verificação de envios já realizados para {$cdFatura}: " . $enviosHoje);

                if ($enviosHoje > 0) {
                    debug_print("Mensagem já enviada hoje ou sendo processada para a fatura {$cdFatura}. Pulando...", null, 'WARNING');
                    $conn->rollBack();
                    continue;
                }
                // Adiciona bloqueio na tabela ENVIOS_EM_ANDAMENTO
                $lockQuery = "INSERT INTO ENVIOS_EM_ANDAMENTO (FATURA, TIMESTAMP, EXEC_ID) 
                VALUES (:fatura, NOW(), :exec_id)
                ON DUPLICATE KEY UPDATE TIMESTAMP = NOW(), EXEC_ID = :exec_id";

                $lockStmt = $conn->prepare($lockQuery);
                $lockStmt->bindParam(':fatura', $cdFatura);
                $lockStmt->bindParam(':exec_id', $execId);
                $lockResult = $lockStmt->execute();



                if ($lockResult) {
                    // Usando debug_print para logar no console e na tela
                    debug_print("Bloqueio registrado na tabela ENVIOS_EM_ANDAMENTO para fatura {$cdFatura} com EXEC_ID {$execId}.", null, 'INFO');
                } else {
                    $errorInfo = $lockStmt->errorInfo();
                    $mensagemErro = "Erro ao registrar bloqueio para fatura {$cdFatura}: " . $errorInfo[2];

                    // Logando o erro com debug_print
                    debug_print($mensagemErro, null, 'ERROR');

                    $conn->rollBack();
                    debug_print("Rollback executado. Pulando fatura {$cdFatura}.", null, 'WARNING');
                    continue;
                }


                // Se chegou aqui, temos o bloqueio exclusivo
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                debug_print("Erro ao verificar/bloquear fatura: " . $e->getMessage(), null, 'ERROR');
                continue;
            }
            // FIM DO BLOQUEIO E VERIFICAÇÃO COM TRANSAÇÃO

            $faturasProcessadas[] = $cdFatura;

            echo "Enviando para: {$nomeCliente} (Fatura: {$cdFatura}, Intervalo: {$intervalo})<br>";
            echo "Diferença em dias: {$fatura['DIFF_DIAS']}<br>";

            // ==================== PREPARAÇÃO DO PAYLOAD PARA 360dialog ====================
            debug_print("Preparando payload para 360dialog");
            $dataEnvioMsg = [
                "to"   => $telefone,
                "type" => "template",
                "template" => [
                    "namespace" => $templateNamespace,
                    "name"      => $modeloMensagem,
                    "language"  => [
                        "code"   => $languageCode,
                        "policy" => "deterministic"
                    ],
                    "components" => [
                        [
                            "type" => "body",
                            "parameters" => [
                                [
                                    "type" => "text",
                                    "text" => $nomeCliente
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            debug_print("Payload preparado:", $dataEnvioMsg);

            // Endpoint e chave para 360dialog - usar constantes do config.php
            $apiKey = API_KEY;
            $url360 = API_URL;

            debug_print("Usando API URL: " . $url360);

            // Aguarda um tempo aleatório entre 1 e 5 segundos
            $delay = rand(1, 5);
            debug_print("Aguardando {$delay} segundos antes de enviar a mensagem...");
            sleep($delay);

            $payload = json_encode($dataEnvioMsg);
            debug_print("Payload JSON:", $payload);

            // Inicia transação para garantir integridade dos dados
            debug_print("Iniciando transação no banco de dados para envio e registro");
            $conn->beginTransaction();

            try {
                debug_print("Configurando requisição cURL");
                $ch = curl_init($url360);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

                // Verificamos se estamos em ambiente de produção ou desenvolvimento
                // Em produção, sempre usar SSL; em dev, pode ser opcional
                $ambiente = defined('AMBIENTE') ? AMBIENTE : 'producao';
                if ($ambiente == 'producao') {
                    debug_print("Ambiente de produção: habilitando verificação SSL");
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                } else {
                    debug_print("Ambiente de desenvolvimento: desabilitando verificação SSL");
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                }

                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "D360-API-KEY: $apiKey",
                    "Content-Type: application/json"
                ]);

                debug_print("Executando requisição cURL");
                $responseEnvio = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_errno($ch);

                debug_print("Resultado da requisição: HTTP Code " . $httpCode);
                debug_print("Resposta da API:", $responseEnvio);

                if ($curlError) {
                    debug_print("Erro cURL: " . curl_strerror($curlError) . " (Código: " . $curlError . ")", null, 'ERROR');
                }

                curl_close($ch);

                $erroMsg = null;
                $sucessoEnvio = false;

                if ($curlError) {
                    debug_print("Erro cURL ao enviar para {$nomeCliente}: " . curl_strerror($curlError), null, 'ERROR');
                    $erroMsg = curl_strerror($curlError);
                    $enviosFalha++;
                    $totalEnviosFalha++;
                } elseif ($httpCode != 200 && $httpCode != 201) {
                    debug_print("Falha ao enviar (HTTP $httpCode): {$responseEnvio}", null, 'ERROR');
                    $erroMsg = $responseEnvio;
                    $enviosFalha++;
                    $totalEnviosFalha++;
                } else {
                    debug_print("Mensagem enviada com sucesso para {$nomeCliente}");
                    $sucessoEnvio = true;
                    $enviosSucesso++;
                    $totalEnviosSucesso++;
                }

                // ==================== REGISTRA LOG DE ENVIO ====================
                debug_print("Registrando log de " . ($sucessoEnvio ? "sucesso" : "falha"));

                if ($sucessoEnvio) {
                    $logQuery = "INSERT INTO LOG_ENVIOS 
                                (FATURA, CONTATO, CELULAR, NOME, VALOR, DATA_VENCIMENTO, DATA_ENVIO, MODELO_MENSAGEM, EXEC_ID) 
                                VALUES (:fatura, :contato, :celular, :nome, :valor, :data_vencimento, NOW(), :modelo_mensagem, :exec_id)";
                    $logStmt = $conn->prepare($logQuery);
                    $logStmt->bindParam(':fatura', $cdFatura);
                    $logStmt->bindParam(':contato', $telefone);
                    $logStmt->bindParam(':celular', $telefone);
                    $logStmt->bindParam(':nome', $nomeCliente);
                    $logStmt->bindParam(':valor', $valor);
                    $logStmt->bindParam(':data_vencimento', $vencFormat);
                    $logStmt->bindParam(':modelo_mensagem', $modeloMensagem);
                    $logStmt->bindParam(':exec_id', $execId);

                    // Após envio com sucesso, deleta a fatura enviada
                    $stmtDel = $conn->prepare("DELETE FROM FATURAS_A_VENCER WHERE ID = :id");
                    $stmtDel->bindParam(':id', $idFatura, PDO::PARAM_INT);
                    $stmtDel->execute();
                } else {
                    $logQuery = "INSERT INTO LOG_ENVIOS_FALHA 
                                (FATURA, CONTATO, CELULAR, NOME, VALOR, DATA_VENCIMENTO, DATA_ENVIO, ERRO, MODELO_MENSAGEM, EXEC_ID) 
                                VALUES (:fatura, :contato, :celular, :nome, :valor, :data_vencimento, NOW(), :erro, :modelo_mensagem, :exec_id)";
                    $logStmt = $conn->prepare($logQuery);
                    $logStmt->bindParam(':fatura', $cdFatura);
                    $logStmt->bindParam(':contato', $telefone);
                    $logStmt->bindParam(':celular', $telefone);
                    $logStmt->bindParam(':nome', $nomeCliente);
                    $logStmt->bindParam(':valor', $valor);
                    $logStmt->bindParam(':data_vencimento', $vencFormat);
                    $logStmt->bindValue(':erro', $erroMsg, PDO::PARAM_STR);
                    $logStmt->bindParam(':modelo_mensagem', $modeloMensagem);
                    $logStmt->bindParam(':exec_id', $execId);

                    // Insert na tabela de falhas ERRO_CONTACT para contatos inválidos
                    if (strpos($erroMsg, 'Recipient is not a valid WhatsApp user') !== false) {
                        $error_contact = "INSERT INTO ERRO_CONTACT
                        (FATURA, CONTATO, CELULAR, NOME, VALOR, DATA_VENCIMENTO, DATA_ENVIO, ERRO, MODELO_MENSAGEM, STATUS, EXEC_ID) 
                        VALUES (:fatura, :contato, :celular, :nome, :valor, :data_vencimento, NOW(), :erro, :modelo_mensagem, :status, :exec_id)";

                        debug_print("Inserindo erro de contato na tabela ERRO_CONTACT");

                        $error_contact_stmt = $conn->prepare($error_contact);
                        $error_contact_stmt->bindParam(':fatura', $cdFatura);
                        $error_contact_stmt->bindParam(':contato', $telefone);
                        $error_contact_stmt->bindParam(':celular', $telefone);
                        $error_contact_stmt->bindParam(':nome', $nomeCliente);
                        $error_contact_stmt->bindParam(':valor', $valor);
                        $error_contact_stmt->bindParam(':data_vencimento', $vencFormat);
                        $error_contact_stmt->bindValue(':erro', $erroMsg, PDO::PARAM_STR);
                        $error_contact_stmt->bindParam(':modelo_mensagem', $modeloMensagem);
                        $error_contact_stmt->bindValue(':status', "NAO", PDO::PARAM_STR);
                        $error_contact_stmt->bindParam(':exec_id', $execId);
                        $error_contact_stmt->execute();
                    }

                    // Após tentativa de envio, também deleta a fatura da tabela principal
                    $stmtDel = $conn->prepare("DELETE FROM FATURAS_A_VENCER WHERE ID = :id");
                    $stmtDel->bindParam(':id', $idFatura, PDO::PARAM_INT);
                    $stmtDel->execute();
                }

                debug_print("Executando inserção no log");
                $resultLog = $logStmt->execute();
                debug_print("Resultado da inserção no log: " . ($resultLog ? "Sucesso" : "Falha"));

                // Remover bloqueio após processamento


                // Remover bloqueio após processamento
                $removeLockQuery = "DELETE FROM ENVIOS_EM_ANDAMENTO 
                                    WHERE FATURA = :fatura AND EXEC_ID = :exec_id";
                $removeLockStmt = $conn->prepare($removeLockQuery);
                $removeLockStmt->bindParam(':fatura', $cdFatura);
                $removeLockStmt->bindParam(':exec_id', $execId);
                $removeLockStmt->execute();

                // Commit da transação
                $conn->commit();

                debug_print("Transação finalizada com sucesso");

                // Incrementa contador
                $contadorEnvios++;

                echo ($sucessoEnvio ?
                    "<span style='color:green'>✓ Enviado com sucesso</span>" :
                    "<span style='color:red'>✗ Falha no envio: " . htmlspecialchars($erroMsg) . "</span>") . "<br>";

                // Limite de segurança para não sobrecarregar a API
                if ($contadorEnvios >= 100) {
                    debug_print("Limite de 100 envios atingido. Interrompendo processamento.");
                    break;
                }
            } catch (Exception $e) {
                // Em caso de erro, faz rollback da transação
                $conn->rollBack();
                debug_print("Erro durante o processamento da fatura {$cdFatura}: " . $e->getMessage(), null, 'ERROR');
                echo "<span style='color:red'>Erro durante o processamento: " . htmlspecialchars($e->getMessage()) . "</span><br>";

                // Tenta remover o bloqueio
                try {
                    $removeLockQuery = "DELETE FROM ENVIOS_EM_ANDAMENTO WHERE FATURA = :fatura AND EXEC_ID = :exec_id";
                    $removeLockStmt = $conn->prepare($removeLockQuery);
                    $removeLockStmt->bindParam(':fatura', $cdFatura);
                    $removeLockStmt->bindParam(':exec_id', $execId);
                    $removeLockStmt->execute();
                } catch (Exception $lockError) {
                    debug_print("Erro ao remover bloqueio: " . $lockError->getMessage(), null, 'ERROR');
                }

                // Contabiliza como falha
                $enviosFalha++;
                $totalEnviosFalha++;
            }

            // Aguarda um pequeno intervalo entre envios para não sobrecarregar a API
            $sleepTime = rand(2, 5);
            debug_print("Aguardando {$sleepTime} segundos antes do próximo envio...");
            sleep($sleepTime);
        } // fim do loop de faturas

        // Relatório para esta régua
        echo "<br><strong>Resumo da régua {$nomeregua}:</strong><br>";
        echo "Total de faturas processadas: " . count($faturas) . "<br>";
        echo "Envios com sucesso: {$enviosSucesso}<br>";
        echo "Envios com falha: {$enviosFalha}<br>";

        debug_print("Resumo do processamento da régua {$nomeregua}", [
            'total_faturas' => count($faturas),
            'envios_sucesso' => $enviosSucesso,
            'envios_falha' => $enviosFalha
        ]);
    } // fim do loop de réguas

    // ==================== RESUMO FINAL ====================
    if ($reguasParaProcessar == 0) {
        debug_print("Nenhuma régua processada nesta execução. Verifique se os horários estão configurados corretamente.");
        echo "<hr><strong>Nenhuma régua foi processada nesta execução.</strong><br>";
        echo "Verifique se os horários das réguas estão configurados para este momento do dia.";
    } else {
        echo "<hr><h2>Resumo Final</h2>";
        echo "<strong>Réguas processadas:</strong> {$totalReguasProcessadas}<br>";
        echo "<strong>Total de faturas processadas:</strong> {$totalFaturasProcessadas}<br>";
        echo "<strong>Envios com sucesso:</strong> {$totalEnviosSucesso}<br>";
        echo "<strong>Envios com falha:</strong> {$totalEnviosFalha}<br>";

        debug_print("RESUMO FINAL", [
            'reguas_processadas' => $totalReguasProcessadas,
            'faturas_processadas' => $totalFaturasProcessadas,
            'envios_sucesso' => $totalEnviosSucesso,
            'envios_falha' => $totalEnviosFalha
        ]);
    }

    // Registra o log final na tabela de execuções
    $logExecucaoQuery = "INSERT INTO LOG_EXECUCOES 
                        (DATA_EXECUCAO, EXEC_ID, REGUAS_PROCESSADAS, FATURAS_PROCESSADAS, 
                         ENVIOS_SUCESSO, ENVIOS_FALHA, TEMPO_EXECUCAO) 
                        VALUES 
                        (NOW(), :exec_id, :reguas, :faturas, :sucessos, :falhas, 
                         TIMEDIFF(NOW(), (SELECT MIN(DATA_ENVIO) FROM LOG_ENVIOS WHERE EXEC_ID = :exec_id2)))";

    try {
        $logExecucaoStmt = $conn->prepare($logExecucaoQuery);
        $logExecucaoStmt->bindParam(':exec_id', $execId);
        $logExecucaoStmt->bindParam(':exec_id2', $execId);
        $logExecucaoStmt->bindParam(':reguas', $totalReguasProcessadas);
        $logExecucaoStmt->bindParam(':faturas', $totalFaturasProcessadas);
        $logExecucaoStmt->bindParam(':sucessos', $totalEnviosSucesso);
        $logExecucaoStmt->bindParam(':falhas', $totalEnviosFalha);
        $logExecucaoStmt->execute();

        debug_print("Log de execução registrado com sucesso.");
    } catch (Exception $e) {
        debug_print("Erro ao registrar log de execução: " . $e->getMessage(), null, 'ERROR');
    }
} catch (Exception $e) {
    // Captura exceções gerais
    debug_print("ERRO FATAL durante a execução do script: " . $e->getMessage(), null, 'ERROR');
    echo "<hr><strong style='color:red'>ERRO FATAL:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Verifique os logs para mais informações.";

    // Tenta registrar o erro no log de execuções
    try {
        $logErroQuery = "INSERT INTO LOG_EXECUCOES 
                        (DATA_EXECUCAO, EXEC_ID, ERRO, REGUAS_PROCESSADAS, FATURAS_PROCESSADAS, 
                         ENVIOS_SUCESSO, ENVIOS_FALHA) 
                        VALUES 
                        (NOW(), :exec_id, :erro, :reguas, :faturas, :sucessos, :falhas)";

        $logErroStmt = $conn->prepare($logErroQuery);
        $logErroStmt->bindParam(':exec_id', $execId);
        $logErroStmt->bindValue(':erro', $e->getMessage());
        $logErroStmt->bindParam(':reguas', $totalReguasProcessadas);
        $logErroStmt->bindParam(':faturas', $totalFaturasProcessadas);
        $logErroStmt->bindParam(':sucessos', $totalEnviosSucesso);
        $logErroStmt->bindParam(':falhas', $totalEnviosFalha);
        $logErroStmt->execute();
    } catch (Exception $logError) {
        debug_print("Não foi possível registrar o erro no log: " . $logError->getMessage(), null, 'ERROR');
    }
} finally {
    // Limpa os bloqueios criados por esta execução
    try {
        echo "excid" . $execId; // Verifica o valor de $execId

        $cleanLockQuery = "DELETE FROM ENVIOS_EM_ANDAMENTO WHERE EXEC_ID = :exec_id";
        $cleanLockStmt = $conn->prepare($cleanLockQuery);
        $cleanLockStmt->bindParam(':exec_id', $execId);
        $cleanLockStmt->execute();
        debug_print("Bloqueios limpos com sucesso");
    } catch (Exception $e) {
        debug_print("Erro ao limpar bloqueios: " . $e->getMessage(), null, 'ERROR');
    }

    // Tempo total de execução
    $tempoFinal = microtime(true);
    $tempoTotal = $tempoFinal - $tempoInicial;
    debug_print("Tempo total de execução: " . number_format($tempoTotal, 2) . " segundos");

    echo "<hr><p>Tempo de execução: " . number_format($tempoTotal, 2) . " segundos</p>";
    echo "<p><small>ID de execução: {$execId}</small></p>";

    debug_print("Script finalizado");
}
