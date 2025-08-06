<?php
require_once "config/conn.php";
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id"])) {
    $id = $_POST["id"];

    try {
        $sql = "SELECT * FROM REGUAS_CRIADAS WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $regua = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($regua) {
            echo json_encode(["success" => true, "data" => $regua]);
        } else {
            echo json_encode(["success" => false, "message" => "Registro não encontrado."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Erro: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Requisição inválida."]);
}
