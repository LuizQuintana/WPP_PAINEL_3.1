<?php
require_once 'config/db.php';
require_once __DIR__ . '/../api/wpp_api.php';
require_once __DIR__ . '/header.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID da resposta não fornecido.");
}

try {
    $sql = "SELECT * FROM respostas_clientes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $resposta = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar a resposta: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem_resposta = $_POST['mensagem_resposta'] ?? '';
    $session = 'painel'; // Assumindo que a sessão é 'painel'

    if (!empty($mensagem_resposta)) {
        $phone = $resposta['remetente'];
        $result = sendMessage($session, $phone, $mensagem_resposta);

        if ($result['success']) {
            $sql_update = "UPDATE respostas_clientes SET respondida = TRUE, data_resposta = NOW(), resposta = :resposta WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':resposta' => $mensagem_resposta,
                ':id' => $id
            ]);

            header("Location: respostas.php");
            exit();
        } else {
            $error_message = "Erro ao enviar a resposta: " . ($result['error'] ?? 'Erro desconhecido');
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responder Mensagem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- O tema_global.css será carregado pelo header.php -->
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="main-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-reply"></i> Responder Mensagem</h2>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger mt-3">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <strong>De:</strong> <?php echo htmlspecialchars($resposta['remetente']); ?><br>
                <strong>Data:</strong> <?php echo date('d/m/Y H:i:s', strtotime($resposta['data_recebimento'])); ?>
            </div>
            <div class="card-body">
                <p><?php echo nl2br(htmlspecialchars($resposta['mensagem'])); ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="mensagem_resposta" class="form-label">Sua Resposta:</label>
                        <textarea name="mensagem_resposta" id="mensagem_resposta" rows="5" class="form-control" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Enviar Resposta</button>
                    <a href="respostas.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
