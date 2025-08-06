<?php
require 'config/conn.php'; // Arquivo de conexão com o banco

header('Content-Type: application/json'); // Define o tipo de resposta como JSON

if (isset($_POST['id'])) {
    $id = $_POST['id'];

    try {
        $sql = "DELETE FROM REGUAS_CRIADAS WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Régua excluída com sucesso!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Erro ao excluir a régua."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Erro ao excluir: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "ID inválido!"]);
}
