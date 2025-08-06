<?php
// Caminhos para os arquivos de log
$webhookLogFile = __DIR__ . '/storage/webhooks.log';
$pixLogFile = __DIR__ . '/storage/pix_payments.log';

// Função para ler conteúdo do log de forma segura
function readLog($filePath)
{
    if (file_exists($filePath)) {
        return htmlspecialchars(file_get_contents($filePath));
    } else {
        return "Arquivo de log não encontrado: " . htmlspecialchars($filePath);
    }
}

$webhookLogs = readLog($webhookLogFile);
$pixLogs = readLog($pixLogFile);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de Logs de Webhook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #2a2a3d;
            color: #f0f0f0;
            padding: 20px;
        }

        .containerne2 {
        background-color: #1e1e2f;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    width: 80%;
    float: right;
    display: flex
;
    flex-direction: column;
        }

        .log-boxne2 {
            background-color: #1a1a2a;
            color: #a6e3a1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: monospace;
            max-height: 500px;
        }

        .titlene2 {
            color: #3498db;
            margin-bottom: 15px;
        }

        .btn-refreshne2 {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .btn-refreshne2:hover {
            background-color: #218838;
        }
.sidebar-toggle-btn{
display: none;
}
    </style>
</head>

<body>
    <div class="containerne2">
        <?php include 'header.php'; ?>
        <h1 class="text-center titlene2">Visualizador de Logs de Webhook</h1>
        <button class="btn-refreshne2" onclick="location.reload();">🔄 Recarregar Logs</button>

        <div class="mb-4">
            <h2 class="titlene2">Logs de Webhooks Gerais (webhooks.log)</h2>
            <div class="log-boxne2"><?= $webhookLogs ?></div>
        </div>

        <div class="mb-4">
            <h2 class="titlene2">Logs de Pagamentos PIX (pix_payments.log)</h2>
            <div class="log-boxne2"><?= $pixLogs ?></div>
        </div>
    </div>
</body>
</html>
