<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

$response = ['success' => false, 'message' => 'ID da integração não fornecido.'];

if (isset($_GET['id'])) {
    $integracao_id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT id, nome, tipo, config_json, ativo FROM integracoes WHERE id = :id");
        $stmt->bindParam(':id', $integracao_id, PDO::PARAM_INT);
        $stmt->execute();
        $integracao = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($integracao) {
            $response['success'] = true;
            $response['message'] = 'Integração encontrada.';
            $response['data'] = [
                'id' => $integracao['id'],
                'nome' => $integracao['nome'],
                'tipo' => $integracao['tipo'],
                'config_json' => $integracao['config_json'], // Retorna o JSON como string
                'ativo' => (bool)$integracao['ativo']
            ];
        } else {
            $response['message'] = 'Integração não encontrada.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    }
} 

echo json_encode($response);
?>