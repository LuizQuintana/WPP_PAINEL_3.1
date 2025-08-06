<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (isset($_GET['id'])) {
    $integracao_id = $_GET['id'];

    try {
        $sql = "DELETE FROM integracoes WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $integracao_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message = "Integração excluída com sucesso!";
        } else {
            $message = "Nenhuma integração encontrada com o ID fornecido.";
        }

        header("Location: gerenciar_integracoes.php?msg=" . urlencode($message));
        exit();

    } catch (PDOException $e) {
        $error_message = "Erro ao excluir integração: " . $e->getMessage();
        header("Location: gerenciar_integracoes.php?msg=" . urlencode($error_message));
        exit();
    }
} else {
    // Se o ID não for fornecido, redireciona para a página principal
    header("Location: gerenciar_integracoes.php");
    exit();
}
?>