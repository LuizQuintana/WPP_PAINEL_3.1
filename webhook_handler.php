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

try {
    if (isset($data['event']) && $data['event'] === 'message') {
        $msg = $data['data'] ?? [];

        // Dados principais
        $messageId      = $msg['id'] ?? null;
        $chatId         = $msg['chatId'] ?? null;
        $from           = $msg['from'] ?? null;
        $to             = $msg['to'] ?? null;
        $type           = $msg['type'] ?? null;
        $body           = $msg['body'] ?? null;
        $timestamp      = isset($msg['timestamp']) ? date('Y-m-d H:i:s', $msg['timestamp']) : date('Y-m-d H:i:s');
        $ack            = $msg['ack'] ?? null;
        $session        = $data['session'] ?? null;
        $sender_name    = $msg['sender']['name'] ?? ($msg['sender']['pushname'] ?? '');

        // Mídia (caso haja)
        $mediaUrl       = $msg['url'] ?? null;
        $mimetype       = $msg['mimetype'] ?? null;

        // Insere no banco
        $stmt = $pdo->prepare("
            INSERT INTO WEBHOOKS_LOGS 
            (message_id, chat_id, from_number, to_number, message_type, message_body, media_url, media_mimetype, timestamp, status_ack, session_id, sender_name, raw_payload)
            VALUES 
            (:message_id, :chat_id, :from_number, :to_number, :message_type, :message_body, :media_url, :media_mimetype, :timestamp, :status_ack, :session_id, :sender_name, :raw_payload)
        ");

        $stmt->execute([
            ':message_id'      => $messageId,
            ':chat_id'         => $chatId,
            ':from_number'     => $from,
            ':to_number'       => $to,
            ':message_type'    => $type,
            ':message_body'    => $body,
            ':media_url'       => $mediaUrl,
            ':media_mimetype'  => $mimetype,
            ':timestamp'       => $timestamp,
            ':status_ack'      => $ack,
            ':session_id'      => $session,
            ':sender_name'     => $sender_name,
            ':raw_payload'     => $json
        ]);
    }

    // Lógica adicional para salvar em respostas_clientes (se necessário)
    if (isset($data['event']) && $data['event'] === 'message' && isset($data['data']['body'])) {
        $remetente = $data['data']['from'] ?? 'desconhecido';
        $mensagem = $data['data']['body'];
        $data_recebimento = date('Y-m-d H:i:s');

        $categoria = (stripos($mensagem, 'pix') !== false) ? 'PIX' : 'Geral';
        $id_conversa = $remetente;
        $status_conversa = 'em_aberto';

        $stmt = $pdo->prepare("INSERT INTO respostas_clientes 
            (remetente, mensagem, categoria, data_recebimento, id_conversa, status_conversa) 
            VALUES 
            (:remetente, :mensagem, :categoria, :data_recebimento, :id_conversa, :status_conversa)");

        $stmt->execute([
            ':remetente' => $remetente,
            ':mensagem' => $mensagem,
            ':categoria' => $categoria,
            ':data_recebimento' => $data_recebimento,
            ':id_conversa' => $id_conversa,
            ':status_conversa' => $status_conversa
        ]);

        $messageId = $pdo->lastInsertId();

        $workflowEngine = new WorkflowEngine($pdo);
        $workflowEngine->processMessage([
            'id' => $messageId,
            'from' => $remetente,
            'body' => $mensagem,
            'categoria' => $categoria
        ]);
    }
} catch (PDOException $e) {
    file_put_contents($logFile, "Erro no banco: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Confirma recebimento do webhook
http_response_code(200);
echo "Webhook processado com sucesso.";
