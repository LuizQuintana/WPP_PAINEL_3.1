<?php

// Configurações da API
$api_url = "http://138.118.247.66:8080/mk/WSAutenticacao.rule?sys=MK0";
$token_usuario = "4d1cf9dcd5e22635057fbc78c2b0da74"; // Token do usuário
$password = "33656d83ba23107"; // Contra senha do perfil
$codigo_servico = "9999";

// Monta a URL com os parâmetros
$full_url = "$api_url&token=$token_usuario&password=$password&cd_servico=$codigo_servico";

// Inicializa a requisição cURL
$ch = curl_init();

// Configurações da requisição
curl_setopt($ch, CURLOPT_URL, $full_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Executa a requisição e captura a resposta
$response = curl_exec($ch);

// Fecha a conexão cURL
curl_close($ch);

// Verifica se houve erro
if ($response === false) {
    echo 'Erro na requisição: ' . curl_error($ch);
} else {
    // Decodifica a resposta JSON
    $response_data = json_decode($response, true);

    if (isset($response_data['Token'])) {
        $tokem_retornado = $response_data['Token'];
        echo "Token recebido: " . $tokem_retornado;
    } else {
        echo "Erro ao obter o token: " . $response;
    }
}
