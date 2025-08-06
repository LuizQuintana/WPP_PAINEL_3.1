<?php
// =================================================================
// ==================== CONFIGURAÇÃO INICIAL =======================
// =================================================================

// Habilita a exibição de todos os erros (útil para depuração)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define o fuso horário para São Paulo, Brasil, para garantir que as datas e horas sejam consistentes
date_default_timezone_set('America/Sao_Paulo');

// Gera um ID único para cada Execucao do script, facilitando o rastreamento nos logs
$execId = uniqid('exec_', true);
// Registra o tempo de início para calcular a duração total da Execucao
$tempoInicial = microtime(true);




// Define o caminho para o arquivo de log
$log_date = date('Y-m-d');
define('LOG_FILE', __DIR__ . '/storage/ENVIOWPP_VENCIDO' . $log_date . '.log');

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

log_message("Iniciando Execucao de ENVIOWPP_VENCIDO.php", "INFO");

/**
 * Função de debug para imprimir mensagens formatadas.
 * Ajuda a entender o fluxo do script e a diagnosticar problemas.
 *
 * @param string $message A mensagem a ser exibida.
 * @param mixed|null $data Dados adicionais (arrays, objetos) para imprimir.
 * @param string $level O nível do log (INFO, ERROR, WARNING).
 */
function debug_print($message, $data = null, $level = 'INFO')
{
    // A constante DEBUG_ENABLED pode ser definida como `false` em produção para desativar os logs
    if (!defined('DEBUG_ENABLED')) define('DEBUG_ENABLED', true);
    if (!DEBUG_ENABLED) return;

    $timestamp = date('Y-m-d H:i:s');
    echo "[{$level}] [{$timestamp}] $message"; // Imprime a mensagem principal com nível e data

    if ($data !== null) {
        echo "\n";
        if (is_array($data) || is_object($data)) {
            print_r($data); // Formata arrays e objetos de forma legível
        } else {
            echo $data; // Imprime outros tipos de dados diretamente
        }
    }
    echo "\n"; // Adiciona espaço para clareza nos logs
    flush(); // Garante que a saída seja enviada imediatamente
}

// Inicializa contadores globais para o relatório final
$totalReguasProcessadas = 0;
$totalFaturasProcessadas = 0;
$totalEnviosSucesso = 0;
$totalEnviosFalha = 0;

debug_print("Iniciando Execucao do script de envio WPP. ID: " . $execId);
log_message("Iniciando Execucao do script de envio WPP. ID: " . $execId);

// =================================================================
// ==================== INCLUSÃO DE DEPENDÊNCIAS ===================
// =================================================================
try {
    // Inclui os arquivos essenciais para o funcionamento do script
    debug_print("Incluindo arquivos de configuração e API...");
    log_message("Incluindo arquivos de configuração e API...");
    include_once __DIR__ . "/config/conn.php"; // Conexão com o banco de dados (PDO)
    include_once __DIR__ . "/config/config_wpp.php"; // Configurações específicas do WhatsApp
    include_once __DIR__ . "/api/wpp_api.php"; // Funções da API do WhatsApp (ex: sendMessage)
    debug_print("Arquivos incluídos com sucesso.");
    log_message("Arquivos incluídos com sucesso.");
} catch (Exception $e) {
    // Se algum arquivo essencial não for encontrado, o script é interrompido
    debug_print("ERRO FATAL: Não foi possível incluir arquivos essenciais. " . $e->getMessage(), null, 'ERROR');
    log_message("ERRO FATAL: Não foi possível incluir arquivos essenciais. " . $e->getMessage(), 'ERROR');
    die(); // Interrompe a Execucao
}

// =================================================================
// ================= VERIFICAÇÃO DA CONEXÃO COM O BANCO =============
// =================================================================
try {
    // Garante que a variável de conexão `$conn` existe e é um objeto PDO válido
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception("A conexão com o banco de dados não foi estabelecida.");
    }
    $conn->query("SELECT 1"); // Executa uma consulta simples para testar a conexão
    debug_print("Conexão com o banco de dados verificada com sucesso.");
    log_message("Conexão com o banco de dados verificada com sucesso.");
} catch (Exception $e) {
    debug_print("ERRO FATAL: Falha na conexão com o banco de dados. " . $e->getMessage(), null, 'ERROR');
    log_message("ERRO FATAL: Falha na conexão com o banco de dados. " . $e->getMessage(), 'ERROR');
    die();
}

