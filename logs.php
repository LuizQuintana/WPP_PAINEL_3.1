<?php
session_start();

if (!isset($_SESSION['user_user'])) {
    header("Location: login.php");
    exit;
}

// Scan for log files in the storage directory
// Diretórios onde buscar os logs
$logDirs = [__DIR__ . '/storage/', '/var/logs/'];
$logFiles = [];
$logGroups = [];

foreach ($logDirs as $logDir) {
    if (is_dir($logDir)) {
        if ($handle = opendir($logDir)) {
            while (false !== ($entry = readdir($handle))) {
                if (preg_match('/^(.*)\.log$/', $entry, $matches)) {
                    $baseName = $matches[1];
                    $groupName = $baseName;
                    if (preg_match('/^(.*?)_(\d{4}-\d{2}-\d{2})$/', $baseName, $dateMatches)) {
                        $groupName = $dateMatches[1];
                    }
                    if (!in_array($groupName, $logGroups)) {
                        $logGroups[] = $groupName;
                    }
                    // Salva o nome do arquivo com seu caminho completo
                    $logFiles[$entry] = $logDir . $entry;
                }
            }
            closedir($handle);
        }
    }
}

$selectedGroup = $_GET['log_group'] ?? '';
$logDate = $_GET['log_date'] ?? '';
$content = '';
$fileName = '';

if ($selectedGroup) {
    $fileName = $selectedGroup . '.log';
    if ($logDate) {
        $fileName = $selectedGroup . '_' . $logDate . '.log';
    }

    if (isset($logFiles[$fileName]) && file_exists($logFiles[$fileName])) {
        $content = file_get_contents($logFiles[$fileName]);
    } else {
        $content = "Arquivo de log não encontrado: " . htmlspecialchars($fileName);
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Sistema</title>
    <link rel="stylesheet" href="assets/html.css">
    <style>
        .log-container-mainnew {
            background-color: #1e1e2f;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.3);
            margin-left: 260px;
            width: calc(100% - 260px);
        }

        h1, h2 {
            color: #3498db;
            margin-bottom: 15px;
            border-bottom: 1px solid #444;
            padding-bottom: 5px;
        }

        select, button, input[type="date"] {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #555;
            background-color: #333;
            color: #eee;
            cursor: pointer;
        }

        select:focus, button:focus, input[type="date"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        button[type="submit"] {
            background-color: #28a745;
            border-color: #28a745;
        }

        button[type="submit"]:hover {
            background-color: #218838;
            border-color: #218838;
        }

        pre {
            background-color: #1a1a2a;
            color: #a6e3a1;
            padding: 15px;
            border-radius: 5px;
            overflow: auto;
            height: 500px;
            white-space: pre;
            word-wrap: normal;
            border: 1px solid #333;
        }

        label {
            color: #f0f0f0;
            margin-right: 10px;
        }

        form {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .main-contentnew {
            margin-left: 8% !important;
        }
    </style>
</head>

<body>
    <div id="containernew">
        <div id="titulonew">
            <h4><i class="fab fa-whatsapp"></i> SendNow 2.0</h4>
        </div>
        <?php include_once 'header.php'; ?>
        <div class="main-contentnew">
            <div class="container-fluidnew mt-4">
                <div class="log-container-mainnew">
                    <h1>Visualizador de Logs</h1>
                    <form method="get">
                        <label for="log_group">Selecione o arquivo de log:</label>
                        <select name="log_group" id="log_group" onchange="this.form.submit()">
                            <option value="">-- Escolha um grupo de log --</option>
                            <?php foreach ($logGroups as $group): ?>
                                <option value="<?= htmlspecialchars($group) ?>" <?= $selectedGroup === $group ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($group) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="log_date">Selecione a data (opcional):</label>
                        <input type="date" name="log_date" id="log_date" value="<?= htmlspecialchars($logDate) ?>" onchange="this.form.submit()">

                        <button type="submit">Ver log</button>
                    </form>

                    <?php if ($selectedGroup): ?>
                        <h2>Conteúdo de: <?= htmlspecialchars($fileName) ?></h2>
                        <pre><?= nl2br(htmlspecialchars($content)) ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
