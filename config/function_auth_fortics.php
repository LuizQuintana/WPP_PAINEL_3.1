<?php

/**
 * Obtém o token de autenticação da API.
 *
 * @return array Array associativo com 'token' e 'id' (caso disponível)
 * @throws Exception Se ocorrer erro na requisição ou se o token não for encontrado
 */
function getAuthToken()
{
    $url = "https://ctvcolombo.sz.chat/api/v4/auth/login";
    $data = [
        "email"    => "appnetcol@gmail.com",
        "password" => "Kanguru34"
    ];

    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "Accept: application/json"
        ],
    ]);

    $response  = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_message = curl_error($ch);
        curl_close($ch);
        throw new Exception("Erro cURL: " . $error_message);
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception("Erro ao fazer login (HTTP $http_code): " . $response);
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
    }

    if (!isset($result['token'])) {
        throw new Exception("Token não encontrado na resposta.");
    }

    return [
        'token' => $result['token'],
        'id'    => $result['user']['_id'] ?? null
    ];
}
