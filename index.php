<?php


session_start();


// Verifica login
if (!isset($_SESSION['user_user'])) {
    header("Location: login.php");
    exit();
}
require_once 'config/conn.php'; // Inclui o arquivo de configuração do banco de dado

// Carrega sessões WPP-Connect
$sessoesWpp = [];

require_once 'api/wpp_api.php'; // ajuste o caminho conforme sua estrutura

$response = get_all_sessions();
/* var_dump($response);
 */

if (isset($response['response'])) {
    $sessoesWpp = $response['response']; // Aqui estão as sessões
} else {
    $sessoesWpp = [];
}




?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Linha do Tempo</title>
    <!-- jQuery e Bootstrap CSS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/html.css">
    <link rel="stylesheet" href="assets/fonts.css">
    <link rel="stylesheet" href="assets/index.css">

</head>

<body>
    <div id="container">

        <div id="titulo">
            <h4><i class="fab fa-whatsapp"></i> SendNow 2.0</h4>
        </div>
        <?php include 'header.php'; ?>
        <div class="conteudos">
            <!-- Seção de Réguas Criadas -->
            <div id="reguas_geral">
                <div id="regua">
                    <div class="reguas">
                        <p>Réguas Criadas</p>
                    </div>
                    <div class="reguas_exibir">
                        <div id="carouselReguas" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php
                                try {
                                    $sql = "SELECT id, nome, intervalo, hora, modelo_mensagem, status, data_criacao,TIPO_DE_MODELO, REGUA_ID, Session_WPP
                                    FROM REGUAS_CRIADAS ORDER BY intervalo ASC";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();

                                    if ($stmt->rowCount() > 0) {
                                        $reguas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        $chunks = array_chunk($reguas, 3);
                                        $isFirst = true;

                                        foreach ($chunks as $grupo) {
                                            echo '<div class="carousel-item ' . ($isFirst ? 'active' : '') . '">';
                                            echo '<div class="row justify-content-center">';
                                            foreach ($grupo as $row) {
                                                $id = $row['id'];
                                                $intervalo = $row['intervalo'];
                                                $status = htmlspecialchars($row['status']);
                                                $data_busca = date('Y-m-d', strtotime("$intervalo days"));
                                                $modelo_wpp = htmlspecialchars($row['modelo_mensagem']);
$session_wpp = htmlspecialchars($row['Session_WPP']);
                                                

                                                // Consulta para contar faturas
                                                $sqlFatura = "SELECT COUNT(*) AS total FROM FATURAS_A_VENCER 
                                                WHERE DATA_VENCIMENTO = :data_busca AND ENVIADO = 'NAO'";
                                                $stmtFatura = $conn->prepare($sqlFatura);
                                                $stmtFatura->bindParam(':data_busca', $data_busca, PDO::PARAM_STR);
                                                $stmtFatura->execute();
                                                $resultado = $stmtFatura->fetch(PDO::FETCH_ASSOC);
                                                $total_faturas = $resultado['total'];

                                                echo '<div class="col-md-4">';
                                                echo '<div class="card mb-4 shadow-sm">';
                                                echo '<div class="card-body">';
                                                echo "<h5 class='card-title'>" . htmlspecialchars($row['nome']) . "</h5>";
                                                echo "<p class='card-text'><strong>Vencimento:</strong> " . htmlspecialchars($intervalo) . " dias</p>";
                                                echo "<p class='card-text'><strong>Hora:</strong> " . htmlspecialchars($row['hora']) . "</p>";
                                                echo "<p class='card-text'><strong>Tipo:</strong> " . htmlspecialchars($row['REGUA_ID']) . "</p>";
                                                echo "<p class='card-text'><strong>Tipo de Modelo:</strong><br>" .
                                                    htmlspecialchars($row['TIPO_DE_MODELO'] ?? '') . "</p>";
                                                $tipo_de_modelo = htmlspecialchars($row['TIPO_DE_MODELO'] ?? '');

                                                if ($tipo_de_modelo == 'Wpp-Connect') {
                                                    $nome_modelo = $modelo_wpp;
                                                } else {
                                                    $nome_modelo = htmlspecialchars($row['modelo_mensagem']);
                                                }

                                                echo "<p class='card-text'><strong>Modelo:</strong><br>" .  $nome_modelo . "</p>";
                                                echo "<p class='card-text'><strong>Status:</strong> " . $status . "</p>";
echo "<p class='card-text'><strong>Session:</strong><br>" .  $session_wpp . "</p>";
                                                echo "<p class='card-text'><strong>Execução:</strong><br>$data_busca</p>";
                                                echo "<p class='card-text'><strong>Afetados:</strong><br>$total_faturas</p>";

                                                // Botões
                                                echo "<button class='btn btn-sm btn-primary me-1' onclick='editarRegua(" . json_encode($id) . ")'>Editar</button> ";
                                                echo "<button class='btn btn-sm btn-danger me-1' onclick='excluirRegua(" . json_encode($id) . ")'>Excluir</button> ";
                                                if ($status === 'Ativo') {
                                                    echo "<button class='btn btn-sm btn-warning' onclick='toggleRegua(" . json_encode($id) . ", " . json_encode('Inativo') . ")'>Desativar</button>";
                                                } else {
                                                    echo "<button class='btn btn-sm btn-success' onclick='toggleRegua(" . json_encode($id) . ", " . json_encode('Ativo') . ")'>Ativar</button>";
                                                }

                                                echo '</div></div></div>'; // Fim da coluna e card
                                            }
                                            echo '</div></div>'; // Fim do carousel-item e row
                                            $isFirst = false;
                                        }
                                    } else {
                                        echo "<div class='text-center p-4'>Nenhuma Régua Criada.</div>";
                                    }
                                } catch (PDOException $e) {
                                    echo "Erro na conexão: " . $e->getMessage();
                                }
                                ?>
                            </div>

                            <!-- Controles -->
                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselReguas" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Anterior</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carouselReguas" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Próximo</span>
                            </button>
                        </div>





                    </div>
                </div>

                <!-- Seção de Criação de Régua -->
                <div id="regua_criar">
                    <div class="reguas">
                        <p>Criar Régua</p>
                    </div>
                    <div class="reguas_exibir_criar">
                        <form id="agendamentoForm" method="post">
                            <input type="text" name="name_regua" id="name_regua" class="form-control mb-2" placeholder="Nome Régua">
                            <select name="Intervalo" id="intervalo" class="form-select mb-2">
                                <option value="">Selecione a data de execução</option>
                                <option value="-62">Atrasado a 62 Dias</option>
                                <option value="-52">Atrasado a 52 Dias</option>
                                <option value="-42">Atrasado a 42 Dias</option>
                                <option value="-30">Atrasado a 30 Dias</option>
                                <option value="-20">Atrasado a 20 Dias</option>
                                <option value="-12">Atrasado a 12 Dias</option>
                                <option value="-5">Atrasado a 5 Dias</option>
                                <option value="0">Hoje</option>
                                <option value="5">Aviso vencimento daqui a 5 Dias</option>
                            </select>

                            <select id="hora" name="Hora" class="form-select mb-1">
                                <option value="">Selecione a hora de execução</option>
                                <?php
                                for ($h = 8; $h <= 18; $h++) {
                                    for ($m = 0; $m < 60; $m += 10) {
                                        $hora = str_pad($h, 2, '0', STR_PAD_LEFT);
                                        $minuto = str_pad($m, 2, '0', STR_PAD_LEFT);
                                        echo "<option value='{$hora}:{$minuto}'>{$hora}:{$minuto}</option>";
                                    }
                                }
                                ?>
                            </select>

                            <select name="tipo" id="tipo" class="form-select mb-1">
                                <option value="">Selecione o tipo da Régua</option>
                                <option value="AVISO">AVISO</option>
                                <option value="COBRANÇA">COBRANÇA</option>
                            </select>

                            <select name="tipo2" id="tipo2" class="form-select mb-1">
                                <option value="">Selecione o Gateway de Envio</option>
                                <option value="360-Dialog">360 Dialog (WhatsApp API Oficial)</option>
                                <option value="Wpp-Connect">Wpp-Connect (LOCAL)</option>
                            </select>

                            <!-- MENSAGEM QUANDO NENHUM GATEWAY ESTIVER SELECIONADO -->
                            <div id="mensagem-aviso-create" style="display: block; color: red; margin-top: 10px;">
                                Selecione um gateway de envio para ver os modelos.
                            </div>

                            <!-- SELECT do 360 Dialog -->
                            <div id="modelo360-create" style="display: none;">
                                <label>Modelo de Mensagem (360 Dialog):</label>
                                <select id="modelo_mensagem02-create" name="modelo_mensagem02" class="form-select mb-1">
                                    <option value="">Selecione o modelo de mensagem 360 Dialog</option>
                                    <?php
                                    if (isset($filteredTemplates) && !empty($filteredTemplates)) {
                                        foreach ($filteredTemplates as $template) {
                                            $name = htmlspecialchars($template['name']);
                                            $text = htmlspecialchars($template['components'][0]['text'] ?? "Sem mensagem disponível");
                                            echo "<option value='$name' data-message='$text'>$name</option>";
                                        }
                                    } else {
                                        echo "<option value=''>Nenhum template encontrado</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- SELECT do WPP Connect -->
                            <div id="modeloWpp-create" style="display: none;">
                                <label>Modelo de Mensagem (WPP-Connect):</label>
                                <select id="modelo_mensagem03-create" name="modelo_mensagem03" class="form-select mb-1">
                                    <option value="">Selecione o modelo de mensagem WPP-Connect Local</option>
                                    <?php
                                    try {
                                        $stmt = $conn->query("SELECT * FROM DBA_MODELOS_MSG ORDER BY id DESC");
                                        $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if ($modelos) {
                                            foreach ($modelos as $modelo) {
                                                $nome = htmlspecialchars($modelo['nome']);
                                                $conteudo = htmlspecialchars($modelo['conteudo']);
                                                echo "<option value=\"$nome\" data-message=\"$conteudo\">$nome</option>";
                                            }
                                        } else {
                                            echo "<option value=''>Nenhum modelo encontrado</option>";
                                        }
                                    } catch (PDOException $e) {
                                        echo "<option value=''>Erro ao buscar modelos: " . htmlspecialchars($e->getMessage()) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- SELECT das sessões do WPP-Connect -->
                            <div id="sessoesWpp-create" style="display: none;">
                                <label>Sessão WPP-Connect:</label>
                                <select id="sessao_wppconnect-create" name="sessao_wppconnect" class="form-select mb-1">
                                    <option value="">Selecione a sessão ativa do WPP</option>
                                    <?php
                                    if (!empty($sessoesWpp)) {
                                        foreach ($sessoesWpp as $sessao) {
                                            $sessionName = htmlspecialchars($sessao);
                                            echo "<option value='$sessionName'>$sessionName</option>";
                                        }
                                    } else {
                                        echo "<option value=''>Nenhuma sessão disponível</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <select name="status" id="status" class="form-select mb-2">
                                <option value="">Selecione o status</option>
                                <option value="ativo">Ativo</option>
                                <option value="Inativo">Inativo</option>
                            </select>

                            <input type="submit" value="gravar" class="btn btn-success">
                        </form>

                        <div id="mensagem"></div>
                        <div id="containerImagem" class="mt-3">
                            <img id="imagemModelo" src="" alt="Pré-visualização do modelo" style="display:none; width:300px;">
                        </div>

                        <!-- Exibição da mensagem do template selecionado -->
                        <h4 class="mt-4">Mensagem do Template</h4>
                        <div id="messageDisplay" class="message-display">
                            Selecione um template para visualizar a mensagem.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Edição -->
        <div id="modalEditar" class="modal" style="display: none;">
            <div class="modal-content p-4 bg-dark rounded">
                <span class="close" onclick="fecharModal()" style="cursor:pointer; float:right;">&times;</span>
                <h4>Editar Régua</h4>
                <form id="formEditar">
                    <input type="hidden" id="edit_id" name="id">
                    <input type="text" name="name_regua01" id="name_regua01" class="form-control mb-2" placeholder="Nome Régua">
                    <label>Intervalo:</label>
                    <select id="edit_intervalo" name="intervalo" class="form-select mb-2">
                        <option value="">Selecione a data de execução</option>
                        <option value="-62">Atrasado a 62 Dias</option>
                        <option value="-52">Atrasado a 52 Dias</option>
                        <option value="-42">Atrasado a 42 Dias</option>
                        <option value="-30">Atrasado a 30 Dias</option>
                        <option value="-20">Atrasado a 20 Dias</option>
                        <option value="-12">Atrasado a 12 Dias</option>
                        <option value="-5">Atrasado a 5 Dias</option>
                        <option value="0">Hoje</option>
                        <option value="5">Aviso vencimento daqui a 5 Dias</option>
                    </select>

                    <label>Hora Execução:</label>
                    <select id="edit_hora" name="hora" class="form-select mb-1">
                        <?php
                        for ($h = 8; $h <= 18; $h++) {
                            for ($m = 0; $m < 60; $m += 10) {
                                $hora = str_pad($h, 2, '0', STR_PAD_LEFT);
                                $minuto = str_pad($m, 2, '0', STR_PAD_LEFT);
                                echo "<option value='{$hora}:{$minuto}'>{$hora}:{$minuto}</option>";
                            }
                        }
                        ?>
                    </select>


                    <label>Selecione o Tipo de Régua:</label>
                    <select name="tipo1" id="tipo1" class="form-select mb-1">
                        <option value="">Selecione o tipo da Régua</option>
                        <option value="AVISO">AVISO</option>
                        <option value="COBRANÇA">COBRANÇA</option>
                    </select>


                    <!-- SELECT do Gateway -->
                    <label for="edit_tipo2">Selecione o Gateway de Envio:</label>
                    <select name="tipo2" id="edit_tipo2" class="form-select mb-1" required>
                        <option value="">Selecione o Gateway de Envio</option>
                        <option value="360-Dialog">360 Dialog (WhatsApp API Oficial)</option>
                        <option value="Wpp-Connect">Wpp-Connect (LOCAL)</option>
                    </select>

                    <!-- MENSAGEM QUANDO NENHUM GATEWAY ESTIVER SELECIONADO -->
                    <div id="mensagem-aviso" style="display: block; color: red; margin-top: 10px;">
                        Selecione um gateway de envio para ver os modelos.
                    </div>

                    <!-- SELECT do 360 Dialog -->
                    <div id="modelo360" style="display: none;">
                        <label>Modelo de Mensagem (360 Dialog):</label>
                        <select id="modelo_mensagem02" name="modelo_mensagem02" class="form-select mb-1">
                            <option value="">Selecione o modelo de mensagem 360 Dialog</option>
                            <?php
                            if (isset($filteredTemplates) && !empty($filteredTemplates)) {
                                foreach ($filteredTemplates as $template) {
                                    $name = htmlspecialchars($template['name']);
                                    $text = htmlspecialchars($template['components'][0]['text'] ?? "Sem mensagem disponível");
                                    echo "<option value='$name' data-message='$text'>$name</option>";
                                }
                            } else {
                                echo "<option value=''>Nenhum template encontrado</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- SELECT do WPP Connect -->
                    <div id="modeloWpp" style="display: none;">
                        <label>Modelo de Mensagem (WPP-Connect):</label>
                        <select id="modelo_mensagem03" name="modelo_mensagem03" class="form-select mb-1">
                            <option value="">Selecione o modelo de mensagem WPP-Connect Local</option>
                            <?php
                            try {
                                $stmt = $conn->query("SELECT * FROM DBA_MODELOS_MSG ORDER BY id DESC");
                                $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if ($modelos) {
                                    foreach ($modelos as $modelo) {
                                        $nome = htmlspecialchars($modelo['nome']);
                                        $conteudo = htmlspecialchars($modelo['conteudo']);
                                        echo "<option value=\"$nome\" data-message=\"$conteudo\">$nome</option>";
                                    }
                                } else {
                                    echo "<option value=''>Nenhum modelo encontrado</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Erro ao buscar modelos: " . htmlspecialchars($e->getMessage()) . "</option>";
                            }
                            ?>
                        </select>
                    </div>



                    <!-- SELECT das sessões do WPP-Connect -->
                    <div id="sessoesWpp" style="display: none;">
                        <label>Sessão WPP-Connect:</label>
                        <select id="sessao_wppconnect" name="sessao_wppconnect" class="form-select mb-1">
                            <option value="">Selecione a sessão ativa do WPP</option>
                            <?php
                            if (!empty($sessoesWpp)) {
                                foreach ($sessoesWpp as $sessao) {
                                    $sessionName = htmlspecialchars($sessao);
                                    echo "<option value='$sessionName'>$sessionName</option>";
                                }
                            } else {
                                echo "<option value=''>Nenhuma sessão disponível</option>";
                            }
                            ?>
                        </select>
                    </div>






                    <label>Status:</label>
                    <select id="edit_status" name="status" class="form-select mb-2">
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>

                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>

                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Envio do formulário de agendamento
        $(document).ready(function() {
            $('#agendamentoForm').submit(function(event) {
                event.preventDefault();
                $.ajax({
                    url: 'add_regua.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        $('#mensagem').html(response);
                        $('#agendamentoForm')[0].reset();
                    },
                    error: function() {
                        $('#mensagem').html("<p style='color:red;'>Erro ao enviar os dados.</p>");
                    }
                });
            });
        });

        function excluirRegua(id) {
            if (confirm("Tem certeza que deseja excluir esta régua?")) {
                $.ajax({
                    url: 'excluir_regua.php',
                    type: 'POST',
                    data: {
                        id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#regua-' + id).fadeOut('slow', function() {
                                $(this).remove();
                            });
                        } else {
                            alert("Erro: " + response.message);
                        }
                    },
                    error: function() {
                        alert("Erro ao tentar excluir a régua.");
                    }
                });
            }
        }

        function editarRegua(id) {
            $.ajax({
                url: 'buscar_regua.php',
                type: 'POST',
                data: {
                    id: id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#edit_id').val(data.id);
                        $('#name_regua01').val(data.nome);
                        $('#edit_intervalo').val(data.intervalo);
                        $('#edit_hora').val(data.hora);
                        $('#tipo1').val(data.REGUA_ID);
                        $('#edit_status').val(data.status.toLowerCase());
                        $('#edit_tipo2').val(data.TIPO_DE_MODELO);

                        // Mostra/esconde campos com base no gateway
                        if (data.TIPO_DE_MODELO === '360-Dialog') {
                            $('#modelo360').show();
                            $('#modeloWpp').hide();
                            $('#sessoesWpp').hide();
                            $('#mensagem-aviso').hide();
                            $('#modelo_mensagem02').val(data.modelo_mensagem);
                        } else if (data.TIPO_DE_MODELO === 'Wpp-Connect') {
                            $('#modelo360').hide();
                            $('#modeloWpp').show();
                            $('#sessoesWpp').show();
                            $('#mensagem-aviso').hide();
                            $('#modelo_mensagem03').val(data.modelo_mensagem);
                        } else {
                            $('#modelo360').hide();
                            $('#modeloWpp').hide();
                            $('#sessoesWpp').hide();
                            $('#mensagem-aviso').show();
                        }

                        $('#modalEditar').fadeIn();
                    } else {
                        alert("Erro: " + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert("Erro ao buscar dados da régua.");
                }
            });
        }

        // ALTERNATIVA: Função para inicializar select quando modal abrir
        $(document).ready(function() {
            // Listener para quando o modal for aberto
            $('#modalEditar').on('shown.bs.modal', function() {
                console.log("Modal totalmente carregado - reinicializando selects");

                // Força refresh em todos os selects do modal
                $(this).find('select').each(function() {
                    const $select = $(this);
                    const valorAtual = $select.val();

                    // Re-aplica o valor após o modal estar visível
                    if (valorAtual) {
                        $select.val(valorAtual);
                        $select[0].dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    }
                });
            });
        });

        // FUNÇÃO DE DEBUG - Use no console para testar
        function debugSelect() {
            const gatewaySelect = $('.modal');

            console.log("=== DEBUG SELECT ===");
            console.log("Select existe:", gatewaySelect.length > 0);
            console.log("Select visível:", gatewaySelect.is(':visible'));
            console.log("Valor atual:", gatewaySelect.val());
            console.log("Opções disponíveis:", gatewaySelect.find('option').map(function() {
                return {
                    value: $(this).val(),
                    text: $(this).text()
                };
            }).get());
            console.log("Opção selecionada:", gatewaySelect.find('option:selected').text());
            console.log("selectedIndex:", gatewaySelect[0].selectedIndex);

            // Testa seleção manual
            const novoValor = 'Wpp-Connect (LOCAL)';
            console.log("\n=== TESTE SELEÇÃO ===");
            console.log("Tentando selecionar:", novoValor);

            gatewaySelect.val(novoValor);
            gatewaySelect[0].dispatchEvent(new Event('change', {
                bubbles: true
            }));

            console.log("Após seleção:", gatewaySelect.val());
            console.log("selectedIndex:", gatewaySelect[0].selectedIndex);
        }

        function fecharModal() {
            $('#modalEditar').fadeOut();
        }

        // Envio do formulário de edição
        $(document).ready(function() {
            $('#formEditar').submit(function(event) {
                event.preventDefault();

                $.ajax({
                    url: 'editar_regua.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        console.log("Resposta AJAX:", response); // Verifique no console

                        // Garante que response.success seja tratado corretamente
                        if (response.success === true || response.success === "true") {
                            alert(response.message);
                            $('#modalEditar').fadeOut();
                            setTimeout(() => location.reload(), 1000); // Pequeno delay antes de recarregar
                        } else {
                            alert("Erro: " + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro AJAX:", status, error);
                        console.error("Resposta do servidor:", xhr.responseText);
                        alert("Erro ao processar a requisição.");
                    }
                });
            });
        });


        // Atualiza a mensagem do template quando o select mudar
        $(document).ready(function() {
            $('#modelo').change(function() {
                var selectedOption = this.options[this.selectedIndex];
                var message = selectedOption.getAttribute('data-message');
                $('#messageDisplay').html(message ? message : 'Sem mensagem para este template.');
            });
        });

        function toggleRegua(id, novoStatus) {
            const acao = novoStatus === 'Ativo' ? 'ativar' : 'desativar';
            if (!confirm(`Tem certeza que deseja ${acao} esta régua?`)) return;
            fetch(`toggle_regua.php?id=${id}&status=${novoStatus}`)
                .then(res => res.json())
                .then(json => {
                    if (json.success) location.reload();
                    else alert('Erro ao atualizar: ' + json.error);
                });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
</body>

</html>
