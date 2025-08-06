<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <title>Criar Modelo de Menu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/html.css">
    <link rel="stylesheet" href="assets/fonts.css">
    <link rel="stylesheet" href="assets/modelos_menu.css">
</head>

<body>
    <?php include 'header.php'; ?>
    <div id="container">
        <h2><i class="fab fa-whatsapp"></i> Criar Modelo de Menu</h2>

        <?php if (isset($_GET['msg'])) : ?>
            <div class="msg">
                <?= htmlspecialchars($_GET['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="form-container-centralized">
            <form id="template-form" method="POST" action="salvar_modelo_menu.php">
                <label for="nome">Nome do Menu:</label>
                <input type="text" id="nome" name="nome" required>

                <label>Menu Interativo:</label>
                <div id="menu-construtor">
                    <!-- Itens vão aqui -->
                </div>
                <button type="button" class="btn btn-primary mt-2" onclick="adicionarItemMenu()">+ Adicionar Item</button>
                <button type="button" class="btn btn-secondary mt-2" onclick="gerarMenu()">📋 Inserir Menu na Mensagem</button>

                <label for="conteudo">Texto da Mensagem:</label>
                <textarea id="conteudo" name="conteudo" rows="8" placeholder="Seu menu aparecerá aqui" required></textarea>

                <button type="submit" class="btn btn-success mt-3"><i class="fas fa-save"></i> Salvar Menu</button>
            </form>
        </div>
    </div>

    <script>
        let contadorMenu = 1;

        function adicionarItemMenu() {
            const container = document.getElementById('menu-construtor');

            const div = document.createElement('div');
            div.classList.add('menu-item');
            div.innerHTML = `
                <input type="text" placeholder="Texto do item ${contadorMenu}" class="item-text" />
                <button type="button" onclick="this.parentElement.remove()">❌</button>
            `;
            container.appendChild(div);
            contadorMenu++;
        }

        function gerarMenu() {
            const itens = document.querySelectorAll('.menu-item .item-text');
            let menuText = '';

            itens.forEach((input, index) => {
                const emojiNumero = (index + 1) + '️⃣';
                const texto = input.value.trim();
                if (texto) {
                    menuText += `${emojiNumero} ${texto}\n`;
                }
            });

            const textarea = document.getElementById('conteudo');
            textarea.value = menuText.trim();
        }
    </script>
</body>

</html>