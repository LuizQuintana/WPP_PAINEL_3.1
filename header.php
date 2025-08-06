<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema WPP-Connect</title>

    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Tema Global -->
    <?php if (!isset($exclude_global_css) || !$exclude_global_css): ?>
    <link rel="stylesheet" href="assets/tema_global.css?v=1.1">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/header.css?v=1.1">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <!-- Botão de Toggle para Mobile -->
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h4 class="sidebar-title">SendNow 2.0</h4>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?= ($currentPage == 'index.php') ? 'active' : '' ?>">
                    <i class="fas fa-home me-2"></i> Início
                </a>
            </li>
            <li class="nav-item">
                <a href="envios.php" class="nav-link <?= ($currentPage == 'envios.php') ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice-dollar me-2"></i> Envios
                </a>
            </li>
            <li class="nav-item">
                <a href="gerenciar_modelos.php" class="nav-link <?= ($currentPage == 'gerenciar_modelos.php') ? 'active' : '' ?>">
                    <i class="fas fa-envelope-open-text me-2"></i> Gerenciar Modelos
                </a>
            </li>
            <li class="nav-item">
                <a href="cron_rules.php" class="nav-link <?= ($currentPage == 'cron_rules.php') ? 'active' : '' ?>">
                    <i class="fas fa-paper-plane me-2"></i> Agendamento de Envio
                </a>
            </li>
            <li class="nav-item">
                <a href="canais.php" class="nav-link <?= ($currentPage == 'canais.php') ? 'active' : '' ?>">
                    <i class="fas fa-paper-plane me-2"></i> Canais
                </a>
            </li>
            <li class="nav-item">
                <a href="respostas.php" class="nav-link <?= ($currentPage == 'respostas.php') ? 'active' : '' ?>">
                    <i class="fas fa-inbox me-2"></i> Caixa de Entrada
                </a>
            </li>
            <li class="nav-item">
                <a href="gerenciar_workflows.php" class="nav-link <?= ($currentPage == 'gerenciar_workflows.php') ? 'active' : '' ?>">
                    <i class="fas fa-cogs me-2"></i> Gerenciar Workflows
                </a>
            </li>
            <li class="nav-item">
                <a href="gerenciar_integracoes.php" class="nav-link <?= ($currentPage == 'gerenciar_integracoes.php') ? 'active' : '' ?>">
                    <i class="fas fa-plug me-2"></i> Gerenciar Integrações
                </a>
            </li>
            <li class="nav-item">
                <a href="sincronizar_sessoes.php" class="nav-link <?= ($currentPage == 'sincronizar_sessoes.php') ? 'active' : '' ?>" target="_blank">
                    <i class="fas fa-sync me-2"></i> Sincronizar Sessões
                </a>
            </li>
            <li class="nav-item">
                <a href="view_logs.php" class="nav-link <?= ($currentPage == 'view_logs.php') ? 'active' : '' ?>">
                    <i class="fas fa-cogs me-2"></i> Gerenciar Logs
                </a>
            </li>
            <li class="nav-item">
                <a href="logs.php" class="nav-link <?= ($currentPage == 'logs.php') ? 'active' : '' ?>">
                    <i class="fas fa-file-alt me-2"></i> Logs
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt me-2"></i> Sair
                </a>
            </li>
        </ul>
    </div>

    <!-- Conteúdo principal -->
    <div class="header-main-content" id="mainContent">
        <!-- O conteúdo da sua página será renderizado aqui -->
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('sidebarToggleBtn');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Opcional: Fechar a sidebar se clicar fora dela em modo mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggleBtn = toggleBtn.contains(event.target);

                    if (!isClickInsideSidebar && !isClickOnToggleBtn && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                }
            });
        });
    </script>

</body>
</html>
