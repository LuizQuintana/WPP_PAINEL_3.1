<?php
// Define o cabeçalho da resposta como JSON
header('Content-Type: application/json');

// Inclui a conexão com o banco de dados
include 'config/conn.php';

// Prepara a resposta padrão
$response = ['success' => false, 'message' => 'Requisição inválida.'];

// Verifica se o método da requisição é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica se o ID foi enviado
    if (isset($_POST['id'])) {
        // Valida o ID para garantir que é um número inteiro positivo
        $modelo_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($modelo_id && $modelo_id > 0) {
            try {
                // Prepara e executa a exclusão de forma segura
                $stmt = $conn->prepare("DELETE FROM DBA_MODELOS_MSG WHERE id = :id");
                $stmt->bindParam(':id', $modelo_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    // Verifica se alguma linha foi realmente afetada
                    if ($stmt->rowCount() > 0) {
                        $response = ['success' => true];
                    } else {
                        $response['message'] = 'Nenhum modelo encontrado com o ID fornecido.';
                    }
                } else {
                    $response['message'] = 'Erro ao executar a exclusão no banco de dados.';
                }
            } catch (PDOException $e) {
                // Captura erros de banco de dados
                // Em um ambiente de produção, evite expor detalhes do erro.
                // error_log("Erro de exclusão no banco de dados: " . $e->getMessage());
                $response['message'] = 'Erro no servidor ao tentar excluir o modelo.';
            }
        } else {
            $response['message'] = 'O ID do modelo fornecido é inválido.';
        }
    } else {
        $response['message'] = 'O ID do modelo não foi fornecido.';
    }
}

// Envia a resposta final em formato JSON
echo json_encode($response);
exit();
?>
