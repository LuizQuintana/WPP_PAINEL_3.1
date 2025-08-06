<?php
header('Content-Type: application/json');
require 'config/conn.php';

if (!isset($_GET['id'], $_GET['status'])) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

$id = intval($_GET['id']);
$status = ($_GET['status'] === 'Ativo') ? 'Ativo' : 'Inativo';

$stmt = $conn->prepare("UPDATE REGUAS_CRIADAS SET status = ? WHERE id = ?");
if ($stmt->execute([$status, $id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Falha ao atualizar status']);
}
