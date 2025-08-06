<?php
// ==================== CONFIGURAÇÃO INICIAL ====================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define o fuso horário para Brasil
date_default_timezone_set('America/Sao_Paulo');

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

// ==================== INCLUSÃO DE ARQUIVOS ====================
try {
    // Corrigido: Caminhos com barras separadoras adequadas
    /*     if (file_exists(__DIR__ . "/../config/config_360.php")) {
        require_once __DIR__ . "/../config/config_360.php";
        debug_print("Arquivo config_360.php carregado com sucesso");
    } else {
        debug_print("ERRO: Arquivo config_360.php não encontrado - criando valores padrão temporários", null, 'WARNING');
        // Valores temporários para desenvolvimento se necessário
    } */

    debug_print("Tentando incluir conn.php");
    if (file_exists(__DIR__ . "/config/conn.php")) {
    include_once __DIR__ . "/config/conn.php";
        debug_print("Arquivo conn.php incluído");
    } else {
        throw new Exception("ERRO FATAL: Arquivo conn.php não encontrado");
    }


    debug_print("Tentando incluir config_wpp.php");
    if (file_exists(__DIR__ . "/../config/config_wpp.php")) {
        if (file_exists(__DIR__ . "/config/config_wpp.php")) {
    include_once __DIR__ . "/config/config_wpp.php";
        debug_print("Arquivo config_wpp.php incluído");
    } else {
        throw new Exception("ERRO FATAL: Arquivo config_wpp.php não encontrado");
    }


    debug_print("Tentando incluir wpp_api.php");
    if (file_exists(__DIR__ . "/api/wpp_api.php")) {
    include_once __DIR__ . "/api/wpp_api.php";
        debug_print("Arquivo wpp_api.php incluído");
    } else {
        throw new Exception("ERRO FATAL: Arquivo wpp_api.php não encontrado");
    }
} catch (Exception $e) {
    debug_print("ERRO FATAL na inclusão de arquivos: " . $e->getMessage(), null, 'ERROR');
    die();
}

// ==================== VERIFICAÇÃO DE CONEXÃO ====================
try {
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception("Conexão com banco de dados não está disponível ou não é um objeto PDO válido");
    }

    // Teste de conexão
    $conn->query("SELECT 1");
    debug_print("Conexão com banco de dados verificada com sucesso");
} catch (Exception $e) {
    debug_print("ERRO FATAL na conexão com banco: " . $e->getMessage(), null, 'ERROR');
    die();
}

// ==================== CONFIGURAÇÃO DE TABELAS ====================
try {
    // Criar tabela de bloqueio de script se não existir
    $conn->exec("CREATE TABLE IF NOT EXISTS SCRIPT_LOCKS (
        lock_name VARCHAR(50) PRIMARY KEY,
        acquired_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL
    )");

    // Criar tabela de envios em andamento se não existir
    $conn->exec("CREATE TABLE IF NOT EXISTS ENVIOS_EM_ANDAMENTO (
        FATURA VARCHAR(255) PRIMARY KEY,
        TIMESTAMP DATETIME NOT NULL,
        EXEC_ID VARCHAR(255) NOT NULL
    )");

    // Criar tabela de log de faturas filtradas se não existir
    $conn->exec("CREATE TABLE IF NOT EXISTS LOG_FATURAS_FILTRADAS (
        ID INT AUTO_INCREMENT PRIMARY KEY,
        ID_FATURA INT,
        CD_FATURA VARCHAR(255),
        NOME VARCHAR(255),
        CELULAR VARCHAR(20),
        VALOR DECIMAL(10,2),
        DATA_VENCIMENTO DATE,
        DIFF_DIAS INT,
        MOTIVO TEXT,
        DATA_LOG DATETIME
    )");

    debug_print("Tabelas verificadas/criadas com sucesso");
} catch (Exception $e) {
    debug_print("ERRO ao configurar tabelas: " . $e->getMessage(), null, 'ERROR');
    die();
}

