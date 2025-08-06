<?php
// buscar_modelo.php
session_start();

// Verifica login
if (!isset($_SESSION['user_user'])) {
    header("Location: login.php");
    exit();
}

require_once "config/conn.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    try {
        $sql = "SELECT * FROM MODELOS_MENSAGEM WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($modelo) {
            echo json_encode([
                'success' => true,
                'data' => $modelo
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Modelo não encontrado'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar modelo: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID não fornecido'
    ]);
}
