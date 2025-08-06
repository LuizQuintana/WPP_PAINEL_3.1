<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <title>Criar Modelo de Mensagem</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modelos.css">
</head>

<body>
    <div id="container">
        <div id="titulo">
            <h4><i class="fab fa-whatsapp"></i> SendNow 2.0</h4>
        </div>
        <?php include 'header.php'; ?>
        <div class="main-content">
            <h2>
                <center>Criar Modelo de Mensagem</center>
            </h2>

            <?php if (isset($_GET['msg'])) : ?>
                <div class="msg">
                    <?= htmlspecialchars($_GET['msg']) ?>
                </div>
            <?php endif; ?>

            <div class="form-container-centralized">
                <form id="template-form" method="POST" action="salvar_modelo.php">
                    <label for="nome">Nome do Modelo:</label>
                    <input type="text" id="nome" name="nome" required>

                    <label for="conteudo">Texto da Mensagem:</label>
                    <textarea id="conteudo" name="conteudo" rows="8" placeholder="Exemplo: Olá {1}, sua fatura {2} no valor de {3} vence em {4}."></textarea>

                    <div class="variable-buttons">
                        <label>Inserir Variáveis:</label><br>
                        <button type="button" onclick="insertVariable('{{1}}')">{{1}}</button>
                        <button type="button" onclick="insertVariable('{{2}}')">{{2}}</button>
                        <button type="button" onclick="insertVariable('{{3}}')">{{3}}</button>
                        <button type="button" onclick="insertVariable('{{4}}')">{{4}}</button>
                        <button type="button" onclick="insertVariable('{{5}}')">{{5}}</button>
                    </div>

                    <div class="emoji-buttons" style="margin-top: 10px;">
                        <label>Inserir Emojis:</label><br>
                        <button type="button" onclick="insertVariable('😊')">😊</button>
                        <button type="button" onclick="insertVariable('📅')">📅</button>
                        <button type="button" onclick="insertVariable('💰')">💰</button>
                        <button type="button" onclick="insertVariable('⚠️')">⚠️</button>
                        <button type="button" onclick="insertVariable('✅')">✅</button>
                        <button type="button" onclick="insertVariable('🚀')">🚀</button>
                        <button type="button" onclick="insertVariable('🎯')">🎯</button>
                    </div>

                    <button type="submit"><i class="fas fa-save"></i> Salvar Modelo</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function insertVariable(variable) {
            const textarea = document.getElementById('conteudo');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;

            textarea.value = text.slice(0, start) + variable + text.slice(end);
            textarea.selectionStart = textarea.selectionEnd = start + variable.length;
            textarea.focus();
        }
    </script>

</body>

</html>