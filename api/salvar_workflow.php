<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Requisição inválida.'];

// Pega o corpo da requisição
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data) {
    $id = $data['id'] ?? null;
    $name = $data['name'] ?? 'Workflow sem nome';
    $ativo = $data['ativo'] ?? 1;
    $gatilho = $data['gatilho'] ?? 'visual';

    // O editor visual usa 'nodes' e 'connections', vamos salvar isso em acoes_json
    // e deixar condicoes_json vazio por enquanto, para manter a compatibilidade
    $acoes_json = json_encode([
        'nodes' => $data['nodes'] ?? [],
        'connections' => $data['connections'] ?? []
    ]);
    $condicoes_json = '{}'; // Vazio para o editor visual

    try {
        if ($id) {
            // Atualizar workflow existente
            $sql = "UPDATE workflows SET nome = :nome, gatilho = :gatilho, condicoes_json = :condicoes_json, acoes_json = :acoes_json, ativo = :ativo WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $name,
                ':gatilho' => $gatilho,
                ':condicoes_json' => $condicoes_json,
                ':acoes_json' => $acoes_json,
                ':ativo' => $ativo,
                ':id' => $id
            ]);
            $response['message'] = 'Workflow atualizado com sucesso!';
        } else {
            // Inserir novo workflow
            $sql = "INSERT INTO workflows (nome, gatilho, condicoes_json, acoes_json, ativo) VALUES (:nome, :gatilho, :condicoes_json, :acoes_json, :ativo)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $name,
                ':gatilho' => $gatilho,
                ':condicoes_json' => $condicoes_json,
                ':acoes_json' => $acoes_json,
                ':ativo' => $ativo
            ]);
            $id = $pdo->lastInsertId();
            $response['message'] = 'Workflow criado com sucesso!';
            $response['new_id'] = $id;
        }
        $response['success'] = true;
    } catch (PDOException $e) {
        $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Nenhum dado recebido.';
}

echo json_encode($response);
