<?php
// Caminhos para os logs
$logFile = __DIR__ . '/storage/webhooks.log';
$pixLogFile = __DIR__ . '/storage/pix_payments.log';

// Lê o conteúdo do corpo da requisição (JSON)
$json = file_get_contents('php://input');

// Registra o JSON bruto com timestamp no log principal
$logMessage = date('Y-m-d H:i:s') . " - Webhook Recebido: " . $json . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Decodifica o JSON para array associativo
$data = json_decode($json, true);

// Finaliza se o JSON for inválido
if (!$data) {
    http_response_code(400);
    exit("JSON inválido");
}

// Inclui conexão com o banco e engine de fluxo
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/workflow_engine.php';

// Verifica se é uma mensagem recebida
if (isset($data['event']) && $data['event'] === 'message' && isset($data['data']['body'])) {
    $remetente = $data['data']['from'] ?? 'desconhecido';
    $mensagem = $data['data']['body'];
    $data_recebimento = date('Y-m-d H:i:s');

    // Define categoria com base no conteúdo
    $categoria = (stripos($mensagem, 'pix') !== false) ? 'PIX' : 'Geral';

    // Define ID de conversa (pode ser o número do contato)
    $id_conversa = $remetente;
    $status_conversa = 'em_aberto';

    try {
        // Prepara o INSERT com os novos campos
        $sql = "INSERT INTO respostas_clientes 
            (remetente, mensagem, categoria, data_recebimento, id_conversa, status_conversa) 
            VALUES 
            (:remetente, :mensagem, :categoria, :data_recebimento, :id_conversa, :status_conversa)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':remetente' => $remetente,
            ':mensagem' => $mensagem,
            ':categoria' => $categoria,
            ':data_recebimento' => $data_recebimento,
            ':id_conversa' => $id_conversa,
            ':status_conversa' => $status_conversa
        ]);

        // Pega o ID da mensagem salva
        $messageId = $pdo->lastInsertId();

        // Encaminha para processamento do fluxo de trabalho
        $workflowEngine = new WorkflowEngine($pdo);
        $workflowEngine->processMessage([
            'id' => $messageId,
            'from' => $remetente,
            'body' => $mensagem,
            'categoria' => $categoria
        ]);
    } catch (PDOException $e) {
        // Em caso de erro, registra no log
        file_put_contents($logFile, "Erro ao salvar no banco: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Retorna 200 OK para o servidor do WhatsApp saber que está tudo certo
http_response_code(200);
echo "Webhook processado com sucesso.";
