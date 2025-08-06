<?php
if (!isset($_GET['modelo'])) {
    echo json_encode(['error' => 'Modelo não especificado']);
    exit;
}

$modelo = $_GET['modelo'];
$apiKey = '4NJjTQFz6AGR84Ad3Se64gOvAK';
$baseUrl = 'https://waba.360dialog.io/v1/configs/templates';

// Parâmetros da API
$params = [
    'limit'  => 1000,
    'offset' => 0,
    'sort'   => 'business_templates.name'
];

$queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
$url = $baseUrl . '?' . $queryString;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'D360-API-KEY: ' . $apiKey
    ]
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(['error' => 'Erro cURL: ' . curl_error($ch)]);
    exit;
}

$data = json_decode($response, true);
curl_close($ch);

if (!isset($data['waba_templates'])) {
    echo json_encode(['error' => 'Templates não encontrados']);
    exit;
}

$modelos = $data['waba_templates'];
$modeloEncontrado = null;

foreach ($modelos as $m) {
    if (strtolower($m['name']) == strtolower($modelo)) {
        $modeloEncontrado = $m;
        break;
    }
}

if ($modeloEncontrado) {
    echo json_encode($modeloEncontrado);
} else {
    echo json_encode(['error' => 'Modelo não encontrado']);
}
