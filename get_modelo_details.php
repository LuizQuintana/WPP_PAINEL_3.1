<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config/conn.php';

$response = ['success' => false, 'message' => 'ID do modelo não fornecido.'];

if (isset($_GET['id'])) {
    $modelo_id = $_GET['id'];

    try {
        $stmt = $conn->prepare("SELECT nome, conteudo FROM DBA_MODELOS_MSG WHERE id = :id");
        $stmt->bindParam(':id', $modelo_id, PDO::PARAM_INT);
        $stmt->execute();
        $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($modelo) {
            $response['success'] = true;
            $response['message'] = 'Modelo encontrado.';
            $response['data'] = [
                'id' => $modelo_id,
                'nome' => $modelo['nome'],
                'conteudo' => json_decode($modelo['conteudo']) // Decodifica o JSON para o frontend
            ];
        } else {
            $response['message'] = 'Modelo não encontrado.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    }
} 

echo json_encode($response);
?>