// ==================== FUNÇÕES DE CONTROLE ====================
function acquireScriptLock($conn)
{
    try {
        $lockName = 'script_envio_faturas_lock';
        $expiry = 1; // minutos

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
    } catch (Exception $e) {
        debug_print("Erro ao adquirir bloqueio: " . $e->getMessage(), null, 'ERROR');
        return false;
    }
}

function releaseScriptLock($conn)
{
    try {
        $lockName = 'script_envio_faturas_lock';
        $sql = "DELETE FROM SCRIPT_LOCKS WHERE lock_name = :lock_name";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':lock_name', $lockName);
        $stmt->execute();
        debug_print("Bloqueio de script liberado");
    } catch (Exception $e) {
        debug_print("Erro ao liberar bloqueio: " . $e->getMessage(), null, 'ERROR');
    }
}

function cleanupOldLocks($conn)
{
    try {
        $conn->exec("DELETE FROM ENVIOS_EM_ANDAMENTO WHERE TIMESTAMP < NOW() - INTERVAL 10 MINUTE");
        debug_print("Registros antigos de ENVIOS_EM_ANDAMENTO (>10min) foram limpos");
    } catch (Exception $e) {
        debug_print("Erro ao limpar locks antigos: " . $e->getMessage(), null, 'ERROR');
    }
}

