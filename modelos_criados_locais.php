<?php
include 'config/conn.php'; // ajuste se necessário
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Modelos Criados - WPP-Connect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modelos_criados.css">
</head>

<body>
    <div id="container">
        <div id="titulo">
            <h4><i class="fab fa-whatsapp"></i> SendNow 2.0</h4>
        </div>
        <?php include 'header.php'; ?>
        <div class="main-content">
            <h2>Modelos Criados - WPP-Connect Local</h2>

            <?php if (isset($_GET['msg'])) : ?>
                <div class="msg"><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>

            <div class="modelos-container">
                <?php
                try {
                    $stmt = $conn->query("SELECT * FROM DBA_MODELOS_MSG ORDER BY id DESC");
                    $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($modelos) :
                        foreach ($modelos as $modelo) :
                            $nome = htmlspecialchars($modelo['nome']);
                            $conteudo = htmlspecialchars($modelo['conteudo']);
                ?>
                            <div class="modelo">
                                <h4><?= $nome ?></h4>
                                <p><?= $conteudo ?></p>
                                <a href="editar_modelo.php?id=<?= $modelo['id'] ?>" class="btn-editar">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                            </div>

                <?php
                        endforeach;
                    else :
                        echo '<p class="no-data">Nenhum modelo encontrado.</p>';
                    endif;
                } catch (PDOException $e) {
                    echo '<p class="no-data">Erro ao buscar modelos: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
                ?>
            </div>

            <center>
                <a href="modelos.php" class="btn-voltar"><i class="fas fa-arrow-left"></i> Voltar</a>
            </center>
        </div>
    </div>

</html>