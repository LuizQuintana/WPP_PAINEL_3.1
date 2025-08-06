<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $conteudo = $_POST['conteudo'] ?? '';

    if (!empty($nome) && !empty($conteudo)) {
        require_once 'config/conn.php'; // aqui já define $conn (PDO)

        try {
            $stmt = $conn->prepare("INSERT INTO menu_modelos (nome, conteudo) VALUES (:nome, :conteudo)");
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':conteudo', $conteudo);

            if ($stmt->execute()) {
                header("Location: criar_modelo_menu.php?msg=Menu salvo com sucesso!");
                exit();
            } else {
                header("Location: criar_modelo_menu.php?msg=Erro ao salvar o menu.");
                exit();
            }
        } catch (PDOException $e) {
            header("Location: criar_modelo_menu.php?msg=Erro: " . $e->getMessage());
            exit();
        }
    } else {
        header("Location: criar_modelo_menu.php?msg=Preencha todos os campos.");
        exit();
    }
}
