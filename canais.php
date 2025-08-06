<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/api/wpp_api.php';

$message = '';
$response = null;
$qrcodeData = null;
$session = '';

function logout_all_sessions()
{
    $sessions = get_saved_sessions();
    $results = [];

    foreach ($sessions as $sessName) {
        $token = get_token($sessName);
        if ($token) {
            $res = terminate_session($sessName); // usa a nova versão
            $results[$sessName] = $res;
        } else {
            $results[$sessName] = ['status' => 'error', 'message' => 'Token não encontrado'];
        }
    }

    return $results;
}


// Ações
try {
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'logout_all') {
            $results = logout_all_sessions();
            $message = "Logout realizado em todas as sessões.";
            $response = $results;
        } elseif (isset($_GET['session'])) {
            $session = $_GET['session'];
            switch ($_GET['action']) {
                case 'generate_token':
                    $response = generate_token($session);
                    if (isset($response['token'])) {
                        $message = "Token gerado para sessão '{$session}'.";
                    } else {
                        $message = "Erro ao gerar token para sessão '{$session}'.";
                        // Ativa o modo debug para esta resposta específica
                        $_GET['debug'] = true;
                    }
                    break;
                case 'start_session':
                    $response = start_session($session);
                    $message = "Sessão '{$session}' iniciada.";
                    break;
                case 'logout':
                    $response = terminate_session($session);
                    if (
                        isset($response['close']['status']) && $response['close']['status'] === 'success' &&
                        isset($response['logout']['status']) && $response['logout']['status'] === 'success'
                    ) {
                        $message = "Sessão '{$session}' desconectada com sucesso.";
                    } else {
                        $message = "Erro ao desconectar a sessão '{$session}'.";
                    }
                    break;

                case 'qrcode':
                    $qrcodeData = get_qrcode($session);
                    $message = "QR Code da sessão '{$session}' obtido.";
                    break;
                case 'status':
                    $response = get_session_status($session);
                    $message = "Status da sessão '{$session}' obtido.";
                    break;
                case 'delete':
                    $response = delete_session($session);
                    $message = "Sessão '{$session}' excluída.";
                    break;
            }
        }
    }
} catch (Exception $e) {
    $message = "Erro ao processar: " . $e->getMessage();
}

// AJAX para QR Code
if (isset($_GET['ajax']) && $_GET['ajax'] === 'qrcode' && isset($_GET['session'])) {
    $qrcodeData = get_qrcode($_GET['session']);
    header('Content-Type: application/json');
    echo json_encode($qrcodeData);
    exit;
}