// =================================================================
// ==================== INICIALIZAÇÃO DE TABELAS ===================
// =================================================================
try {
    // Cria as tabelas necessárias para o controle do script, se elas não existirem.
    // Isso torna o script mais robusto e fácil de implantar.
    debug_print("Verificando e criando tabelas do sistema, se necessário...");
    log_message("Verificando e criando tabelas do sistema, se necessário...");
    // Tabela para evitar execuções simultâneas do script
    $conn->exec("CREATE TABLE IF NOT EXISTS SCRIPT_LOCKS (
        lock_name VARCHAR(50) PRIMARY KEY,
        acquired_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL
    )");
    // Tabela para controlar faturas que estão em processo de envio
    $conn->exec("CREATE TABLE IF NOT EXISTS ENVIOS_EM_ANDAMENTO (
        FATURA VARCHAR(255) PRIMARY KEY,
        TIMESTAMP DATETIME NOT NULL,
        EXEC_ID VARCHAR(255) NOT NULL
    )");
    // Tabela para registrar faturas que foram filtradas e não enviadas
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
    // Tabela para gerenciar as sessões (instâncias) do WhatsApp
    $conn->exec("CREATE TABLE IF NOT EXISTS SESSOES_WPP (
        session_id VARCHAR(100) PRIMARY KEY,
        nome VARCHAR(200) NOT NULL,
        tipo_gateway VARCHAR(50) NOT NULL,
        status ENUM('ATIVO', 'INATIVO', 'ERRO') DEFAULT 'ATIVO',
        ultima_utilizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    debug_print("Tabelas do sistema verificadas com sucesso.");
    log_message("Tabelas do sistema verificadas com sucesso.");
} catch (Exception $e) {
    debug_print("ERRO: Falha ao criar/verificar tabelas do sistema. " . $e->getMessage(), null, 'ERROR');
    log_message("ERRO: Falha ao criar/verificar tabelas do sistema. " . $e->getMessage(), 'ERROR');
    die();
}


// =================================================================
// =================== FUNÇÕES DE CONTROLE DE Execucao =============
// =================================================================

/**
 * Garante que apenas uma instância do script seja executada por vez.
 * Cria um "lock" no banco de dados que impede outras execuções.
 *
 * @param PDO $conn Conexão com o banco de dados.
 * @return bool Retorna `true` se o bloqueio for adquirido, `false` caso contrário.
 */
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
        log_message("Verificação de bloqueio: " . json_encode([
            'acquired_at' => $result['acquired_at'],
            'expires_at' => $result['expires_at'],
            'has_lock' => $hasLock ? 'Sim' : 'Não'
        ]));

        return $hasLock;
    } catch (Exception $e) {
        debug_print("Erro ao adquirir bloqueio: " . $e->getMessage(), null, 'ERROR');
        log_message("Erro ao adquirir bloqueio: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Libera o "lock" do script no banco de dados ao final da Execucao.
 *
 * @param PDO $conn Conexão com o banco de dados.
 */
function releaseScriptLock($conn)
{
    try {
        $lockName = 'script_envio_faturas_lock';
        $sql = "DELETE FROM SCRIPT_LOCKS WHERE lock_name = :lock_name";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':lock_name', $lockName);
        $stmt->execute();
        debug_print("Bloqueio de script liberado");
        log_message("Bloqueio de script liberado");
    } catch (Exception $e) {
        debug_print("Erro ao liberar bloqueio: " . $e->getMessage(), null, 'ERROR');
        log_message("Erro ao liberar bloqueio: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Limpa "locks" de faturas muito antigos que podem ter travado por algum erro.
 *
 * @param PDO $conn Conexão com o banco de dados.
 */
function cleanupOldLocks($conn)
{
    try {
        $conn->exec("DELETE FROM ENVIOS_EM_ANDAMENTO WHERE TIMESTAMP < NOW() - INTERVAL 10 MINUTE");
        debug_print("Registros antigos de ENVIOS_EM_ANDAMENTO (>10min) foram limpos");
        log_message("Registros antigos de ENVIOS_EM_ANDAMENTO (>10min) foram limpos");
    } catch (Exception $e) {
        debug_print("Erro ao limpar locks antigos: " . $e->getMessage(), null, 'ERROR');
        log_message("Erro ao limpar locks antigos: " . $e->getMessage(), 'ERROR');
    }
}


// =================================================================
// =================== FUNÇÕES PRINCIPAIS DE ENVIO =================
// =================================================================

/**
 * Verifica se uma fatura já foi enviada hoje ou se está sendo processada.
 * Também cria um bloqueio para a fatura para evitar envio duplicado.
 *
 * @param PDO $conn Conexão com o banco de dados.
 * @param string $cdFatura O código da fatura.
 * @param string $execId O ID da Execucao atual do script.
 * @return array Retorna se o envio pode ser feito e o motivo.
 */
function verificarSeJaFoiEnviado($conn, $cdFatura, $execId)
{
    try {
        $conn->beginTransaction();

        $checkQuery = "SELECT 
            (SELECT COUNT(*) FROM LOG_ENVIOS WHERE FATURA = :fatura AND DATE(DATA_ENVIO) = CURDATE()) +
            (SELECT COUNT(*) FROM LOG_ENVIOS_FALHA WHERE FATURA = :fatura AND DATE(DATA_ENVIO) = CURDATE()) +
            (SELECT COUNT(*) FROM ENVIOS_EM_ANDAMENTO WHERE FATURA = :fatura AND TIMESTAMP > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) 
            AS total_envios FOR UPDATE";

        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':fatura', $cdFatura);
        $checkStmt->execute();
        $totalEnvios = (int)$checkStmt->fetchColumn();

        if ($totalEnvios > 0) {
            $conn->rollBack();
            return ['pode_enviar' => false, 'motivo' => 'Já enviado hoje ou em processamento'];
        }

        $lockQuery = "INSERT INTO ENVIOS_EM_ANDAMENTO (FATURA, TIMESTAMP, EXEC_ID) 
                     VALUES (:fatura, NOW(), :exec_id)";

        $lockStmt = $conn->prepare($lockQuery);
        $lockStmt->bindParam(':fatura', $cdFatura);
        $lockStmt->bindParam(':exec_id', $execId);

        if (!$lockStmt->execute()) {
            $conn->rollBack();
            return ['pode_enviar' => false, 'motivo' => 'Erro ao criar bloqueio'];
        }

        $conn->commit();
        return ['pode_enviar' => true, 'motivo' => 'Bloqueio criado com sucesso'];
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return ['pode_enviar' => false, 'motivo' => 'Erro na verificação: ' . $e->getMessage()];
    }
}

/**
 * Valida a sessão do WhatsApp especificada na régua.
 * MODIFICADO: Esta função agora verifica APENAS a sessão da régua. Não há mais fallback.
 *
 * @param PDO $conn Conexão com o banco de dados.
 * @param string $reguaSessionWpp O ID da sessão definida na régua.
 * @return array Retorna um array com o status da validação e os dados da sessão se for bem-sucedida.
 */
function validarESelecionarSessao($conn, $reguaSessionWpp)
{
    try {
        // Verifica se um ID de sessão foi realmente passado
        if (empty($reguaSessionWpp)) {
            return ['sucesso' => false, 'erro' => 'Nenhuma sessão de WhatsApp foi especificada na régua.'];
        }

        // Busca a sessão no banco de dados para verificar seu nome e status
        $checkSessionQuery = "SELECT status, nome FROM SESSOES_WPP WHERE session_id = :session_id";
        $checkStmt = $conn->prepare($checkSessionQuery);
        $checkStmt->bindParam(':session_id', $reguaSessionWpp);
        $checkStmt->execute();
        $sessionData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Se a sessão foi encontrada e seu status é 'ATIVO'
        if ($sessionData && $sessionData['status'] === 'ATIVO') {
            return [
                'sucesso' => true,
                'sessao' => $reguaSessionWpp,
                'nome' => $sessionData['nome']
            ];
        }

        // Se a sessão não foi encontrada, retorna um erro específico
        if (!$sessionData) {
            return ['sucesso' => false, 'erro' => "A sessão '{$reguaSessionWpp}' especificada na régua não foi encontrada no sistema."];
        } else {
            // Se a sessão foi encontrada mas não está ativa, informa o status atual
            return ['sucesso' => false, 'erro' => "A sessão '{$sessionData['nome']}' ({$reguaSessionWpp}) não está ativa. Status atual: {$sessionData['status']}."];
        }
    } catch (Exception $e) {
        // Captura qualquer erro de banco de dados durante a verificação
        return ['sucesso' => false, 'erro' => 'Erro de banco de dados ao validar a sessão: ' . $e->getMessage()];
    }
}


/**
 * Remove o bloqueio de uma fatura da tabela ENVIOS_EM_ANDAMENTO.
 *
 * @param PDO $conn Conexão com o banco de dados.
 * @param string $cdFatura O código da fatura.
 */
function removerBloqueio($conn, $cdFatura)
{
    try {
        $unlockQuery = "DELETE FROM ENVIOS_EM_ANDAMENTO WHERE FATURA = :fatura";
        $unlockStmt = $conn->prepare($unlockQuery);
        $unlockStmt->bindParam(':fatura', $cdFatura);
        $unlockStmt->execute();
        debug_print("Bloqueio removido para fatura: {$cdFatura}");
        log_message("Bloqueio removido para fatura: {$cdFatura}");
    } catch (Exception $e) {
        debug_print("Erro ao remover bloqueio: " . $e->getMessage(), null, 'ERROR');
        log_message("Erro ao remover bloqueio: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Monta a mensagem final substituindo as variáveis (placeholders) pelos dados da fatura.
 * ESTA FUNÇÃO FOI MODIFICADA PARA DECODIFICAR O JSON.
 *
 * @param PDO $conn Conexão com o banco de dados.
 * @param string $modeloMensagem O nome do modelo da mensagem.
 * @param array $fatura Os dados da fatura.
 * @return array|null Retorna um array com a estrutura da mensagem decodificada ou null em caso de erro.
 */
function montarMensagem($conn, $modeloMensagem, $fatura)
{
    // 1. Busca o modelo no banco de dados
    $buscar_modelo_msg = "SELECT conteudo FROM DBA_MODELOS_MSG WHERE nome = :modeloMensagem";
    $stmt = $conn->prepare($buscar_modelo_msg);
    $stmt->bindParam(':modeloMensagem', $modeloMensagem);
    $stmt->execute();
    $modeloRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$modeloRow || empty($modeloRow['conteudo'])) {
        throw new Exception("Modelo de mensagem '{$modeloMensagem}' não encontrado ou está vazio.");
    }

    // 2. Decodifica a string JSON para um array associativo
    $mensagemEstruturada = json_decode($modeloRow['conteudo'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar o JSON do modelo '{$modeloMensagem}': " . json_last_error_msg());
    }

    // 3. Substitui as variáveis APENAS no corpo da mensagem
    if (isset($mensagemEstruturada['body'])) {
        $nomeCliente = $fatura['NOME'];
        $cdFatura = $fatura['CD_FATURA'];
        $valor = number_format((float)$fatura['VALOR'], 2, ',', '.');
        $vencimento = date('d/m/Y', strtotime($fatura['DATA_VENCIMENTO']));

        $mensagemEstruturada['body'] = str_replace(
            ['{{1}}', '{{2}}', '{{3}}', '{{4}}'],
            [$nomeCliente, $cdFatura, $valor, $vencimento],
            $mensagemEstruturada['body']
        );
    }

    // 4. Retorna a estrutura completa da mensagem
    return $mensagemEstruturada;
}

/**
 * Processa o resultado do envio, registrando sucesso ou falha nos logs.
 * Se o envio for bem-sucedido, remove a fatura da lista de envios pendentes.
 *
 * @param PDO $conn Conexão com o banco de dados.
 * @param array $result O resultado retornado pela API de envio.
 * @param array $fatura Os dados da fatura.
 * @param string $telefone O número de telefone do destinatário.
 * @param string $mensagem O conteúdo da mensagem enviada.
 * @param string $execId O ID da Execucao atual.
 * @param string $sessaoFinal A sessão utilizada para o envio.
 * @param string $modeloMensagem O nome do modelo de mensagem.
 * @return array Retorna um status de sucesso ou falha.
 */
function processarResultadoEnvio($conn, $result, $fatura, $telefone, $mensagem, $execId, $sessaoFinal, $modeloMensagem)
{
    $cdFatura = $fatura['CD_FATURA'];
    $idFatura = $fatura['ID'];
    $nomeCliente = $fatura['NOME'];
    $vencFormat = date('Y-m-d', strtotime($fatura['DATA_VENCIMENTO']));

    $payloadJson = json_encode([
        'session' => $sessaoFinal,
        'phone' => $telefone,
        'message' => $mensagem
    ], JSON_UNESCAPED_UNICODE);

    try {
        if (isset($result['success']) && $result['success'] === true) {
            $logQuery = "INSERT INTO LOG_ENVIOS (FATURA, CONTATO, CELULAR, NOME, VALOR, DATA_VENCIMENTO, DATA_ENVIO, MODELO_MENSAGEM, EXEC_ID, PAYLOAD_JSON) 
                         VALUES (:fatura, :contato, :celular, :nome, :valor, :data_vencimento, NOW(), :modelo_mensagem, :exec_id, :payload_json)";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->execute([
                ':fatura' => $cdFatura,
                ':contato' => $nomeCliente,
                ':celular' => $telefone,
                ':nome' => $nomeCliente,
                ':valor' => $fatura['VALOR'],
                ':data_vencimento' => $vencFormat,
                ':modelo_mensagem' => $modeloMensagem,
                ':exec_id' => $execId,
                ':payload_json' => $payloadJson
            ]);

            $deleteFaturasAVencer = "DELETE FROM FATURAS_A_VENCER WHERE ID = :id_fatura";
            $stmtDelFatura = $conn->prepare($deleteFaturasAVencer);
            $stmtDelFatura->bindParam(':id_fatura', $idFatura);
            $stmtDelFatura->execute();

            return ['sucesso' => true];
        } else {
            $erroMsg = $result['error'] ?? json_encode($result['response'] ?? $result);

            $logFalhaQuery = "INSERT INTO LOG_ENVIOS_FALHA (FATURA, CONTATO, CELULAR, NOME, VALOR, DATA_VENCIMENTO, DATA_ENVIO, ERRO, MODELO_MENSAGEM, EXEC_ID, PAYLOAD_JSON) 
                            VALUES (:fatura, :contato, :celular, :nome, :valor, :data_vencimento, NOW(), :erro, :modelo_mensagem, :exec_id, :payload_json)";
            $logFalhaStmt = $conn->prepare($logFalhaQuery);
            $logFalhaStmt->execute([
                ':fatura' => $cdFatura,
                ':contato' => $nomeCliente,
                ':celular' => $telefone,
                ':nome' => $nomeCliente,
                ':valor' => $fatura['VALOR'],
                ':data_vencimento' => $vencFormat,
                ':erro' => $erroMsg,
                ':modelo_mensagem' => $modeloMensagem,
                ':exec_id' => $execId,
                ':payload_json' => $payloadJson
            ]);

            $erroContactQuery = "INSERT INTO ERRO_CONTACT (FATURA, CONTATO, DATA_ERRO, MOTIVO, STATUS) 
                               VALUES (:fatura, :contato, NOW(), :motivo, 'NAO')
                               ON DUPLICATE KEY UPDATE DATA_ERRO = NOW(), MOTIVO = :motivo";
            $erroContactStmt = $conn->prepare($erroContactQuery);
            $erroContactStmt->execute([':fatura' => $cdFatura, ':contato' => $telefone, ':motivo' => $erroMsg]);

            return ['sucesso' => false, 'erro' => $erroMsg];
        }
    } catch (Exception $e) {
        debug_print("Erro ao processar resultado do envio: " . $e->getMessage(), null, 'ERROR');
        log_message("Erro ao processar resultado do envio: " . $e->getMessage(), 'ERROR');
        return ['sucesso' => false, 'erro' => 'Erro no banco de dados ao processar resultado: ' . $e->getMessage()];
    }
}

/**
 * Orquestra o processo de envio de uma única mensagem, controlando duplicatas e validando a sessão.
 *
 * @param PDO $conn Conexão com o banco de dados.
 * @param array $fatura Os dados da fatura.
 * @param string $telefone O número de telefone.
 * @param string $mensagem A mensagem a ser enviada.
 * @param string $execId O ID da Execucao.
 * @param string $sessionWpp A sessão de WhatsApp especificada na régua.
 * @param string $modeloMensagem O nome do modelo de mensagem.
 * @return array Retorna o resultado final do envio.
 */
function enviarMensagemComControleDeDuplicatas($conn, $fatura, $telefone, $mensagem, $execId, $sessionWpp, $modeloMensagem)
{
    $cdFatura = $fatura['CD_FATURA'];

    try {
        // 1. Verifica se a fatura já foi enviada ou está em processamento
        $verificacao = verificarSeJaFoiEnviado($conn, $cdFatura, $execId);
        if (!$verificacao['pode_enviar']) {
            return ['sucesso' => false, 'erro' => $verificacao['motivo'], 'tipo' => 'DUPLICATA'];
        }

        // 2. Valida a sessão especificada na régua (SEM FALLBACK)
        $sessaoInfo = validarESelecionarSessao($conn, $sessionWpp);
        if (!$sessaoInfo['sucesso']) {
            removerBloqueio($conn, $cdFatura); // Libera o bloqueio da fatura
            return ['sucesso' => false, 'erro' => $sessaoInfo['erro'], 'tipo' => 'SESSAO_INATIVA'];
        }

        $sessaoFinal = $sessaoInfo['sessao'];
        debug_print("Usando sessão '{$sessaoInfo['nome']}' ({$sessaoFinal}) definida na régua.");
        log_message("Usando sessão '{$sessaoInfo['nome']}' ({$sessaoFinal}) definida na régua.");

        // 3. Envia a mensagem através da API
        $result = sendMessage($sessaoFinal, $telefone, $mensagem);

        // 4. Processa o resultado (registra logs, remove da fila)
        $resultadoFinal = processarResultadoEnvio($conn, $result, $fatura, $telefone, $mensagem, $execId, $sessaoFinal, $modeloMensagem);

        // 5. Remove o bloqueio da fatura, independentemente do resultado
        removerBloqueio($conn, $cdFatura);

        return $resultadoFinal;
    } catch (Exception $e) {
        // Em caso de erro inesperado, garante que o bloqueio seja removido
        removerBloqueio($conn, $cdFatura);
        return ['sucesso' => false, 'erro' => 'Erro interno no processo de envio: ' . $e->getMessage(), 'tipo' => 'ERRO_INTERNO'];
    }
}


// =================================================================
// ==================== VERIFICAÇÕES INICIAIS DO SCRIPT =============
// =================================================================
try {
    // Garante que o script não seja executado se já houver uma instância rodando
    if (!acquireScriptLock($conn)) {
        debug_print("Execucao abortada: outra instância do script já está em Execucao.", null, 'WARNING');
        log_message("Execucao abortada: outra instância do script já está em Execucao.", 'WARNING');
        exit;
    }

    // Limpa bloqueios de envios que possam ter travado em execuções anteriores
    cleanupOldLocks($conn);

    // Verifica se é um dia útil (não executa em fins de semana)
    $diaSemana = date('w'); // 0 = Domingo, 6 = Sábado
    if ($diaSemana == 0 || $diaSemana == 6) {
        debug_print("Execucao abortada: envio desabilitado nos finais de semana.");
        log_message("Execucao abortada: envio desabilitado nos finais de semana.");
        releaseScriptLock($conn);
        exit;
    }

    // Verifica se hoje é um feriado (lista de feriados fixos)
    $feriados = [
        '01/01/2025',
        '03/03/2025',
        '04/03/2025',
        '18/04/2025',
        '21/04/2025',
        '01/05/2025',
        '19/06/2025',
        '07/09/2025',
        '12/10/2025',
        '02/11/2025',
        '15/11/2025',
        '20/11/2025',
        '24/12/2025',
        '25/12/2025',
        '31/12/2025',
    ];
    $dataHoje = date('d/m/Y');
    if (in_array($dataHoje, $feriados)) {
        debug_print("Execucao abortada: envio desabilitado em feriados.");
        log_message("Execucao abortada: envio desabilitado em feriados.");
        releaseScriptLock($conn);
        exit;
    }

    debug_print("Verificações iniciais concluídas. O script pode continuar.");
    log_message("Verificações iniciais concluídas. O script pode continuar.");
} catch (Exception $e) {
    debug_print("ERRO FATAL durante as verificações iniciais: " . $e->getMessage(), null, 'ERROR');
    log_message("ERRO FATAL durante as verificações iniciais: " . $e->getMessage(), 'ERROR');
    releaseScriptLock($conn);
    die();
}

// =================================================================
// ==================== PROCESSAMENTO PRINCIPAL DAS RÉGUAS =========
// =================================================================
try {
    debug_print("Iniciando busca por réguas de cobrança ativas...");
    log_message("Iniciando busca por réguas de cobrança ativas...");

    // Busca todas as réguas que estão marcadas como "Ativo" no banco de dados
    $sqlReguas = "SELECT id, nome, intervalo, hora, modelo_mensagem, TIPO_DE_MODELO, Session_WPP 
                FROM REGUAS_CRIADAS WHERE status = 'Ativo'";
    $stmtReguas = $conn->query($sqlReguas);
    $reguasAtivas = $stmtReguas->fetchAll(PDO::FETCH_ASSOC);

    if (!$reguasAtivas) {
        debug_print("Nenhuma régua ativa encontrada. Encerrando.");
        log_message("Nenhuma régua ativa encontrada. Encerrando.");
        releaseScriptLock($conn);
        exit;
    }
    debug_print(count($reguasAtivas) . " régua(s) ativa(s) encontrada(s).");
    log_message(count($reguasAtivas) . " régua(s) ativa(s) encontrada(s).");

    $currentTime = date('H:i');
    $reguasParaProcessar = 0;

    // Itera sobre cada régua ativa encontrada
    foreach ($reguasAtivas as $regua) {
        try {
            debug_print("Analisando régua: '{$regua['nome']}'", $regua);
            log_message("Analisando régua: '{$regua['nome']}'", json_encode($regua));

            // Extrai os dados da régua
            $intervalo = (int)$regua['intervalo'];
            $horaRegua = substr($regua['hora'], 0, 5);
            $modeloMensagem = $regua['modelo_mensagem'];
            $GatewayEnvio = $regua['TIPO_DE_MODELO'];
            $Session_WPP = $regua['Session_WPP'];

            // Pula a régua se dados essenciais estiverem faltando
            if (empty($modeloMensagem) || empty($Session_WPP)) {
                debug_print("Régua ignorada: modelo de mensagem ou sessão WPP não definidos.", null, 'WARNING');
                log_message("Régua ignorada: modelo de mensagem ou sessão WPP não definidos.", 'WARNING');
                continue;
            }

            // Verifica se a régua é do tipo correto (WPP-Connect)
            if ($GatewayEnvio !== 'Wpp-Connect' && $GatewayEnvio !== 'Wpp - Connect') {
                debug_print("Régua ignorada: não é do tipo 'Wpp-Connect'.");
                log_message("Régua ignorada: não é do tipo 'Wpp-Connect'.");
                continue;
            }

            // Define a janela de tempo para Execucao (hora da régua + 6 horas)
            $inicioPermitido = DateTime::createFromFormat('H:i', $horaRegua);
            $fimPermitido = (clone $inicioPermitido)->add(new DateInterval('PT6H'));
            $currentTimeObj = DateTime::createFromFormat('H:i', $currentTime);

            // Verifica se o horário atual está dentro da janela permitida
            if ($currentTimeObj < $inicioPermitido || $currentTimeObj > $fimPermitido) {
                debug_print("Régua ignorada: fora da janela de horário ({$inicioPermitido->format('H:i')} - {$fimPermitido->format('H:i')}).");
                log_message("Régua ignorada: fora da janela de horário ({$inicioPermitido->format('H:i')} - {$fimPermitido->format('H:i')}).");
                continue;
            }
            // Este script lida apenas com faturas já vencidas (intervalo < 0)
            if ($intervalo >= 0) {
                debug_print("Régua ignorada: o intervalo ({$intervalo}) indica fatura NÃO vencida.");
                log_message("Régua ignorada: o intervalo ({$intervalo}) indica fatura NÃO vencida.");
                continue;
            }

            $reguasParaProcessar++;
            $totalReguasProcessadas++;
            debug_print("Régua '{$regua['nome']}' selecionada para processamento!");
            log_message("Régua '{$regua['nome']}' selecionada para processamento!");

            // Busca as faturas que correspondem ao critério de intervalo de dias da régua
            $sqlFaturas = "SELECT ID, NOME, CELULAR, CD_FATURA, VALOR, DATA_VENCIMENTO
                           FROM FATURAS_A_VENCER
                           WHERE ENVIADO = 'NAO' AND DATEDIFF(CURDATE(), DATA_VENCIMENTO) = :intervalo";
            $stmtFaturas = $conn->prepare($sqlFaturas);
            $stmtFaturas->bindParam(':intervalo', $intervalo, PDO::PARAM_INT);
            $stmtFaturas->execute();
            $faturas = $stmtFaturas->fetchAll(PDO::FETCH_ASSOC);

            if (!$faturas) {
                debug_print("Nenhuma fatura encontrada para o intervalo de {$intervalo} dia(s).");
                log_message("Nenhuma fatura encontrada para o intervalo de {$intervalo} dia(s).");
                continue;
            }
            debug_print(count($faturas) . " fatura(s) encontrada(s) para esta régua.");
            log_message(count($faturas) . " fatura(s) encontrada(s) para esta régua.");

            $contadorEnviosRégua = 0;

            // Itera sobre cada fatura encontrada
            foreach ($faturas as $fatura) {
                try {
                    $totalFaturasProcessadas++;
                    $cdFatura = $fatura['CD_FATURA'];

                    // Limpa e formata o número de telefone
                    $telefone = preg_replace('/[^0-9]/', '', $fatura['CELULAR']);
                    if (substr($telefone, 0, 2) !== '55') {
                        $telefone = '55' . $telefone;
                    }

                    // Monta a mensagem (agora retorna um array decodificado)
                    $mensagemEstruturada = montarMensagem($conn, $modeloMensagem, $fatura);

                    // Envia a mensagem usando a função orquestradora
                    $resultadoEnvio = enviarMensagemComControleDeDuplicatas(
                        $conn,
                        $fatura,
                        $telefone,
                        $mensagemEstruturada, // Passa o array completo
                        $execId,
                        $Session_WPP,
                        $modeloMensagem
                    );

                    if ($resultadoEnvio['sucesso']) {
                        $totalEnviosSucesso++;
                        debug_print("SUCESSO no envio para a fatura {$cdFatura}");
                        log_message("SUCESSO no envio para a fatura {$cdFatura}");
                    } else {
                        $totalEnviosFalha++;
                        debug_print("FALHA no envio para a fatura {$cdFatura}. Motivo: " . $resultadoEnvio['erro'], null, 'ERROR');
                        log_message("FALHA no envio para a fatura {$cdFatura}. Motivo: " . $resultadoEnvio['erro'], 'ERROR');
                    }

                    $contadorEnviosRégua++;
                    if ($contadorEnviosRégua >= 50) { // Limite de envios por régua/Execucao
                        debug_print("Limite de 50 envios para a régua '{$regua['nome']}' atingido.", null, 'WARNING');
                        log_message("Limite de 50 envios para a régua '{$regua['nome']}' atingido.", 'WARNING');
                        break;
                    }

                    sleep(rand(2, 5)); // Pausa para evitar bloqueios

                } catch (Exception $e) {
                    debug_print("Erro crítico ao processar a fatura {$cdFatura}: " . $e->getMessage(), null, 'ERROR');
                    log_message("Erro crítico ao processar a fatura {$cdFatura}: " . $e->getMessage(), 'ERROR');
                    continue;
                }
            }
        } catch (Exception $e) {
            debug_print("Erro crítico ao processar a régua '{$regua['nome']}': " . $e->getMessage(), null, 'ERROR');
            log_message("Erro crítico ao processar a régua '{$regua['nome']}': " . $e->getMessage(), 'ERROR');
            continue;
        }
    }

    // =================================================================
    // ==================== RELATÓRIO FINAL DA Execucao ===============
    // =================================================================
    $tempoFinal = microtime(true);
    $tempoExecucao = round($tempoFinal - $tempoInicial, 2);

    debug_print("================ Execucao FINALIZADA ================", [
        'ID da Execucao' => $execId,
        'Tempo de Execucao' => $tempoExecucao . ' segundos',
        'Reguas Processadas' => $totalReguasProcessadas,
        'Faturas Processadas' => $totalFaturasProcessadas,
        'Envios com Sucesso' => $totalEnviosSucesso,
        'Envios com Falha' => $totalEnviosFalha
    ]);
    log_message("================ Execucao FINALIZADA ================", json_encode([
        'ID da Execucao' => $execId,
        'Tempo de Execucao' => $tempoExecucao . ' segundos',
        'Reguas Processadas' => $totalReguasProcessadas,
        'Faturas Processadas' => $totalFaturasProcessadas,
        'Envios com Sucesso' => $totalEnviosSucesso,
        'Envios com Falha' => $totalEnviosFalha
    ]));

    if ($reguasParaProcessar == 0) {
        debug_print("Nenhuma régua ativa estava dentro do horário de Execucao permitido.");
        log_message("Nenhuma régua ativa estava dentro do horário de Execucao permitido.");
    }
} catch (Exception $e) {
    debug_print("ERRO FATAL durante o processamento das réguas: " . $e->getMessage(), null, 'ERROR');
    log_message("ERRO FATAL durante o processamento das réguas: " . $e->getMessage(), 'ERROR');
} finally {
    // =================================================================
    // ========================== LIMPEZA FINAL ========================
    // =================================================================
    // Libera o bloqueio do script para que possa ser executado novamente no futuro
    releaseScriptLock($conn);
    debug_print("Bloqueio do script liberado. Script finalizado.");
    log_message("Bloqueio do script liberado. Script finalizado.");
}
