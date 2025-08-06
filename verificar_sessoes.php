<?php
// verificar_sessoes.php
session_start();

// Verifica login
if (!isset($_SESSION['user_user'])) {
    header("Location: login.php");
    exit();
}

// Configuração da API local do WPP-Connect
$WPP_CONNECT_URL = "http://localhost:21465";

// Função para buscar sessões
function buscarSessoes()
{
    global $WPP_CONNECT_URL;

    $apiUrl = $WPP_CONNECT_URL . "/api/default/show-all-sessions";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        return $data['response'] ?? [];
    }
    return [];
}

// Função para verificar status específico de uma sessão
function verificarStatusSessao($sessao)
{
    global $WPP_CONNECT_URL;

    $apiUrl = $WPP_CONNECT_URL . "/api/$sessao/check-connection-session";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        return $data['response'] ?? false;
    }
    return false;
}

$sessoes = buscarSessoes();

if (empty($sessoes)): ?>
    <div class="alert alert-warning">
        <h6>Nenhuma sessão encontrada</h6>
        <p class="mb-0">Verifique se o WPP-Connect está rodando em: <code><?php echo $WPP_CONNECT_URL; ?></code></p>
        <small class="text-muted">
            Para criar uma nova sessão, acesse: <a href="<?php echo $WPP_CONNECT_URL; ?>" target="_blank"><?php echo $WPP_CONNECT_URL; ?></a>
        </small>
    </div>
<?php else: ?>
    <?php foreach ($sessoes as $sessao): ?>
        <div class="session-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">
                        <i class="fas fa-mobile-alt"></i>
                        <?php echo htmlspecialchars($sessao['session'] ?? 'Sessão'); ?>
                    </h6>
                    <small class="text-muted">
                        Status:
                        <span class="status-<?php echo ($sessao['state'] === 'CONNECTED') ? 'online' : 'offline'; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo htmlspecialchars($sessao['state'] ?? 'Desconhecido'); ?>
                        </span>
                    </small>
                    <?php if (isset($sessao['phone'])): ?>
                        <br><small class="text-muted">Telefone: <?php echo htmlspecialchars($sessao['phone']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-column align-items-end">
                    <?php if (($sessao['state'] ?? '') === 'CONNECTED'): ?>
                        <span class="badge bg-success mb-1">
                            <i class="fas fa-check-circle"></i> Online
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger mb-1">
                            <i class="fas fa-times-circle"></i> Offline
                        </span>
                    <?php endif; ?>

                    <div class="btn-group-sm">
                        <button class="btn btn-sm btn-outline-primary" onclick="verificarSessao('<?php echo $sessao['session']; ?>')">
                            <i class="fas fa-sync"></i> Verificar
                        </button>
                        <?php if (($sessao['state'] ?? '') !== 'CONNECTED'): ?>
                            <button class="btn btn-sm btn-outline-success" onclick="iniciarSessao('<?php echo $sessao['session']; ?>')">
                                <i class="fas fa-play"></i> Iniciar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="mt-3">
        <button class="btn btn-primary" onclick="criarNovaSessao()">
            <i class="fas fa-plus"></i> Criar Nova Sessão
        </button>
    </div>
<?php endif; ?>

<script>
    function verificarSessao(sessionName) {
        // Implementar verificação individual da sessão
        $.ajax({
            url: 'verificar_sessao_individual.php',
            type: 'POST',
            data: {
                session: sessionName
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Sessão ' + sessionName + ' está ' + response.status);
                    verificarSessoes(); // Recarregar lista
                } else {
                    alert('Erro ao verificar sessão: ' + response.message);
                }
            },
            error: function() {
                alert('Erro ao verificar sessão');
            }
        });
    }

    function iniciarSessao(sessionName) {
        if (confirm('Deseja iniciar a sessão ' + sessionName + '?')) {
            $.ajax({
                url: 'iniciar_sessao.php',
                type: 'POST',
                data: {
                    session: sessionName
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Sessão iniciada com sucesso!');
                        verificarSessoes(); // Recarregar lista
                    } else {
                        alert('Erro ao iniciar sessão: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao iniciar sessão');
                }
            });
        }
    }

    function criarNovaSessao() {
        var sessionName = prompt('Digite o nome da nova sessão:');
        if (sessionName && sessionName.trim() !== '') {
            $.ajax({
                url: 'criar_sessao.php',
                type: 'POST',
                data: {
                    session: sessionName.trim()
                },
                dataType: 'json',
                beforeSend: function() {
                    // Mostrar loading
                    $('body').append('<div id="loading" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; justify-content: center; align-items: center; color: white;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
                },
                complete: function() {
                    // Remover loading
                    $('#loading').remove();
                },
                success: function(response) {
                    if (response.success) {
                        alert('Sessão criada com sucesso!');
                        verificarSessoes(); // Recarregar lista
                        // Se retornar QR Code, mostrar para escaneamento
                        if (response.qrcode) {
                            mostrarQRCode(response.qrcode, sessionName);
                        }
                    } else {
                        alert('Erro ao criar sessão: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao criar sessão');
                }
            });
        }
    }

    function mostrarQRCode(qrcode, sessionName) {
        // Criar modal para mostrar QR Code
        var modal = `
        <div class="modal fade" id="qrCodeModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">QR Code - Sessão: ${sessionName}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body text-center">
                        <p>Escaneie este QR Code com seu WhatsApp:</p>
                        <img src="${qrcode}" alt="QR Code" class="img-fluid" style="max-width: 300px;">
                        <p class="mt-2"><small class="text-muted">O QR Code expira em alguns minutos</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="verificarSessao('${sessionName}')">
                            <i class="fas fa-sync"></i> Verificar Conexão
                        </button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

        // Adicionar modal ao body e mostrar
        $('body').append(modal);
        $('#qrCodeModal').modal('show');

        // Remover modal quando fechar
        $('#qrCodeModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    }

    function verificarSessoes() {
        // Recarregar a página ou fazer requisição AJAX para atualizar a lista
        location.reload();
    }

    // Auto-refresh das sessões a cada 30 segundos
    setInterval(function() {
        verificarSessoes();
    }, 30000);
</script>

<style>
    .session-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        background-color: #f8f9fa;
    }

    .status-online {
        color: #28a745;
    }

    .status-offline {
        color: #dc3545;
    }

    .btn-group-sm .btn {
        margin-left: 5px;
    }

    #loading {
        font-family: Arial, sans-serif;
    }
</style>