// AJAX para Start
if (isset($_GET['ajax']) && $_GET['ajax'] === 'start' && isset($_GET['session'])) {
    $response = start_session($_GET['session']);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// AJAX para Status
if (isset($_GET['ajax']) && $_GET['ajax'] === 'status' && isset($_GET['session'])) {
    $statusData = get_session_status($_GET['session']);
    header('Content-Type: application/json');
    echo json_encode($statusData);
    exit;
}

$savedSessions = get_saved_sessions();
$activeSessions = get_all_sessions();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel WPPConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/canais.css">
</head>

<body>
    <div id="container">
        <?php include 'header.php'; ?>
        <div class="main-content">
            <div class="container">


                <div class="content">
                    <?php if ($message): ?>
                        <div class="message <?= (isset($response['status']) && $response['status'] === 'error') ? 'error' : '' ?>">
                            <i class="fas fa-info-circle"></i>
                            <?= htmlspecialchars($message) ?>
                            <?php if ($response && isset($_GET['debug'])): ?>
                                <div class="debug">
                                    <h4>Resposta da API:</h4>
                                    <pre><?= htmlspecialchars(print_r($response, true)) ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="section">
                        <h2>
                            <i class="fas fa-cogs"></i>
                            Gerenciamento de Sessões
                        </h2>

                        <div style="margin-bottom: 20px;">
                            <a href="?action=logout_all" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja fazer logout de todas as sessões?')">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout de Todas as Sessões
                            </a>
                        </div>

                        <?php if (!empty($savedSessions)): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-tag"></i> Nome da Sessão</th>
                                        <th><i class="fas fa-signal"></i> Status</th>
                                        <th><i class="fas fa-key"></i> Token</th>
                                        <th><i class="fas fa-tools"></i> Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($savedSessions as $sess): ?>
                                        <?php
                                        $status = 'Não iniciada';
                                        $statusClass = 'status-unknown';

                                        // Verificar status através da API
                                        if (get_token($sess)) {
                                            $sessionStatus = get_session_status($sess);
                                            if (isset($sessionStatus['status'])) {
                                                $status = $sessionStatus['status'];
                                                $statusClass = $sessionStatus['status'] === 'connected' ? 'status-connected' : 'status-disconnected';
                                            }
                                        }

                                        $hasToken = get_token($sess) ? 'Sim' : 'Não';
                                        ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-mobile-alt"></i>
                                                <?= htmlspecialchars($sess) ?>
                                            </td>
                                            <td>
                                                <span id="status-text-<?= htmlspecialchars($sess) ?>" class="status-badge <?= $statusClass ?>">
                                                    <?= htmlspecialchars($status) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= $hasToken === 'Sim' ? 'status-connected' : 'status-disconnected' ?>">
                                                    <?= $hasToken ?>
                                                </span>
                                            </td>
                                            <td class="actions">
                                                <?php if (!get_token($sess)): ?>
                                                    <a href="?action=generate_token&session=<?= urlencode($sess) ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-key"></i> Gerar Token
                                                    </a>
                                                <?php else: ?>
                                                    <button onclick="initiateAndShowQRCode('<?= urlencode($sess) ?>')" id="btn-connect-<?= urlencode($sess) ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-qrcode"></i> Conectar
                                                    </button>
                                                    <a href="?action=status&session=<?= urlencode($sess) ?>" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-info"></i> Status
                                                    </a>
                                                    <a href="?action=logout&session=<?= urlencode($sess) ?>" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-sign-out-alt"></i> Logout
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?action=delete&session=<?= urlencode($sess) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir a sessão '<?= htmlspecialchars($sess) ?>'?')">
                                                    <i class="fas fa-trash"></i> Excluir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>Nenhuma sessão encontrada</h3>
                                <p>Crie sua primeira sessão para começar</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="section">
                        <h2>
                            <i class="fas fa-plus-circle"></i>
                            Criar Nova Sessão
                        </h2>
                        <div class="form-container">
                            <form method="get">
                                <div class="form-group">
                                    <input type="hidden" name="action" value="generate_token">
                                    <input type="text" name="session" placeholder="Digite o nome da sessão" required>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-plus"></i>
                                        Criar Sessão
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (isset($_GET['debug'])): ?>
                        <div class="debug">
                            <h4><i class="fas fa-bug"></i> Informações de Debug:</h4>
                            <p><strong>Sessões Salvas:</strong> <?= count($savedSessions) ?></p>
                            <p><strong>Diretório Storage:</strong> <?= is_dir(__DIR__ . '/../storage') ? 'Existe' : 'Não existe' ?></p>
                            <p><strong>Arquivo tokens.json:</strong> <?= file_exists(__DIR__ . '/../storage/tokens.json') ? 'Existe' : 'Não existe' ?></p>

                            <?php if (isset($activeSessions)): ?>
                                <h5>Sessões Ativas (API):</h5>
                                <pre><?= htmlspecialchars(print_r($activeSessions, true)) ?></pre>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <script>
        // Função para iniciar sessão e exibir QR Code
        async function initiateAndShowQRCode(sessionName) {
            const button = document.getElementById(`btn-connect-${sessionName}`);
            const statusElement = document.getElementById(`status-text-${sessionName}`);

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Conectando...';

            try {
                // 1. Inicia a sessão
                const startResponse = await fetch(`?ajax=start&session=${encodeURIComponent(sessionName)}`);
                const startData = await startResponse.json();

                if (startData.status === 'error') {
                    throw new Error(startData.message || 'Erro ao iniciar sessão');
                }

                // 2. Exibe o modal imediatamente com mensagem de carregando
                showQRCodeModal(sessionName, null); // ainda sem o QR

                // 3. Tenta obter o QR Code até ele estar disponível
                let attempts = 0;
                let qrData = null;

                while (attempts < 10) {
                    const qrResponse = await fetch(`?ajax=qrcode&session=${encodeURIComponent(sessionName)}`);
                    const data = await qrResponse.json();

                    if (data.qrcode) {
                        qrData = data.qrcode;
                        break;
                    }

                    await new Promise(r => setTimeout(r, 2000)); // espera 2s
                    attempts++;
                }

                if (!qrData) {
                    throw new Error("QR Code não disponível após várias tentativas.");
                }

                // 4. Atualiza o QR Code no modal
                updateQRCodeInModal(qrData);

                // 5. Inicia o monitoramento de status
                startStatusMonitoring(sessionName);

            } catch (error) {
                console.error('Erro ao conectar:', error);
                showErrorMessage('Erro ao conectar: ' + error.message);
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-qrcode"></i> Conectar';
                closeQRModal();
            }
        }

        function updateQRCodeInModal(qrData) {
            const qrImage = document.querySelector('.qr-image');
            if (qrImage) {
                qrImage.src = qrData;
            }
        }

        // Função para exibir QR Code em modal
        function showQRCodeModal(sessionName, qrCodeData) {
            const existingModal = document.getElementById('qr-modal');
            if (existingModal) existingModal.remove();

            const modal = document.createElement('div');
            modal.id = 'qr-modal';
            modal.className = 'modal';
            modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-qrcode"></i> QR Code - ${sessionName}</h3>
                <span class="close" onclick="closeQRModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="qr-container">
                    <img src="${qrCodeData || ''}" alt="QR Code" class="qr-image">
                    <p class="qr-instructions">
                        <i class="fas fa-mobile-alt"></i>
                        Escaneie este código QR com seu WhatsApp
                    </p>
                    <div class="status-indicator">
                        <i class="fas fa-spinner fa-spin"></i> Aguardando conexão...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeQRModal()">
                    <i class="fas fa-times"></i> Fechar
                </button>
                <button class="btn btn-primary" onclick="refreshQRCode('${sessionName}')">
                    <i class="fas fa-sync"></i> Atualizar QR
                </button>
            </div>
        </div>
    `;

            document.body.appendChild(modal);
            modal.style.display = 'block';

            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeQRModal();
                }
            });
        }


        // Função para fechar o modal do QR Code
        function closeQRModal() {
            console.log("closeQRModal called.");
            const modal = document.getElementById('qr-modal');
            if (modal) {
                modal.remove();
            }

            // Para o monitoramento de status
            if (window.statusMonitorInterval) {
                clearInterval(window.statusMonitorInterval);
                window.statusMonitorInterval = null;
            }
        }

        // Função para atualizar o QR Code
        async function refreshQRCode(sessionName) {
            const qrImage = document.querySelector('.qr-image');
            const statusIndicator = document.querySelector('.status-indicator');

            if (qrImage && statusIndicator) {
                statusIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando QR Code...';

                try {
                    const response = await fetch(`?ajax=qrcode&session=${encodeURIComponent(sessionName)}`);
                    const data = await response.json();

                    if (data.status === 'success' && data.qrcode) {
                        qrImage.src = data.qrcode;
                        statusIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aguardando conexão...';
                    } else {
                        statusIndicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao atualizar QR Code';
                    }
                } catch (error) {
                    statusIndicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao atualizar QR Code';
                }
            }
        }

        // Função para monitorar o status da sessão
        function startStatusMonitoring(sessionName) {
            // Para qualquer monitoramento anterior
            if (window.statusMonitorInterval) {
                clearInterval(window.statusMonitorInterval);
            }

            window.statusMonitorInterval = setInterval(async () => {
                try {
                    const response = await fetch(`?ajax=status&session=${encodeURIComponent(sessionName)}`);
                    const data = await response.json();

                    // Atualiza o status na tabela
                    const statusElement = document.getElementById(`status-text-${sessionName}`);
                    if (statusElement && data.status) {
                        console.log("Status recebido do backend para " + sessionName + ": ", data.status);
                        updateStatusDisplay(statusElement, data.status);
                    }

                    // Se conectou, fecha o modal e para o monitoramento
                    if (data.status === 'CONNECTED' || data.status === 'connected' || data.status.includes('qr_read_success')) {
                        showSuccessMessage(`Sessão '${sessionName}' conectada com sucesso!`);
                        closeQRModal();

                        // Restaura botão
                        const button = document.getElementById(`btn-connect-${sessionName}`);
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = '<i class="fas fa-qrcode"></i> Conectar';
                        }
                    }


                } catch (error) {
                    console.error('Erro ao verificar status:', error);
                }
            }, 3000); // Verifica a cada 3 segundos
        }

        // Função para atualizar a exibição do status
        function updateStatusDisplay(element, status) {
            // Remove classes anteriores
            element.classList.remove('status-connected', 'status-disconnected', 'status-unknown');

            // Adiciona nova classe e texto
            switch (status) {
                case 'connected':
                    element.classList.add('status-connected');
                    element.textContent = 'Conectado';
                    break;
                case 'disconnected':
                    element.classList.add('status-disconnected');
                    element.textContent = 'Desconectado';
                    break;
                default:
                    element.classList.add('status-unknown');
                    element.textContent = status || 'Desconhecido';
            }
        }

        // Função para exibir mensagem de sucesso
        function showSuccessMessage(message) {
            showMessage(message, 'success');
        }

        // Função para exibir mensagem de erro
        function showErrorMessage(message) {
            showMessage(message, 'error');
        }

        // Função genérica para exibir mensagens
        function showMessage(message, type = 'info') {
            // Remove mensagem anterior se existir
            const existingMessage = document.querySelector('.floating-message');
            if (existingMessage) {
                existingMessage.remove();
            }

            // Cria a mensagem
            const messageEl = document.createElement('div');
            messageEl.className = `floating-message ${type}`;
            messageEl.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
        <span class="close-message" onclick="this.parentElement.remove()">&times;</span>
    `;

            document.body.appendChild(messageEl);

            // Remove automaticamente após 5 segundos
            setTimeout(() => {
                if (messageEl.parentElement) {
                    messageEl.remove();
                }
            }, 5000);
        }

        // Função para atualizar todos os status
        async function updateAllStatuses() {
            const statusElements = document.querySelectorAll('[id^="status-text-"]');

            for (const element of statusElements) {
                const sessionName = element.id.replace('status-text-', '');

                try {
                    const response = await fetch(`?ajax=status&session=${encodeURIComponent(sessionName)}`);
                    const data = await response.json();

                    if (data.status) {
                        updateStatusDisplay(element, data.status);
                    }
                } catch (error) {
                    console.error(`Erro ao atualizar status de ${sessionName}:`, error);
                }
            }
        }

        // Inicialização quando a página carrega
        document.addEventListener('DOMContentLoaded', function() {
            // Atualiza todos os status quando a página carrega
            updateAllStatuses();

            // Atualiza status a cada 30 segundos
            setInterval(updateAllStatuses, 30000);
        });

        // Estilos CSS para o modal e mensagens (adicione ao seu CSS)
        const styles = `
<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: none;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
    background-color: #25D366;
    color: white;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: white;
    opacity: 0.8;
}

.close:hover {
    opacity: 1;
}

.modal-body {
    padding: 20px;
}

.qr-container {
    text-align: center;
}

.qr-image {
    max-width: 100%;
    height: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 15px;
}

.qr-instructions {
    color: #666;
    font-size: 14px;
    margin-bottom: 15px;
}

.status-indicator {
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
    color: #666;
    font-size: 14px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    text-align: right;
}

.modal-footer .btn {
    margin-left: 10px;
}

.floating-message {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 4px;
    color: white;
    font-weight: bold;
    z-index: 1001;
    min-width: 300px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.floating-message.success {
    background-color: #28a745;
}

.floating-message.error {
    background-color: #dc3545;
}

.floating-message.info {
    background-color: #17a2b8;
}

.close-message {
    font-size: 20px;
    cursor: pointer;
    opacity: 0.8;
    margin-left: 10px;
}

.close-message:hover {
    opacity: 1;
}

.floating-message i {
    margin-right: 10px;
}
</style>
`;

        // Adiciona os estilos ao documento
        if (!document.querySelector('#wpp-modal-styles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'wpp-modal-styles';
            styleSheet.innerHTML = styles.replace('<style>', '').replace('</style>', '');
            document.head.appendChild(styleSheet);
        }
    </script>