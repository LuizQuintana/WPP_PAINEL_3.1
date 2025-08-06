<?php
include 'config/db.php';
include 'api/wpp_api.php';

$name = $_POST['name'] ?? '';

var_dump($_POST);
if (!$name) {
    exit('Nome da sessão é obrigatório.');
}

$tokenData = generate_token($name);

var_dump($tokenData);

if (!$tokenData || $tokenData['status'] !== 'success') {
    exit('Erro ao gerar token.');
}

$stmt = $pdo->prepare("INSERT INTO sessions (name, token, status)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE token = VALUES(token), status = VALUES(status)");
$stmt->execute([$name, $tokenData['token'], 'created']);


header('Location: index.php');
exit;




