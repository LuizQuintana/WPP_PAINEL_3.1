<?php
// Forçar a saída imediata para o navegador
if (ob_get_level() == 0) ob_start();

function log_message($message, $type = 'INFO') {
    $color = '#fff'; // Branco para padrão
    if ($type === 'SUCCESS') $color = '#28a745'; // Verde
    if ($type === 'ERROR') $color = '#dc3545'; // Vermelho
    if ($type === 'WARN') $color = '#ffc107'; // Amarelo

    echo "<p style='color: {$color}; margin: 2px 0; font-family: monospace;'>[{$type}] " . htmlspecialchars($message) . "</p>";
    ob_flush();
    flush();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronizando Sessões...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #212529;
            color: #e9ecef;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #343a40;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0 15px rgba(0,0,0,0.5);
            max-width: 800px;
            width: 100%;
        }
        .log-box {
            background-color: #212529;
            border: 1px solid #495057;
            border-radius: 0.25rem;
            padding: 1rem;
            height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.875em;
            margin-top: 1rem;
        }
        .spinner-border {
            width: 1rem;
            height: 1rem;
            border-width: 0.2em;
        }
        .btn-close-custom {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <button type="button" class="btn-close btn-close-white btn-close-custom" aria-label="Close" onclick="window.close();"></button>
        <h4 class="mb-3">
            <span class="spinner-border text-primary me-2" role="status" id="spinner"></span>
            Sincronizando Sessões do WPP-Connect
        </h4>
        <div class="log-box" id="log-output">
            <?php
            log_message("Iniciando script...");

            try {
                include_once __DIR__ . "/config/conn.php";
                include_once __DIR__ . "/config/config_wpp.php";
                log_message("Arquivos de configuração carregados.", "SUCCESS");
            } catch (Exception $e) {
                log_message("ERRO FATAL: Não foi possível incluir arquivos essenciais. " . $e->getMessage(), "ERROR");
                die();
            }

            // Funções de apoio
            function load_tokens() { /* ... (código original) ... */ return []; }
            function save_tokens($tokens) { /* ... (código original) ... */ }
            function generate_token($sessionName, $secretKey, $baseUrl) {
                $url = "{$baseUrl}/api/{$sessionName}/{$secretKey}/generate-token";
                log_message("Gerando novo token para '{$sessionName}'...");
                $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15]);
                $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
                if ($httpCode == 200 || $httpCode == 201) {
                    $data = json_decode($response, true);
                    if (isset($data['token'])) {
                        log_message("Token gerado com sucesso para '{$sessionName}'.", "SUCCESS");
                        $tokens = load_tokens(); $tokens[$sessionName] = $data['token']; save_tokens($tokens);
                        return $data['token'];
                    }
                }
                log_message("ERRO ao gerar token para '{$sessionName}'.", "ERROR");
                return null;
            }
            function get_session_status($sessionName, $secretKey, $baseUrl) {
                $tokens = load_tokens(); $token = $tokens[$sessionName] ?? null;
                if (!$token) { $token = generate_token($sessionName, $secretKey, $baseUrl); if (!$token) return null; }
                $url = "{$baseUrl}/api/{$sessionName}/status-session";
                $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]]);
                $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
                if ($httpCode == 200) return (json_decode($response, true))['status'] ?? 'UNKNOWN';
                if ($httpCode == 401) {
                    log_message("Token para '{$sessionName}' expirou. Gerando um novo...", "WARN");
                    $token = generate_token($sessionName, $secretKey, $baseUrl);
                    if ($token) return get_session_status($sessionName, $secretKey, $baseUrl);
                }
                log_message("ERRO ao buscar status da sessão '{$sessionName}'.", "ERROR");
                return 'ERROR';
            }
            function get_all_session_names($secretKey, $baseUrl) {
                $url = "{$baseUrl}/api/{$secretKey}/show-all-sessions";
                log_message("Buscando nomes de sessão da API...");
                $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
                $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
                if ($httpCode == 200) {
                    $data = json_decode($response, true);
                    if (isset($data['response']) && is_array($data['response'])) {
                        log_message("Sessões encontradas: " . implode(', ', $data['response']), "SUCCESS");
                        return $data['response'];
                    }
                }
                log_message("ERRO FATAL ao buscar nomes de sessão. Código: {$httpCode}", "ERROR");
                return null;
            }

            if (!defined('baseUrl') || !defined('secretKey')) {
                log_message("ERRO: 'baseUrl' ou 'secretKey' não definidas.", "ERROR");
                die();
            }

            $sessionNames = get_all_session_names(secretKey, baseUrl);

            if ($sessionNames !== null) {
                $sessoesParaAtualizar = [];
                foreach ($sessionNames as $name) {
                    log_message("Verificando status da sessão: {$name}");
                    $status = get_session_status($name, secretKey, baseUrl);
                    if ($status) {
                        $sessoesParaAtualizar[$name] = $status;
                        log_message("Status de '{$name}': {$status}", "SUCCESS");
                    }
                }

                try {
                    $conn->beginTransaction();
                    $stmt = $conn->prepare("INSERT INTO SESSOES_WPP (session_id, nome, tipo_gateway, status, updated_at) VALUES (:session_id, :nome, :tipo_gateway, :status, NOW()) ON DUPLICATE KEY UPDATE nome = VALUES(nome), status = VALUES(status), updated_at = NOW()");
                    $totalProcessadas = 0;
                    foreach ($sessoesParaAtualizar as $sessionId => $apiStatus) {
                        $dbStatus = in_array($apiStatus, ['CONNECTED', 'SYNCING', 'CONNECTING']) ? 'ATIVO' : 'INATIVO';
                        $stmt->execute([':session_id' => $sessionId, ':nome' => $sessionId, ':tipo_gateway' => 'Wpp-Connect', ':status' => $dbStatus]);
                        $totalProcessadas++;
                    }
                    if (!empty($sessionNames)) {
                        $placeholders = implode(',', array_fill(0, count($sessionNames), '?'));
                        $stmtInactive = $conn->prepare("UPDATE SESSOES_WPP SET status = 'INATIVO', updated_at = NOW() WHERE session_id NOT IN ($placeholders)");
                        $stmtInactive->execute($sessionNames);
                        log_message("Sessões antigas marcadas como INATIVO.", "WARN");
                    }
                    $conn->commit();
                    log_message("Banco de dados atualizado com sucesso!", "SUCCESS");
                    echo "<hr><p class='text-light'><b>Resumo:</b> {$totalProcessadas} sessões foram sincronizadas.</p>";

                } catch (Exception $e) {
                    if ($conn->inTransaction()) $conn->rollBack();
                    log_message("ERRO CRÍTICO no banco de dados: " . $e->getMessage(), "ERROR");
                }
            }
            ?>
        </div>
        <div id="summary" class="mt-3 text-center text-light">
            <p>Sincronização concluída. Você pode fechar esta janela.</p>
        </div>
    </div>
    <script>
        document.getElementById('spinner').style.display = 'none';
    </script>
</body>
</html>
<?php ob_end_flush(); ?>