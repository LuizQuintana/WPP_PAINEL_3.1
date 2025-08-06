<?php
$config = include __DIR__ . '/../config/config.php';

function call_api($method, $url, $data = null, $headers = [])
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $json = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = 'Content-Type: application/json';
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'message' => $error];
    }

    curl_close($ch);

    // Debug: log the response
    error_log("API Response: " . $response);
    error_log("HTTP Code: " . $httpCode);

    $decoded = json_decode($response, true);
    if (!$decoded && $response !== '') {
        return ['status' => 'error', 'message' => 'Resposta JSON inválida: ' . $response, 'http_code' => $httpCode];
    }

    return $decoded ?: ['status' => 'error', 'message' => 'Resposta vazia', 'http_code' => $httpCode];
}

function get_all_sessions()
{
    global $config;
    $url = $config['baseUrl'] . "/api/{$config['secretKey']}/show-all-sessions";
    return call_api('GET', $url);
}

function generate_token($sessionName)
{
    global $config;
    // URL-encode the secret key to handle special characters
    $secretKeyEncoded = urlencode($config['secretKey']);
    $url = $config['baseUrl'] . "/api/{$sessionName}/{$secretKeyEncoded}/generate-token";

    // Log da URL sendo chamada
    error_log("[generate_token] URL: " . $url);

    $result = call_api('POST', $url);

    // Log do resultado completo da API
    error_log("[generate_token] Result: " . print_r($result, true));

    if (isset($result['token'])) {
        $tokens = load_tokens();
        $tokens[$sessionName] = $result['token'];
        save_tokens($tokens);
    } else {
        // Adiciona a resposta da API no retorno em caso de erro para debug
        $result['debug_url'] = $url;
        $result['debug_response'] = $result;
    }

    return $result;
}

function start_session($sessionName)
{
    global $config;
    $token = get_token($sessionName);
    if (!$token) return ['status' => 'error', 'message' => 'Token não encontrado. Gere o token primeiro.'];

    $url = $config['baseUrl'] . "/api/{$sessionName}/start-session";
    $data = [
        'webhook' => '',
        'waitQrCode' => true
    ];
    $headers = [
        "Authorization: Bearer $token",
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Mantendo a configuração original

    // Define um timeout muito curto (1 segundo) para não esperar a conclusão
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);

    curl_exec($ch);

    // Verificamos se o erro foi um timeout, o que é esperado aqui
    if (curl_errno($ch) === CURLE_OPERATION_TIMEDOUT) {
        curl_close($ch);
        // Retorna sucesso, pois o comando foi enviado e o servidor está processando em background
        return ['status' => 'success', 'message' => 'Comando de início enviado.'];
    }

    // Se for outro erro, retorne o erro real
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'message' => $error];
    }

    curl_close($ch);
    // Se por acaso responder rápido, retorna sucesso
    return ['status' => 'success', 'message' => 'Comando de início enviado.'];
}

function get_qrcode($sessionName)
{
    global $config;
    $token = get_token($sessionName);
    if (!$token) return ['status' => 'error', 'message' => 'Token não encontrado'];

    $url = $config['baseUrl'] . "/api/{$sessionName}/qrcode-session";

    // Usar função específica para QR Code que pode retornar imagem binária
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'message' => $error];
    }

    curl_close($ch);

    // Se a resposta é uma imagem PNG
    if (strpos($contentType, 'image/png') !== false || substr($response, 0, 8) === "\x89PNG\r\n\x1a\n") {
        $base64 = base64_encode($response);
        return [
            'status' => 'success',
            'qrcode' => 'data:image/png;base64,' . $base64,
            'base64' => $base64
        ];
    }

    // Se a resposta é JSON
    $decoded = json_decode($response, true);
    if ($decoded) {
        return $decoded;
    }

    // Se não conseguiu decodificar, retornar erro
    return ['status' => 'error', 'message' => 'Resposta inválida do servidor', 'http_code' => $httpCode];
}

function logout_session($sessionName)
{
    global $config;
    $token = get_token($sessionName);
    if (!$token) return ['status' => 'error', 'message' => 'Token não encontrado'];

    $url = $config['baseUrl'] . "/api/{$sessionName}/logout-session";
    return call_api('POST', $url, null, [
        "Authorization: Bearer $token"
    ]);
}

function terminate_session($sessionName)
{
    global $config;

    $token = get_token($sessionName);
    if (!$token) return ['status' => 'error', 'message' => 'Token não encontrado'];

    $headers = [
        "Authorization: Bearer $token"
    ];

    // Logout
    $logoutUrl = $config['baseUrl'] . "/api/{$sessionName}/logout-session";
    $logoutResponse = call_api('POST', $logoutUrl, null, $headers);

    // Delete
    $deleteUrl = $config['baseUrl'] . "/api/{$sessionName}";
    $deleteResponse = call_api('DELETE', $deleteUrl, null, $headers);

    // Remover token local
    $tokens = load_tokens();
    if (isset($tokens[$sessionName])) {
        unset($tokens[$sessionName]);
        save_tokens($tokens);
    }

    // ✅ DEBUG TEMPORÁRIO
    file_put_contents('logs_terminate.txt', print_r([
        'logout' => $logoutResponse,
        'delete' => $deleteResponse
    ], true));

    return [
        'logout' => $logoutResponse,
        'delete' => $deleteResponse
    ];
}




