<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nome = $_POST['nome'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $config_data = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'config_') === 0) {
            $config_name = substr($key, 7); // Remove 'config_' prefix
            $config_data[$config_name] = $value;
        }
    }
    $config_json = json_encode($config_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    try {
        if ($id) {
            // Atualizar integração existente
            $sql = "UPDATE integracoes SET nome = :nome, tipo = :tipo, config_json = :config_json, ativo = :ativo, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(compact('nome', 'tipo', 'config_json', 'ativo', 'id'));
            $message = "Integração atualizada com sucesso!";
        } else {
            // Inserir nova integração
            $sql = "INSERT INTO integracoes (nome, tipo, config_json, ativo) VALUES (:nome, :tipo, :config_json, :ativo)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(compact('nome', 'tipo', 'config_json', 'ativo'));
            $message = "Integração adicionada com sucesso!";
        }

        header("Location: gerenciar_integracoes.php?msg=" . urlencode($message));
        exit();

    } catch (PDOException $e) {
        $error_message = "Erro ao salvar integração: " . $e->getMessage();
        header("Location: gerenciar_integracoes.php?msg=" . urlencode($error_message));
        exit();
    }

} else {
    // Se não for POST, redireciona para a página principal
    header("Location: gerenciar_integracoes.php");
    exit();
}
?>