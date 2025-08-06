<?php
session_start();

if (!isset($_SESSION['user_user'])) {
    header("Location: login.php");
    exit();
}

$usuarioLogado = $_SESSION['user_user'];
include_once "config/conn.php";

// Filtros permitidos para consulta
$allowedFilters = ['CONTATO', 'FATURA', 'NOME', 'DATA_VENCIMENTO', 'DATA_ENVIO'];

// Obtenção segura dos parâmetros via GET
$data_inicio   = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$data_fim      = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$filtro        = filter_input(INPUT_GET, 'filtro', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$palavra_chave = filter_input(INPUT_GET, 'palavra_chave', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
$page          = max(1, intval(filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1));

$filtro = in_array($filtro, $allowedFilters) ? $filtro : '';

// Função para validar data no formato 'Y-m-d'
function isValidDate($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$data_inicio = isValidDate($data_inicio) ? $data_inicio : '';
$data_fim    = isValidDate($data_fim) ? $data_fim : '';

$limit  = 30;
$offset = ($page - 1) * $limit;

/**
 * Constrói a query de acordo com os filtros de data e palavra-chave.
 *
 * @param string $baseQuery Consulta base ("SELECT ..." ou "COUNT(*) FROM ...")
 * @param array  &$params Vetor que receberá os parâmetros vinculados à query
 * @param string $dataInicio Data inicial
 * @param string $dataFim Data final
 * @param string $filtro Nome da coluna a ser filtrada
 * @param string $palavraChave Palavra-chave para busca
 * @return string Query final
 */
function buildQuery(string $baseQuery, array &$params, string $dataInicio, string $dataFim, string $filtro, string $palavraChave)
{
    $query = $baseQuery . " WHERE 1";
    if (!empty($dataInicio) && !empty($dataFim)) {
        $query .= " AND DATA_ENVIO BETWEEN :data_inicio AND :data_fim";
        $params[':data_inicio'] = "$dataInicio 00:00:00";
        $params[':data_fim']    = "$dataFim 23:59:59";
    }
    if (!empty($filtro) && !empty($palavraChave)) {
        $query .= " AND $filtro LIKE :palavra_chave";
        $params[':palavra_chave'] = "%" . htmlspecialchars($palavraChave) . "%";
    }
    return $query;
}
$indice_envios = 1;
$indice_falhas = 1;
// Bases de consulta para envios e falhas
$baseSelect = "SELECT ID, FATURA, CONTATO, CELULAR, NOME, VALOR, DATA_VENCIMENTO, DATA_ENVIO, ERRO, MODELO_MENSAGEM FROM";
$baseCount  = "SELECT COUNT(*) FROM";

$params      = [];
$countParams = [];

$queryEnvios = buildQuery("$baseSelect LOG_ENVIOS", $params, $data_inicio, $data_fim, $filtro, $palavra_chave);
$queryFalhas = buildQuery("$baseSelect LOG_ENVIOS_FALHA", $params, $data_inicio, $data_fim, $filtro, $palavra_chave);
$countEnvios = buildQuery("$baseCount LOG_ENVIOS", $countParams, $data_inicio, $data_fim, $filtro, $palavra_chave);
$countFalhas = buildQuery("$baseCount LOG_ENVIOS_FALHA", $countParams, $data_inicio, $data_fim, $filtro, $palavra_chave);

$queryEnvios .= " ORDER BY DATA_ENVIO DESC LIMIT $limit OFFSET $offset";
$queryFalhas .= " ORDER BY DATA_ENVIO DESC LIMIT $limit OFFSET $offset";

try {
    $stmtEnvios = $conn->prepare($queryEnvios);
    $stmtFalhas = $conn->prepare($queryFalhas);
    $stmtEnvios->execute($params);
    $stmtFalhas->execute($params);

    $envios = $stmtEnvios->fetchAll(PDO::FETCH_ASSOC);
    $falhas = $stmtFalhas->fetchAll(PDO::FETCH_ASSOC);

    $stmtTotalEnvios = $conn->prepare($countEnvios);
    $stmtTotalFalhas = $conn->prepare($countFalhas);
    $stmtTotalEnvios->execute($countParams);
    $stmtTotalFalhas->execute($countParams);

    $totalEnvios = $stmtTotalEnvios->fetchColumn();
    $totalFalhas = $stmtTotalFalhas->fetchColumn();

    // Define total de registros e páginas (usa a maior contagem entre as duas consultas)
    $totalRegistros = max($totalEnvios, $totalFalhas);
    $totalPaginas   = ceil($totalRegistros / $limit);
} catch (PDOException $e) {
    die("Erro ao executar a consulta: " . $e->getMessage());
}

/**
 * Função para contar os registros por dias específicos (utilizando LOG_ENVIOS).
 *
 * @param PDO    $conn Conexão PDO
 * @param string|null $dataInicio Data inicial
 * @param string|null $dataFim Data final
 * @param string|null $filtroAdicional Filtro adicional (se houver)
 * @return array Vetor com contagens para cada faixa
 */
function countPorDiasEspecificos(PDO $conn, $dataInicio = null, $dataFim = null, $filtroAdicional = null)
{
    $resultados   = [];
    $diasEspecificos = [0, -5, 5, 12, 20, 30, 42, 52, 62];

    foreach ($diasEspecificos as $dias) {


        $query = "SELECT COUNT(*) FROM LOG_ENVIOS 
                  WHERE DATA_VENCIMENTO IS NOT NULL 
                    AND DATA_VENCIMENTO != '' 
                    AND DATEDIFF(DATE(DATA_ENVIO), DATE(DATA_ENVIO)) = :dias";
        // Ajuste da query para comparar DATA_ENVIO com DATA_VENCIMENTO
        $query = "SELECT COUNT(*) FROM LOG_ENVIOS 
                  WHERE DATA_VENCIMENTO IS NOT NULL 
                    AND DATA_VENCIMENTO != '' 
                    AND DATEDIFF(DATE(DATA_ENVIO), DATE(DATA_ENVIO)) = :dias";
        $query = "SELECT COUNT(*) FROM LOG_ENVIOS 
                  WHERE DATA_VENCIMENTO IS NOT NULL 
                    AND DATA_ENVIO IS NOT NULL 
                    AND DATEDIFF(DATE(DATA_ENVIO), DATE(DATA_VENCIMENTO)) = :dias";
        $params = [':dias' => $dias];

        if ($dataInicio && $dataFim) {
            $query .= " AND DATA_ENVIO BETWEEN :data_inicio AND :data_fim";
            $params[':data_inicio'] = "$dataInicio 00:00:00";
            $params[':data_fim']    = "$dataFim 23:59:59";
        }
        if ($filtroAdicional) {
            $query .= " " . $filtroAdicional;
        }
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();

        if ($dias == 0) {
            $chave = 'vencimentos_hoje';
        } elseif ($dias < 0) {
            $chave = 'antes_' . abs($dias) . '_dias';
        } else {
            $chave = 'vencidos_' . $dias . '_dias';
        }
        $resultados[$chave] = $count;
    }
    return $resultados;
}

try {
    // Obtém estatísticas para os cards utilizando os filtros atuais (se houver)
    $contagens = countPorDiasEspecificos($conn, $data_inicio, $data_fim);
} catch (PDOException $e) {
    die("Erro ao calcular estatísticas: " . $e->getMessage());
}

// Atribui os valores para os cards
$vencimentosHoje = $contagens['vencimentos_hoje'] ?? 0;
$vencimentos5Dias = $contagens['antes_5_dias'] ?? 0;
$vencidos5       = $contagens['vencidos_5_dias'] ?? 0;
$vencidos12      = $contagens['vencidos_12_dias'] ?? 0;
$vencidos20      = $contagens['vencidos_20_dias'] ?? 0;
$vencidos30      = $contagens['vencidos_30_dias'] ?? 0;
$vencidos42      = $contagens['vencidos_42_dias'] ?? 0;
$vencidos52      = $contagens['vencidos_52_dias'] ?? 0;
$vencidos62      = $contagens['vencidos_62_dias'] ?? 0;



// Contagem dos envios bem-sucedidos conforme os filtros (ou para o dia atual se não houver filtro)
try {
    if (!empty($data_inicio) && !empty($data_fim)) {
        $stmtTotalEnviosDiaSucesso = $conn->prepare("SELECT COUNT(*) FROM LOG_ENVIOS WHERE DATA_ENVIO BETWEEN :data_inicio AND :data_fim");
        $stmtTotalEnviosDiaSucesso->bindValue(':data_inicio', "$data_inicio 00:00:00");
        $stmtTotalEnviosDiaSucesso->bindValue(':data_fim', "$data_fim 23:59:59");
    } else {
        $stmtTotalEnviosDiaSucesso = $conn->prepare("SELECT COUNT(*) FROM LOG_ENVIOS WHERE DATE(DATA_ENVIO) = CURDATE()");
    }
    $stmtTotalEnviosDiaSucesso->execute();
    $totalEnviosDiaSucesso = $stmtTotalEnviosDiaSucesso->fetchColumn();
} catch (PDOException $e) {
    die("Erro ao contar os envios (sucesso) do dia: " . $e->getMessage());
}


// Contagem dos envios com falha conforme os filtros (ou para o dia atual se não houver filtro)
try {
    if (!empty($data_inicio) && !empty($data_fim)) {
        $stmtTotalEnviosDiaFalha = $conn->prepare("SELECT COUNT(*) FROM LOG_ENVIOS_FALHA WHERE DATA_ENVIO BETWEEN :data_inicio AND :data_fim");
        $stmtTotalEnviosDiaFalha->bindValue(':data_inicio', "$data_inicio 00:00:00");
        $stmtTotalEnviosDiaFalha->bindValue(':data_fim', "$data_fim 23:59:59");
    } else {
        $stmtTotalEnviosDiaFalha = $conn->prepare("SELECT COUNT(*) FROM LOG_ENVIOS_FALHA WHERE DATE(DATA_ENVIO) = CURDATE()");
    }
    $stmtTotalEnviosDiaFalha->execute();
    $totalEnviosDiaFalha = $stmtTotalEnviosDiaFalha->fetchColumn();
} catch (PDOException $e) {
    die("Erro ao contar os envios (falha) do dia: " . $e->getMessage());
}


// Contagem dos envios com falha conforme os filtros (ou para o dia atual se não houver filtro)
try {
    if (!empty($data_inicio) && !empty($data_fim)) {
        $stmtTotalEnviosDiaPendente = $conn->prepare("SELECT COUNT(*) FROM FATURAS_A_VENCER WHERE DATA_ATUAL BETWEEN :data_inicio AND :data_fim");
        $stmtTotalEnviosDiaPendente->bindValue(':data_inicio', $data_inicio);
        $stmtTotalEnviosDiaPendente->bindValue(':data_fim', $data_fim);
    } else {
        $stmtTotalEnviosDiaPendente = $conn->prepare("SELECT COUNT(*) FROM FATURAS_A_VENCER WHERE DATA_ATUAL = CURDATE()");
    }
    $stmtTotalEnviosDiaPendente->execute();
    $totalEnviosDiaPendente = $stmtTotalEnviosDiaPendente->fetchColumn();
} catch (PDOException $e) {
    die("Erro ao contar os envios (Pendente) do dia: " . $e->getMessage());
}


?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Envios</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/html.css">
    <link rel="stylesheet" href="assets/fonts.css">
    <link rel="stylesheet" href="assets/envios.css">
</head>

<body>
    <div class="container-fluid_envios">
        <div id="titulo">
            <h4><i class="fab fa-whatsapp"></i> SendNow 2.0</h4>
        </div>
        <?php include 'header.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 p-4 main01">
            <!-- Painéis de Vencimentos -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Hoje</h5>
                            <p class="card-text fs-4"><?= $vencimentosHoje ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-primary mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Em 5 dias</h5>
                            <p class="card-text fs-4"><?= $vencimentos5Dias ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Atrasados 5d</h5>
                            <p class="card-text fs-4"><?= $vencidos5 ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Atrasados 12d</h5>
                            <p class="card-text fs-4"><?= $vencidos12 ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Atrasados 20d</h5>
                            <p class="card-text fs-4"><?= $vencidos20 ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Atrasados 30d</h5>
                            <p class="card-text fs-4"><?= $vencidos30 ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-warning mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Atrasados 42d</h5>
                            <p class="card-text fs-4"><?= $vencidos42 ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-danger mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Atrasados 52d</h5>
                            <p class="card-text fs-4"><?= $vencidos52 ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-white mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Atrasados 62d</h5>
                            <p class="card-text fs-4"><?= $vencidos62 ?></p>
                        </div>
                    </div>
                </div>
                <!-- Card para Envios Bem-Sucedidos do Dia -->
                <div class="col-md-3">
                    <div class="card text-bg-secondary mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Envios Hoje (Sucesso)</h5>
                            <p class="card-text fs-4"><?= $totalEnviosDiaSucesso ?></p>
                        </div>
                    </div>
                </div>

                <!-- Card para Envios com Falha do Dia -->
                <div class="col-md-3">
                    <div class="card text-bg-danger mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Envios Hoje (Falha)</h5>
                            <p class="card-text fs-4"><?= $totalEnviosDiaFalha ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-bg-secondary mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Envios Pendentes</h5>
                            <p class="card-text fs-4"><?= $totalEnviosDiaPendente ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtro de pesquisa -->
            <div class="row">
                <form method="GET" class="bg-secondary p-4 rounded shadow-sm mb-5">
                    <div class="row">
                        <div class="col-md-2">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filtrar por</label>
                            <select name="filtro" class="form-select">
                                <option value="">Selecione...</option>
                                <option value="CONTATO" <?= $filtro == 'CONTATO' ? 'selected' : '' ?>>Contato</option>
                                <option value="FATURA" <?= $filtro == 'FATURA' ? 'selected' : '' ?>>Fatura</option>
                                <option value="NOME" <?= $filtro == 'NOME' ? 'selected' : '' ?>>Nome</option>
                                <option value="DATA_VENCIMENTO" <?= $filtro == 'DATA_VENCIMENTO' ? 'selected' : '' ?>>Data de Vencimento</option>
                                <option value="DATA_ENVIO" <?= $filtro == 'DATA_ENVIO' ? 'selected' : '' ?>>Data de Envio</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Palavra-chave</label>
                            <input type="text" name="palavra_chave" class="form-control" value="<?= htmlspecialchars($palavra_chave) ?>">
                        </div>
                    </div>
                    <button type="  " class="btn btn-primary mt-3">🔍 Filtrar</button>
                </form>
            </div>

            <!-- Tabela de Envios Bem-Sucedidos -->
            <h4>Envios Bem-Sucedidos</h4>
            <?php if (!empty($_SESSION['msg'])): ?>
                <div class="alert alert-info"><?= $_SESSION['msg'];
                                                unset($_SESSION['msg']); ?></div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle table-striped">
                    <thead class="table-primary">
                        <tr>
                            <th>#</th>
                            <th>Fatura</th>
                            <th>Contato</th>
                            <th>Celular</th>
                            <th>Nome</th>
                            <th>Valor</th>
                            <th>Data Vencimento</th>
                            <th>Data Envio</th>
                            <th>Erro</th>
                            <th>Modelo Mensagem</th>
                            <th>Mensagem Enviada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($envios)): ?>
                            <?php foreach ($envios as $row): ?>
                                <tr>
                                    <td><?= $indice_envios++ ?></td>
                                    <td><?= htmlspecialchars($row['FATURA']) ?></td>
                                    <td><?= htmlspecialchars($row['CONTATO']) ?></td>
                                    <td><?= htmlspecialchars($row['CELULAR']) ?></td>
                                    <td><?= htmlspecialchars($row['NOME']) ?></td>
                                    <td><?= htmlspecialchars($row['VALOR']) ?></td>
                                    <td><?= htmlspecialchars($row['DATA_VENCIMENTO']) ?></td>
                                    <td><?= htmlspecialchars($row['DATA_ENVIO']) ?></td>
                                    <td><?= htmlspecialchars($row['ERRO']) ?></td>
                                    <td><?= htmlspecialchars($row['MODELO_MENSAGEM']) ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm ver-mensagem"
                                            data-modelo="<?= htmlspecialchars($row['MODELO_MENSAGEM']) ?>"
                                            data-nome="<?= htmlspecialchars($row['NOME']) ?>"
                                            data-fatura="<?= htmlspecialchars($row['FATURA']) ?>"
                                            data-valor="<?= htmlspecialchars($row['VALOR']) ?>"
                                            data-vencimento="<?= htmlspecialchars($row['DATA_VENCIMENTO']) ?>"
                                            data-envio="<?= htmlspecialchars($row['DATA_ENVIO']) ?>"
                                            data-telefone="<?= htmlspecialchars($row['CELULAR']) ?>">
                                            Ver Mensagem
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">Nenhum envio encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tabela de Envios com Falha -->
            <h4>Envios com Falha</h4>

            <form method="POST" action="relistar_todos.php">
                <button type="submit" name="relistar_todos" class="btn btn-warning">Relistar Todos</button>
            </form>
            <br>
            <div class="table-responsive">
                <table class="table table-hover align-middle table-striped">
                    <thead class="table-danger">
                        <tr>
                            <th>#</th>
                            <th>Fatura</th>
                            <th>Contato</th>
                            <th>Celular</th>
                            <th>Nome</th>
                            <th>Valor</th>
                            <th>Data Vencimento</th>
                            <th>Data Envio</th>
                            <th>Erro</th>
                            <th>Modelo Mensagem</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($falhas)): ?>
                            <?php foreach ($falhas as $row): ?>
                                <tr>
                                    <td><?= $indice_falhas++ ?></td>
                                    <td><?= htmlspecialchars($row['FATURA']) ?></td>
                                    <td><?= htmlspecialchars($row['CONTATO']) ?></td>
                                    <td><?= htmlspecialchars($row['CELULAR']) ?></td>
                                    <td><?= htmlspecialchars($row['NOME']) ?></td>
                                    <td><?= htmlspecialchars($row['VALOR']) ?></td>
                                    <td><?= htmlspecialchars($row['DATA_VENCIMENTO']) ?></td>
                                    <td><?= htmlspecialchars($row['DATA_ENVIO']) ?></td>
                                    <td><?= htmlspecialchars($row['ERRO']) ?></td>
                                    <td><?= htmlspecialchars($row['MODELO_MENSAGEM']) ?></td>
                                    <td>
                                        <form action="relistar_fatura.php" method="POST">
                                            <input type="hidden" name="id_falha" value="<?= $row['ID'] ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">🔁 Relistar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">Nenhum envio com falha encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>




            <?php
            $totalPaginas = 50; // Exemplo
            $paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
            $maxLado = 2; // Quantas páginas ao redor da atual serão mostradas

            echo '<div style="display: flex; justify-content: center; margin-top: 20px;">
<ul style="list-style: none; padding: 0; display: flex; gap: 5px;">';

            if ($paginaAtual > 1) {
                echo '<li><a href="?pagina=' . ($paginaAtual - 1) . '" style="padding:8px 12px; background-color:#007bff; color:white; border-radius:5px; text-decoration:none;">←</a></li>';
            }

            for ($i = 1; $i <= $totalPaginas; $i++) {
                if (
                    $i == 1 || $i == $totalPaginas ||
                    ($i >= $paginaAtual - $maxLado && $i <= $paginaAtual + $maxLado)
                ) {
                    if ($i == $paginaAtual) {
                        echo '<li><span style="padding:8px 12px; background-color:#6c757d; color:white; border-radius:5px;">' . $i . '</span></li>';
                    } else {
                        echo '<li><a href="?pagina=' . $i . '" style="padding:8px 12px; background-color:#f8f9fa; border:1px solid #ccc; color:black; border-radius:5px; text-decoration:none;">' . $i . '</a></li>';
                    }
                } elseif (
                    $i == 2 && $paginaAtual - $maxLado > 3 ||
                    $i == $totalPaginas - 1 && $paginaAtual + $maxLado < $totalPaginas - 2 ||
                    ($i == $paginaAtual - $maxLado - 1 || $i == $paginaAtual + $maxLado + 1)
                ) {
                    echo '<li><span style="padding:8px 12px;">...</span></li>';
                }
            }

            if ($paginaAtual < $totalPaginas) {
                echo '<li><a href="?pagina=' . ($paginaAtual + 1) . '" style="padding:8px 12px; background-color:#007bff; color:white; border-radius:5px; text-decoration:none;">→</a></li>';
            }

            echo '</ul></div>';
            ?>


    </div>



    <!-- Paginação -->


    </div>
    <div class="modal fade" id="mensagemModal" tabindex="-1" aria-labelledby="mensagemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content whatsapp-modal">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Mensagem do WhatsApp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="envio-info mb-3 text-muted" id="data-envio-topo"></div>
                    <div id="conteudo-mensagem" class="mensagem-whatsapp"></div>
                </div>
            </div>
        </div>
    </div>



    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll('.ver-mensagem').forEach(button => {
                button.addEventListener('click', () => {
                    const modelo = button.dataset.modelo;
                    const nome = button.dataset.nome;
                    const fatura = button.dataset.fatura;
                    const valor = button.dataset.valor;
                    const vencimento = button.dataset.vencimento;
                    const envio = button.dataset.envio;
                    const telefone = button.dataset.telefone;

                    const formatarData = (dataString) => {
                        const [ano, mes, dia] = dataString.split('T')[0].split('-');
                        return `${dia}/${mes}/${ano}`;
                    };

                    const formatarDataEnvio = (dataHora) => {
                        const dataObj = new Date(dataHora);
                        const dia = String(dataObj.getDate()).padStart(2, '0');
                        const mes = String(dataObj.getMonth() + 1).padStart(2, '0');
                        const ano = dataObj.getFullYear();
                        const hora = String(dataObj.getHours()).padStart(2, '0');
                        const minutos = String(dataObj.getMinutes()).padStart(2, '0');
                        const segundos = String(dataObj.getSeconds()).padStart(2, '0');
                        return `${dia}/${mes}/${ano} ${hora}:${minutos}:${segundos}`;
                    };

                    const dataVenc = new Date(vencimento);
                    const hoje = new Date();

                    // Zerar hora para comparar somente a data
                    dataVenc.setHours(0, 0, 0, 0);
                    hoje.setHours(0, 0, 0, 0);

                    const diff = dataVenc.getTime() - hoje.getTime();
                    const vencida = diff < 0;
                    const venceHoje = diff === 0;

                    document.getElementById('data-envio-topo').innerText =
                        '📤 Enviado em: ' + formatarDataEnvio(envio) + ' para 📞 ' + telefone;

                    fetch('get_modelo_mensagem_wpp.php?modelo=' + encodeURIComponent(modelo))
                        .then(res => res.json())
                        .then(data => {
                            let msg = '';

                            if (data.error) {
                                msg = 'Erro: ' + data.error;
                            } else {
                                const components = data.components ?? [];
                                const bodyComponent = components.find(c => c.type === 'BODY');
                                let templateText = bodyComponent?.text ?? 'Modelo sem corpo de mensagem.';

                                templateText = templateText
                                    .replace('{{1}}', nome)
                                    .replace('{{2}}', fatura)
                                    .replace('{{3}}', valor)
                                    .replace('{{4}}', formatarData(vencimento));
                                
                                msg = templateText;
                            }

                            const conteudoMensagem = document.getElementById('conteudo-mensagem');
                            conteudoMensagem.innerHTML = '';

                            const parts = msg.split('*');
                            parts.forEach((part, index) => {
                                if (index % 2 === 1) {
                                    const strong = document.createElement('strong');
                                    strong.innerText = part;
                                    conteudoMensagem.appendChild(strong);
                                } else {
                                    conteudoMensagem.appendChild(document.createTextNode(part));
                                }
                            });
                            
                            conteudoMensagem.style.whiteSpace = 'pre-wrap';

                            const modal = new bootstrap.Modal(document.getElementById('mensagemModal'));
                            modal.show();
                        })
                        .catch(err => {
                            document.getElementById('conteudo-mensagem').innerHTML = 'Erro ao buscar mensagem.';
                            const modal = new bootstrap.Modal(document.getElementById('mensagemModal'));
                            modal.show();
                        });
                });
            });
        });
    </script>

</body>

</html>