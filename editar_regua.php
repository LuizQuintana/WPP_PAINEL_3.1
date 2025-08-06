<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once "config/conn.php"; // Certifique-se de que a conexão está correta.

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST["id"] ?? null;
    $regua_name = $_POST["name_regua01"] ?? null;
    $intervalo = $_POST["intervalo"] ?? null;
    $hora = $_POST["hora"] ?? null;
    $TIPO_DE_MODELO = $_POST["tipo2"] ?? null;

    if ($TIPO_DE_MODELO === "Wpp-Connect") {
        $modelo_mensagem = $_POST["modelo_mensagem03"] ?? null;
    } else {
        $modelo_mensagem = $_POST["modelo_mensagem02"] ?? null;
    }

    $status = $_POST["status"] ?? null;
    $TIPO_DE_MODELO = $_POST["tipo2"] ?? null; // Define um valor padrão
    $REGUA_ID = $_POST["tipo1"] ?? null;
    $sessao = $_POST["sessao_wppconnect"] ?? null;




    if (empty($id) || $regua_name === null || $intervalo === null || empty($hora) || empty($status)) {

        echo json_encode(["success" => false, "message" => "Preencha todos os campos obrigatórios."]);
        exit;
    }

    try {
        $sql = "UPDATE REGUAS_CRIADAS 
                SET 
                nome = :nome_regua,
                intervalo = :intervalo, 
                    hora = :hora, 
                    modelo_mensagem = :modelo_mensagem, 
                    status = :status,
                    TIPO_DE_MODELO = :TIPO_DE_MODELO,
                    Session_WPP = :sessao_wppconnect,
                    REGUA_ID = :REGUA_ID
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":nome_regua", $regua_name, PDO::PARAM_STR);
        $stmt->bindParam(":intervalo", $intervalo);
        $stmt->bindParam(":hora", $hora);
        $stmt->bindParam(":modelo_mensagem", $modelo_mensagem, PDO::PARAM_STR);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":TIPO_DE_MODELO", $TIPO_DE_MODELO);
        $stmt->bindParam(":sessao_wppconnect", $sessao, PDO::PARAM_STR);
        $stmt->bindParam(":REGUA_ID", $REGUA_ID);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Régua atualizada com sucesso!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Erro ao atualizar régua."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Erro: " . $e->getMessage()]);
    }
}
