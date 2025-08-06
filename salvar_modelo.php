<?php
// --- salvar_modelo.php ---
include 'config/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $conteudo = isset($_POST['conteudo']) ? trim($_POST['conteudo']) : '';

    if (empty($nome) || empty($conteudo)) {
        // Redireciona com erro
        header('Location: modelos.php?msg=' . urlencode('Preencha todos os campos.'));
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO DBA_MODELOS_MSG (nome, conteudo) VALUES (:nome, :conteudo)");
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':conteudo', $conteudo);
        $stmt->execute();

        header('Location: modelos.php?msg=' . urlencode('Modelo salvo com sucesso!'));
        exit;
    } catch (PDOException $e) {
        error_log("Erro ao salvar modelo: " . $e->getMessage());
        header('Location: modelos.php?msg=' . urlencode('Erro ao salvar modelo.'));
        exit;
    }
} else {
    header('Location: modelos.php?msg=' . urlencode('Método inválido.'));
    exit;
}
