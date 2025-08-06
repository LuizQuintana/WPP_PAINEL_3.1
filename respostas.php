<?php
require_once 'config/db.php';
require_once __DIR__ . '/header.php';

$categoria_filtro = $_GET['categoria'] ?? 'Todas';

try {
    $sql = "SELECT * FROM respostas_clientes";
    if ($categoria_filtro !== 'Todas') {
        $sql .= " WHERE categoria = :categoria";
    }
    $sql .= " ORDER BY data_recebimento DESC";

    $stmt = $pdo->prepare($sql);

    if ($categoria_filtro !== 'Todas') {
        $stmt->bindParam(':categoria', $categoria_filtro, PDO::PARAM_STR);
    }

    $stmt->execute();
    $respostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar as respostas: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caixa de Entrada - Respostas dos Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/respostas.css">
    
</head>

<body>
    <div class="main-container">
        <div id="titulo">
            <h4><i class="fab fa-whatsapp"></i> SendNow 2.0</h4>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-inbox"></i> Caixa de Entrada</h2>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="categoria-filtro" class="form-label">Filtrar por Categoria</label>
                            <select id="categoria-filtro" name="categoria" class="form-select" onchange="this.form.submit()">
                                <option value="Todas" <?= ($categoria_filtro === 'Todas') ? 'selected' : '' ?>>Todas as Categorias</option>
                                <option value="PIX" <?= ($categoria_filtro === 'PIX') ? 'selected' : '' ?>>PIX</option>
                                <option value="Geral" <?= ($categoria_filtro === 'Geral') ? 'selected' : '' ?>>Geral</option>
                            </select>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Remetente</th>
                                <th>Mensagem</th>
                                <th>Categoria</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($respostas)) : ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">Nenhuma resposta encontrada.</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($respostas as $resposta) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($resposta['remetente']) ?></td>
                                        <td><?= nl2br(htmlspecialchars(substr($resposta['mensagem'], 0, 70))) . (strlen($resposta['mensagem']) > 70 ? '...' : '') ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($resposta['categoria']) ?></span></td>
                                        <td><?= date('d/m/Y H:i', strtotime($resposta['data_recebimento'])) ?></td>
                                        <td>
                                            <?php
                                            if ($resposta['respondida']) {
                                                echo '<span class="status-badge status-respondida">Respondida</span>';
                                            } elseif ($resposta['lida']) {
                                                echo '<span class="status-badge status-lida">Lida</span>';
                                            } else {
                                                echo '<span class="status-badge status-nao-lida">Não Lida</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="responder.php?id=<?= $resposta['id'] ?>" class="btn btn-sm btn-primary">Responder</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