// ==================== VERIFICAÇÕES INICIAIS ====================
try {
    // Verifica se pode executar o script
    if (!acquireScriptLock($conn)) {
        debug_print("Outra instância do script está em execução. Encerrando.", null, 'WARNING');
        exit;
    }

    // Limpa bloqueios de envios antigos
    cleanupOldLocks($conn);



    // Verificação de dia da semana
    $diaSemana = date('w');
    debug_print("Verificando dia da semana: " . $diaSemana . " (0=Domingo, 6=Sábado)");
    if ($diaSemana == 0 || $diaSemana == 6) {
        debug_print("Envio desabilitado nos finais de semana. Encerrando script.");
        releaseScriptLock($conn);
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

    // Verificação de feriado
    $dataHoje = date('d/m/Y');
    debug_print("Verificando feriados para a data: " . $dataHoje);

    if (in_array($dataHoje, $feriados)) {
        debug_print("Envio desabilitado em feriados. Encerrando script.");
        releaseScriptLock($conn);
        exit;
    }

    debug_print("Dia útil. Continuando com a execução.");
} catch (Exception $e) {
    debug_print("ERRO nas verificações iniciais: " . $e->getMessage(), null, 'ERROR');
    releaseScriptLock($conn);
    die();
}

// ==================== PROCESSAMENTO DAS RÉGUAS ====================
try {
    debug_print("Iniciando busca de réguas ativas");

    // Seleciona todas as réguas ativas
    $sqlReguas = "SELECT id, nome, intervalo, hora, modelo_mensagem, status, REGUA_ID, TIPO_DE_MODELO, Session_WPP, data_criacao 
                  FROM REGUAS_CRIADAS 
                  WHERE status = 'Ativo'";

    debug_print("Executando query de réguas: " . $sqlReguas);
    $stmtReguas = $conn->prepare($sqlReguas);
    $stmtReguas->execute();
    $reguasAtivas = $stmtReguas->fetchAll(PDO::FETCH_ASSOC);

    debug_print("Número de réguas encontradas: " . count($reguasAtivas));

    if (!$reguasAtivas) {
        debug_print("Nenhuma régua ativa encontrada. Encerrando script.");
        releaseScriptLock($conn);
        exit;
    }

    debug_print("Lista completa de réguas ativas:", $reguasAtivas);

    $currentTime = date('H:i');
    debug_print("Hora atual: " . $currentTime);

    $reguasParaProcessar = 0;

    foreach ($reguasAtivas as $regua) {
        try {
            debug_print("Processando régua:", $regua);

            // Inicializa variáveis para cada régua
            $reguaId = $regua['REGUA_ID'];
            $nomeregua = $regua['nome'];
            $intervalo = (int)$regua['intervalo'];
            $hora = $regua['hora'];
            $modeloMensagem = $regua['modelo_mensagem'];
            $GatewayEnvio = $regua['TIPO_DE_MODELO'];
            $Session_WPP = $regua['Session_WPP'];

            // Inicializa contadores para cada régua
            $contadorEnvios = 0;
            $enviosSucesso = 0;
            $enviosFalha = 0;
            $faturasProcessadas = array();

            // Validações básicas
            if (empty($modeloMensagem) || empty($Session_WPP)) {
                debug_print("Dados da régua incompletos. Pulando régua.", null, 'WARNING');
                continue;
            }

            // Obtém os dados de configuração
            $templateNamespace = defined('TEMPLATE_NAMESPACE') ? TEMPLATE_NAMESPACE : '';
            $languageCode = defined('LANGUAGE_CODE') ? LANGUAGE_CODE : 'pt_BR';

            // Converte os horários para DateTime
            $horaRegua = DateTime::createFromFormat('H:i', substr($hora, 0, 5));
            $currentTimeObj = DateTime::createFromFormat('H:i', substr($currentTime, 0, 5));

            if (!$horaRegua || !$currentTimeObj) {
                debug_print("Erro ao converter horários. Pulando régua.", null, 'ERROR');
                continue;
            }

            $inicioPermitido = clone $horaRegua;
            $fimPermitido = clone $horaRegua;

            // Ajusta janela de tempo baseado no gateway
            if ($GatewayEnvio === 'Wpp-Connect' || $GatewayEnvio === 'Wpp - Connect') {
                $fimPermitido->add(new DateInterval('PT6H')); // 6 horas para WPP-Connect
            } else {
                $fimPermitido->add(new DateInterval('PT2H')); // 2 horas para outros
            }

            debug_print("Verificando horário da régua: " . $horaRegua->format('H:i') . " vs hora atual: " . $currentTimeObj->format('H:i'));
            debug_print("Prazo para finalização: " . $fimPermitido->format('H:i'));

            // Verifica se está dentro da janela permitida
            if ($currentTimeObj < $inicioPermitido || $currentTimeObj > $fimPermitido) {
                debug_print("Horário atual fora da janela permitida ({$inicioPermitido->format('H:i')} - {$fimPermitido->format('H:i')}). Pulando régua.");
                continue;
            }

            // Verifica se é régua pós-vencimento
            debug_print("Verificando intervalo da régua: " . $intervalo);
            if ($intervalo < 0) {
                debug_print("Régua '{$nomeregua}' ignorada por não ser pós-vencimento ({$intervalo}).");
                continue;
            }

            // Verifica se é WPP-Connect
            if ($GatewayEnvio !== 'Wpp-Connect' && $GatewayEnvio !== 'Wpp - Connect') {
                debug_print("Régua '{$nomeregua}' não é WPP-Connect. Pulando.");
                continue;
            }

            $reguasParaProcessar++;
            $totalReguasProcessadas++;
            debug_print("Régua selecionada para processamento!");
            echo "<hr><strong>Processando a régua: {$nomeregua}</strong><br>";
            echo "Intervalo: {$intervalo} | Hora: {$hora} | Modelo: {$modeloMensagem}<br>";

            // ==================== BUSCA E FILTRAGEM DE FATURAS ====================
            debug_print("Buscando faturas para o intervalo " . $intervalo);

            // Identifica faturas que seriam filtradas por estarem na tabela ERRO_CONTACT
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

            // Registra faturas ignoradas no log
            if (count($faturasIgnoradas) > 0) {
                debug_print("Encontradas " . count($faturasIgnoradas) . " faturas ignoradas por estarem na tabela ERRO_CONTACT");

                foreach ($faturasIgnoradas as $fatura) {
                    try {
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

                        // Remove fatura filtrada
                        $log_delete_filtrada = "DELETE FROM FATURAS_A_VENCER WHERE ID = :id_fatura";
                        $stmtDelFiltrada = $conn->prepare($log_delete_filtrada);
                        $stmtDelFiltrada->bindParam(':id_fatura', $fatura['ID']);
                        $stmtDelFiltrada->execute();
                    } catch (Exception $e) {
                        debug_print("Erro ao processar fatura ignorada: " . $e->getMessage(), null, 'ERROR');
                    }
                }
            }

            // Busca faturas válidas
            $sqlFaturas = "SELECT FAV.ID, FAV.NOME, FAV.CELULAR, FAV.CD_FATURA, FAV.VALOR, FAV.DATA_VENCIMENTO,
                           CURDATE() as DATA_HOJE,
                           DATEDIFF(FAV.DATA_VENCIMENTO, CURDATE()) as DIFF_DIAS
                           FROM FATURAS_A_VENCER FAV
                           WHERE FAV.ENVIADO = 'NAO'
                           AND DATEDIFF(FAV.DATA_VENCIMENTO, CURDATE()) = :intervalo
                           AND NOT EXISTS (
                               SELECT 1 FROM ERRO_CONTACT EC
                               WHERE EC.FATURA = FAV.CD_FATURA AND EC.STATUS = 'NAO'
                           )
                           AND NOT EXISTS (
                               SELECT 1 FROM ENVIOS_EM_ANDAMENTO EA
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

            debug_print("Lista das primeiras 5 faturas encontradas:", array_slice($faturas, 0, 5));

            // ==================== PROCESSAMENTO DAS FATURAS ====================
            foreach ($faturas as $fatura) {
                try {
                    $totalFaturasProcessadas++;
                    debug_print("Processando fatura:", $fatura);

                    // Validação dos dados da fatura
                    if (empty($fatura['CELULAR']) || empty($fatura['VALOR']) || empty($fatura['NOME'])) {
                        debug_print("Dados da fatura inválidos. Pulando fatura.", null, 'WARNING');
                        continue;
                    }

                    $idFatura = $fatura['ID'];
                    $nomeCliente = $fatura['NOME'];
                    $telefone = preg_replace('/\D/', '', $fatura['CELULAR']);
                    $cdFatura = $fatura['CD_FATURA'];
                    $valor = number_format((float)$fatura['VALOR'], 2, ',', '.');
                    $vencimento = date('d/m/Y', strtotime($fatura['DATA_VENCIMENTO']));

                    // Converte data de vencimento para formato do banco
                    $date = DateTime::createFromFormat('d/m/Y', $vencimento);
                    $vencFormat = $date ? $date->format('Y-m-d') : null;

                    if (!$vencFormat) {
                        debug_print("Erro ao converter data de vencimento. Pulando fatura.", null, 'ERROR');
                        continue;
                    }

                    debug_print("Data de vencimento convertida: " . $vencFormat);

                    // Formata telefone com prefixo 55
                    if (substr($telefone, 0, 2) !== '55') {
                        $telefone = '55' . $telefone;
                    }
                    debug_print("Telefone formatado: " . $telefone);

                    // Verifica se já foi processada nesta execução
                    if (in_array($cdFatura, $faturasProcessadas)) {
                        debug_print("Fatura {$cdFatura} já foi processada nesta execução. Pulando...");
                        continue;
                    }

                    // ==================== CONTROLE DE CONCORRÊNCIA ====================
                    $conn->beginTransaction();
                    try {
                        // Verificação se já foi enviado hoje
                        $checkQuery = "SELECT COUNT(*) FROM (
                            SELECT FATURA FROM LOG_ENVIOS 
                            WHERE FATURA = :fatura AND DATE(DATA_ENVIO) = CURDATE()
                            UNION 
                            SELECT FATURA FROM LOG_ENVIOS_FALHA
                            WHERE FATURA = :fatura AND DATE(DATA_ENVIO) = CURDATE()
                            UNION
                            SELECT FATURA FROM ENVIOS_EM_ANDAMENTO
                            WHERE FATURA = :fatura AND TIMESTAMP > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
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

                        // Adiciona bloqueio
                        $lockQuery = "INSERT INTO ENVIOS_EM_ANDAMENTO (FATURA, TIMESTAMP, EXEC_ID) 
                                     VALUES (:fatura, NOW(), :exec_id)
                                     ON DUPLICATE KEY UPDATE TIMESTAMP = NOW(), EXEC_ID = :exec_id";

                        $lockStmt = $conn->prepare($lockQuery);
                        $lockStmt->bindParam(':fatura', $cdFatura);
                        $lockStmt->bindParam(':exec_id', $execId);
                        $lockResult = $lockStmt->execute();

                        if (!$lockResult) {
                            $errorInfo = $lockStmt->errorInfo();
                            debug_print("Erro ao registrar bloqueio para fatura {$cdFatura}: " . $errorInfo[2], null, 'ERROR');
                            $conn->rollBack();
                            continue;
                        }

                        debug_print("Bloqueio registrado para fatura {$cdFatura}");
                        $conn->commit();
                    } catch (Exception $e) {
                        $conn->rollBack();
                        debug_print("Erro ao verificar/bloquear fatura: " . $e->getMessage(), null, 'ERROR');
                        continue;
                    }

                    // Adiciona à lista de processadas
                    $faturasProcessadas[] = $cdFatura;

                    echo "Enviando para: {$nomeCliente} (Fatura: {$cdFatura}, Intervalo: {$intervalo})<br>";
                    echo "Diferença em dias: {$fatura['DIFF_DIAS']}<br>";

                    // ==================== ENVIO VIA WPP-CONNECT ====================
                    debug_print("Preparando mensagem personalizada para envio via WPP-Connect");

                    $buscar_modelo_msg = "SELECT * FROM DBA_MODELOS_MSG WHERE nome = :modeloMensagem";
                    $stmt = $conn->prepare($buscar_modelo_msg);
                    $stmt->bindParam(':modeloMensagem', $modeloMensagem);
                    $stmt->execute();
                    $modeloDados = $stmt->fetch(PDO::FETCH_ASSOC);

                    $mensagemModeloTexto = $modeloDados['conteudo'];

                    $mensagemFinal = str_replace(
                        ['{1}', '{2}', '{3}', '{4}'],
                        [$nomeCliente, $cdFatura, $valor, $vencimento],
                        $mensagemModeloTexto
                    );


                    debug_print("Mensagem final montada:", $mensagemFinal);

                    // Delay para simular envio humano
                    $delay = rand(1, 5);
                    debug_print("Aguardando {$delay} segundos antes de enviar...");
                    sleep($delay);

                    // Envio da mensagem
                    debug_print("Enviando mensagem via WPP-Connect para {$telefone}");

                    $conn->beginTransaction();
                    try {
                        $result = sendMessage($Session_WPP, $telefone, $mensagemFinal);
                        debug_print("Result send MMessage: " . json_encode($result), null, 'INFO');
                        $erroMsg = null;
                        $sucessoEnvio = false;


                        $payloadJson = json_encode([
                            'session' => $Session_WPP,
                            'phone' => $telefone,
                            'message' => $mensagemFinal
                        ], JSON_UNESCAPED_UNICODE);

                        debug_print("Payload JSON: " . $payloadJson, null, 'INFO');

                        if (isset($result['success']) && $result['success'] === true) {
                            debug_print("Mensagem enviada com sucesso para {$nomeCliente}");
                            $sucessoEnvio = true;
                            $enviosSucesso++;
                            $totalEnviosSucesso++;
                        } else {
                            $erroMsg = $result['error'] ?? json_encode($result['response']);
                            debug_print("Erro ao enviar mensagem: " . $erroMsg, null, 'ERROR');
                            $enviosFalha++;
                            $totalEnviosFalha++;
                        }


                        // ==================== REGISTRO DE LOG ====================


                        if ($sucessoEnvio) {
                            // Continuação do código a partir do LOG_ENVIOS
                            $logQuery = "INSERT INTO LOG_ENVIOS (FATURA, CONTATO, CELULAR, NOME, VALOR, DATA_VENCIMENTO, DATA_ENVIO, MODELO_MENSAGEM, EXEC_ID, PAYLOAD_JSON) 
                                                                    VALUES (:fatura, :contato, :celular, :nome, :valor, :data_vencimento, NOW(), :modelo_mensagem, :exec_id, :payload_json)";

                            $logStmt = $conn->prepare($logQuery);
                            $logStmt->bindParam(':fatura', $cdFatura);
                            $logStmt->bindParam(':contato', $nomeCliente);
                            $logStmt->bindParam(':celular', $telefone);
                            $logStmt->bindParam(':nome', $nomeCliente);
                            $logStmt->bindParam(':valor', $fatura['VALOR']);
                            $logStmt->bindParam(':data_vencimento', $vencFormat);
                            $logStmt->bindParam(':modelo_mensagem', $modeloMensagem);
                            $logStmt->bindParam(':exec_id', $execId);
                            $logStmt->bindParam(':payload_json', $payloadJson);

                            if (!$logStmt->execute()) {
                                $errorInfo = $logStmt->errorInfo();
                                debug_print("Erro ao registrar log de sucesso: " . $errorInfo[2], null, 'ERROR');
                            }

                            // Atualiza fatura como enviada
                            $updateQuery = "UPDATE FATURAS_A_VENCER SET ENVIADO = 'SIM' WHERE ID = :id_fatura";
                            $updateStmt = $conn->prepare($updateQuery);
                            $updateStmt->bindParam(':id_fatura', $idFatura);

                            if (!$updateStmt->execute()) {
                                $errorInfo = $updateStmt->errorInfo();
                                debug_print("Erro ao atualizar fatura como enviada: " . $errorInfo[2], null, 'ERROR');
                            }
                        } else {
                            // Log de falha
                            $logFalhaQuery = "INSERT INTO LOG_ENVIOS_FALHA (FATURA, CONTATO, CELULAR, NOME, VALOR, DATA_VENCIMENTO, DATA_ENVIO, ERRO, MODELO_MENSAGEM, EXEC_ID, PAYLOAD_JSON) 
                                                                        VALUES (:fatura, :contato, :celular, :nome, :valor, :data_vencimento, NOW(), :erro, :modelo_mensagem, :exec_id, :payload_json)";

                            $logFalhaStmt = $conn->prepare($logFalhaQuery);
                            $logFalhaStmt->bindParam(':fatura', $cdFatura);
                            $logFalhaStmt->bindParam(':contato', $nomeCliente);
                            $logFalhaStmt->bindParam(':celular', $telefone);
                            $logFalhaStmt->bindParam(':nome', $nomeCliente);
                            $logFalhaStmt->bindParam(':valor', $fatura['VALOR']);
                            $logFalhaStmt->bindParam(':data_vencimento', $vencFormat);
                            $logFalhaStmt->bindParam(':modelo_mensagem', $modeloMensagem);
                            $logFalhaStmt->bindParam(':erro_mensagem', $erroMsg);
                            $logFalhaStmt->bindParam(':exec_id', $execId);
                            $logFalhaStmt->bindParam(':payload_json', $payloadJson);

                            if (!$logFalhaStmt->execute()) {
                                $errorInfo = $logFalhaStmt->errorInfo();
                                debug_print("Erro ao registrar log de falha: " . $errorInfo[2], null, 'ERROR');
                            }

                            // Adiciona à tabela de erros de contato
                            $erroContactQuery = "INSERT INTO ERRO_CONTACT (FATURA, CONTATO, DATA_ERRO, MOTIVO, STATUS) 
                                                                           VALUES (:fatura, :contato, NOW(), :motivo, 'NAO')
                                                                           ON DUPLICATE KEY UPDATE 
                                                                           DATA_ERRO = NOW(), 
                                                                           MOTIVO = :motivo";

                            $erroContactStmt = $conn->prepare($erroContactQuery);
                            $erroContactStmt->bindParam(':fatura', $cdFatura);
                            $erroContactStmt->bindParam(':contato', $telefone);
                            $erroContactStmt->bindParam(':motivo', $erroMsg);

                            if (!$erroContactStmt->execute()) {
                                $errorInfo = $erroContactStmt->errorInfo();
                                debug_print("Erro ao registrar erro de contato: " . $errorInfo[2], null, 'ERROR');
                            }
                        }

                        // Remove bloqueio
                        $unlockQuery = "DELETE FROM ENVIOS_EM_ANDAMENTO WHERE FATURA = :fatura";
                        $unlockStmt = $conn->prepare($unlockQuery);
                        debug_print("Desbloqueando fatura  // Remove bloqueio: {$cdFatura}", null, 'INFO');

                        $unlockStmt->bindParam(':fatura', $cdFatura);
                        $unlockStmt->execute();

                        $conn->commit();
                        debug_print("Transação finalizada com sucesso para fatura {$cdFatura}");
                    } catch (Exception $e) {
                        $conn->rollBack();
                        debug_print("Erro na transação de envio: " . $e->getMessage(), null, 'ERROR');

                        // Remove bloqueio em caso de erro
                        try {
                            $unlockQuery = "DELETE FROM ENVIOS_EM_ANDAMENTO WHERE FATURA = :fatura";
                            $unlockStmt = $conn->prepare($unlockQuery);
                            debug_print("Desbloqueando fatura // Remove bloqueio em caso de erro: {$cdFatura}", null, 'INFO');

                            $unlockStmt->bindParam(':fatura', $cdFatura);
                            $unlockStmt->execute();
                        } catch (Exception $unlockError) {
                            debug_print("Erro ao remover bloqueio em caso de falha: " . $unlockError->getMessage(), null, 'ERROR');
                        }

                        $enviosFalha++;
                        $totalEnviosFalha++;
                        continue;
                    }

                    $contadorEnvios++;
                    echo "Status: " . ($sucessoEnvio ? "Enviado" : "Falha") . "<br>";
                    echo "Total processado nesta régua: {$contadorEnvios}<br><br>";

                    // Controle de limite de envios por régua
                    if ($contadorEnvios >= 50) {
                        debug_print("Limite de 50 envios por régua atingido. Parando processamento desta régua.");
                        break;
                    }

                    // Pausa entre envios
                    sleep(2);
                } catch (Exception $e) {
                    debug_print("Erro ao processar fatura {$cdFatura}: " . $e->getMessage(), null, 'ERROR');

                    // Remove bloqueio em caso de erro
                    try {
                        $unlockQuery = "DELETE FROM ENVIOS_EM_ANDAMENTO WHERE FATURA = :fatura";
                        $unlockStmt = $conn->prepare($unlockQuery);
                        debug_print("Desbloqueando fatura // Remove bloqueio em caso de erro 02: {$cdFatura}", null, 'INFO');

                        $unlockStmt->bindParam(':fatura', $cdFatura);
                        $unlockStmt->execute();
                    } catch (Exception $unlockError) {
                        debug_print("Erro ao remover bloqueio após falha: " . $unlockError->getMessage(), null, 'ERROR');
                    }

                    continue;
                }
            }

            // ==================== RELATÓRIO DA RÉGUA ====================
            echo "<hr><strong>Relatório da Régua: {$nomeregua}</strong><br>";
            echo "Total de faturas processadas: {$contadorEnvios}<br>";
            echo "Sucessos: {$enviosSucesso}<br>";
            echo "Falhas: {$enviosFalha}<br>";
            echo "Taxa de sucesso: " . ($contadorEnvios > 0 ? round(($enviosSucesso / $contadorEnvios) * 100, 2) : 0) . "%<br>";

            debug_print("Régua '{$nomeregua}' finalizada", [
                'total_processadas' => $contadorEnvios,
                'sucessos' => $enviosSucesso,
                'falhas' => $enviosFalha,
                'taxa_sucesso' => ($contadorEnvios > 0 ? round(($enviosSucesso / $contadorEnvios) * 100, 2) : 0) . '%'
            ]);
        } catch (Exception $e) {
            debug_print("Erro ao processar régua '{$nomeregua}': " . $e->getMessage(), null, 'ERROR');
            continue;
        }
    }

    // ==================== RELATÓRIO FINAL ====================
    $tempoFinal = microtime(true);
    $tempoExecucao = round($tempoFinal - $tempoInicial, 2);

    echo "<hr><strong>RELATÓRIO FINAL DA EXECUÇÃO</strong><br>";
    echo "ID da Execução: {$execId}<br>";
    echo "Tempo de execução: {$tempoExecucao} segundos<br>";
    echo "Réguas processadas: {$totalReguasProcessadas}<br>";
    echo "Faturas processadas: {$totalFaturasProcessadas}<br>";
    echo "Total de envios com sucesso: {$totalEnviosSucesso}<br>";
    echo "Total de envios com falha: {$totalEnviosFalha}<br>";
    echo "Taxa de sucesso geral: " . (($totalEnviosSucesso + $totalEnviosFalha) > 0 ?
        round(($totalEnviosSucesso / ($totalEnviosSucesso + $totalEnviosFalha)) * 100, 2) : 0) . "%<br>";

    debug_print("Execução finalizada", [
        'exec_id' => $execId,
        'tempo_execucao' => $tempoExecucao . 's',
        'reguas_processadas' => $totalReguasProcessadas,
        'faturas_processadas' => $totalFaturasProcessadas,
        'envios_sucesso' => $totalEnviosSucesso,
        'envios_falha' => $totalEnviosFalha,
        'taxa_sucesso_geral' => (($totalEnviosSucesso + $totalEnviosFalha) > 0 ?
            round(($totalEnviosSucesso / ($totalEnviosSucesso + $totalEnviosFalha)) * 100, 2) : 0) . '%'
    ]);

    if ($reguasParaProcessar == 0) {
        debug_print("Nenhuma régua estava no horário correto para processamento.");
        echo "<strong>Nenhuma régua estava no horário correto para processamento.</strong><br>";
    }
} catch (Exception $e) {
    debug_print("ERRO FATAL no processamento das réguas: " . $e->getMessage(), null, 'ERROR');
    echo "<strong>ERRO FATAL:</strong> " . $e->getMessage() . "<br>";
} finally {
    // ==================== LIMPEZA FINAL ====================
    try {
        // Remove bloqueios antigos
        $conn->exec("DELETE FROM ENVIOS_EM_ANDAMENTO WHERE TIMESTAMP < NOW() - INTERVAL 10 MINUTE");
        debug_print("Limpeza final de bloqueios antigos concluída");

        // Libera bloqueio do script
        releaseScriptLock($conn);
        debug_print("Script finalizado e bloqueio liberado");
    } catch (Exception $e) {
        debug_print("Erro na limpeza final: " . $e->getMessage(), null, 'ERROR');
    }
}



debug_print("=== SCRIPT FINALIZADO ===");