function get_session_status($sessionName)
{
    global $config;
    $token = get_token($sessionName);
    if (!$token) return ['status' => 'error', 'message' => 'Token não encontrado'];

    $url = $config['baseUrl'] . "/api/{$sessionName}/status-session";
    return call_api('GET', $url, null, [
        "Authorization: Bearer $token"
    ]);
}

function delete_session($sessionName)
{
    global $config;
    $token = get_token($sessionName);

    // Se tem token, tentar logout primeiro
    if ($token) {
        logout_session($sessionName);
    }

    // Remover token do storage local
    $tokens = load_tokens();
    if (isset($tokens[$sessionName])) {
        unset($tokens[$sessionName]);
        save_tokens($tokens);
    }

    return ['status' => 'success', 'message' => 'Sessão excluída com sucesso'];
}

// Token Storage
function load_tokens()
{
    $path = __DIR__ . '/../storage/tokens.json';

    // Criar diretório se não existir
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (!file_exists($path)) {
        file_put_contents($path, '{}');
        return [];
    }

    $content = file_get_contents($path);
    return json_decode($content, true) ?? [];
}

function save_tokens($tokens)
{
    $path = __DIR__ . '/../storage/tokens.json';

    // Criar diretório se não existir
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($path, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function get_token($sessionName)
{
    $tokens = load_tokens();
    return $tokens[$sessionName] ?? null;
}

function get_saved_sessions()
{
    return array_keys(load_tokens());
}
function sendMessage($session, $phone, $messageData)
{
    $token = getTokenFromStorage($session);
    if (!$token) {
        return [
            "success" => false,
            "error" => "Token não encontrado para a sessão '$session'."
        ];
    }

    $url = "http://138.118.247.6:21465/api/{$session}/send-message";
    $payload = [];

    // Verifica se $messageData é a nossa estrutura de modelo
    if (is_array($messageData) && isset($messageData['body'])) {
        // É uma mensagem estruturada (com header, body, botões, etc.)
        $payload = [
            "phone" => $phone,
            "isGroup" => false,
            "isNewsletter" => false,
            "isLid" => false,
            "message" => $messageData['body'], // O corpo principal da mensagem
        ];

        // Adiciona botões, se existirem (verificação segura)
        if (isset($messageData['action'], $messageData['action']['list'], $messageData['action']['list']['options']) && !empty($messageData['action']['list']['options'])) {
            $buttons = [];
            foreach ($messageData['action']['list']['options'] as $option) {
                $buttons[] = [
                    "buttonId" => "id_" . uniqid(), // ID único para o botão
                    "buttonText" => ["displayText" => $option['title']],
                    "type" => 1
                ];
            }
            
            $payload['button'] = $buttons;
            $payload['headerType'] = 1; // Tipo de cabeçalho para texto simples com botões
            $payload['description'] = $messageData['footer'] ?? ''; // Usa o footer como descrição
            $payload['title'] = $messageData['action']['list']['title'] ?? 'Escolha uma opção';
            $payload['footer'] = $messageData['footer'] ?? '';
            $payload['buttonText'] = $messageData['action']['list']['button_text'] ?? 'Ver Opções';

        }
        // Adiciona cabeçalho de texto, se existir e não houver botões (para não conflitar)
        elseif (isset($messageData['header']['type']) && $messageData['header']['type'] === 'TEXT' && !empty($messageData['header']['text'])) {
             // A API do WPP-Connect pode não ter um campo de cabeçalho separado para texto simples.
             // A prática comum é concatenar o cabeçalho e o rodapé ao corpo da mensagem.
             $headerText = "*" . $messageData['header']['text'] . "*\n\n";
             $footerText = "\n\n" . ($messageData['footer'] ?? '');
             
             $payload['message'] = $headerText . $payload['message'] . $footerText;
        }

    } else {
        // É uma mensagem de texto simples (mantém compatibilidade)
        $payload = [
            "phone" => $phone,
            "isGroup" => false,
            "isNewsletter" => false,
            "isLid" => false,
            "message" => $messageData
        ];
    }

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer $token"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return [
            "success" => false,
            "error" => curl_error($ch)
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    $isOk = in_array($httpCode, [200, 201]) && isset($decoded['status']) && $decoded['status'] === 'success';

    return [
        "success" => $isOk,
        "status_code" => $httpCode,
        "response" => $decoded
    ];
}


function getTokenFromStorage($session)
{
    $tokenFile = __DIR__ . '/../storage/tokens.json';

    if (!file_exists($tokenFile)) {
        return null;
    }

    $tokens = json_decode(file_get_contents($tokenFile), true);

    return $tokens[$session] ?? null;
}
