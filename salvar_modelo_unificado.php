<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'config/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modelo_id = $_POST['modelo_id'] ?? null;
    $nome_modelo = $_POST['nome_modelo'] ?? 'Modelo sem nome';

    // Monta a estrutura base do modelo
    $modelo_data = [
        'canal' => $_POST['canal'] ?? '1',
        'categoria' => $_POST['categoria'] ?? 'SERVICE',
        'idioma' => $_POST['idioma'] ?? 'pt_BR',
        'header' => [
            'type' => $_POST['header_type'] ?? 'NONE',
            'text' => $_POST['header_text'] ?? null
        ],
        'body' => $_POST['conteudo'] ?? '',
        'footer' => $_POST['footer_text'] ?? ''
    ];

    // Adiciona a seção 'action' apenas se houver um título para a lista
    if (!empty($_POST['list_title']) && !empty($_POST['list_options'])) {
        $modelo_data['action'] = [
            'list' => [
                'title' => $_POST['list_title'],
                'button_text' => $_POST['list_button_text'] ?? '',
                'options' => $_POST['list_options']
            ]
        ];
    }

    // Converte o array para uma string JSON para salvar no banco
    $conteudo_json = json_encode($modelo_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    try {
        if (!empty($modelo_id)) {
            // Atualizar modelo existente
            $stmt = $conn->prepare("UPDATE DBA_MODELOS_MSG SET nome = :nome, conteudo = :conteudo WHERE id = :id");
            $stmt->bindParam(':id', $modelo_id, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $nome_modelo, PDO::PARAM_STR);
            $stmt->bindParam(':conteudo', $conteudo_json, PDO::PARAM_STR);
            $stmt->execute();
            $msg = "Modelo atualizado com sucesso!";
        } else {
            // Inserir novo modelo
            $stmt = $conn->prepare("INSERT INTO DBA_MODELOS_MSG (nome, conteudo) VALUES (:nome, :conteudo)");
            $stmt->bindParam(':nome', $nome_modelo, PDO::PARAM_STR);
            $stmt->bindParam(':conteudo', $conteudo_json, PDO::PARAM_STR);
            $stmt->execute();
            $msg = "Modelo criado com sucesso!";
        }

        header("Location: gerenciar_modelos.php?msg=" . urlencode($msg));
        exit();

    } catch (PDOException $e) {
        // Em caso de erro, redireciona com a mensagem de erro
        $error_msg = "Erro ao salvar modelo: " . $e->getMessage();
        header("Location: gerenciar_modelos.php?msg=" . urlencode($error_msg));
        exit();
    }

} else {
    // Se não for POST, redireciona para a página principal
    header("Location: gerenciar_modelos.php");
    exit();
}
?>
