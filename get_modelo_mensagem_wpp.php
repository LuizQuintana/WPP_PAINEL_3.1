<?php
// Supõe que $conn é sua conexão PDO já aberta

if (!isset($_GET['modelo'])) {
    echo json_encode(['error' => 'Modelo não especificado']);
    exit;
}

include_once 'config/conn.php'; // Inclui a configuração do banco de dados
$modeloBusca = strtolower($_GET['modelo']);

try {
    $stmt = $conn->query("SELECT * FROM DBA_MODELOS_MSG ORDER BY id DESC");
    $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$modelos) {
        echo json_encode(['error' => 'Nenhum modelo encontrado no banco']);
        exit;
    }

    $modeloEncontrado = null;

    foreach ($modelos as $modelo) {
        if (strtolower($modelo['nome']) === $modeloBusca) {
            $modeloEncontrado = $modelo;
            break;
        }
    }

    if ($modeloEncontrado) {
        // Decodifica o JSON do campo 'conteudo'
        $conteudoDecodificado = json_decode($modeloEncontrado['conteudo'], true);

        // Pega o texto do corpo da mensagem, ou um texto padrão se não existir
        $textoBody = isset($conteudoDecodificado['body']) ? $conteudoDecodificado['body'] : 'Corpo da mensagem não encontrado no modelo.';

        // Monta a estrutura JSON esperada pelo front
        $response = [
            'name' => $modeloEncontrado['nome'],
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $textoBody,
                ]
            ],
        ];

        echo json_encode($response);
    } else {
        echo json_encode(['error' => 'Modelo nao encontrado']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro no banco: ' . $e->getMessage()]);
}
