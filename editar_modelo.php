<?php
require_once 'config/conn.php';

if (!isset($_GET['id'])) {
    echo "ID do modelo não fornecido.";
    exit;
}

$id = (int)$_GET['id'];

// Buscar dados existentes
$stmt = $conn->prepare("SELECT * FROM DBA_MODELOS_MSG WHERE id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$modelo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$modelo) {
    echo "Modelo não encontrado.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $conteudo = $_POST['conteudo'] ?? '';

    $update = $conn->prepare("UPDATE DBA_MODELOS_MSG SET nome = :nome, conteudo = :conteudo WHERE id = :id");
    $update->bindParam(':nome', $nome);
    $update->bindParam(':conteudo', $conteudo);
    $update->bindParam(':id', $id, PDO::PARAM_INT);

    if ($update->execute()) {
        header("Location: modelos_criados_locais.php?msg=Modelo atualizado com sucesso! 🚀");
        exit;
    } else {
        $erro = "Erro ao atualizar o modelo.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">

<head>
    <meta charset="UTF-8" />
    <title>Editar Modelo de Mensagem WPP-CONNECT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-900 text-white min-h-screen p-6 font-sans">

    <div class="max-w-3xl mx-auto">
        <h2 class="text-2xl font-bold text-center mb-6 text-teal-400">Editar Modelo de Mensagem WPP-CONNECT</h2>

        <?php include_once 'header.php'; ?>

        <?php if (isset($erro)): ?>
            <div class="bg-red-700 text-white px-4 py-2 rounded mb-4">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-gray-800 p-6 rounded shadow space-y-4">
            <div>
                <label for="nome" class="block font-semibold mb-1">Nome do Modelo:</label>
                <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($modelo['nome']) ?>" required
                    class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-teal-500 text-white">
            </div>

            <div>
                <label for="conteudo" class="block font-semibold mb-1">Texto da Mensagem:</label>
                <textarea id="conteudo" name="conteudo" rows="8" required
                    class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-teal-500 text-white"><?= htmlspecialchars($modelo['conteudo']) ?></textarea>
            </div>

            <div>
                <label class="block font-semibold mb-2">Inserir Variáveis:</label>
                <div class="flex flex-wrap gap-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" onclick="insertVariable('{{<?= $i ?>}}')"
                            class="bg-teal-600 hover:bg-teal-700 px-3 py-1 rounded text-sm font-mono">{{<?= $i ?>}}</button>
                    <?php endfor; ?>
                </div>
            </div>

            <div>
                <label class="block font-semibold mb-2 mt-4">Inserir Emojis:</label>
                <div class="flex flex-wrap gap-2">
                    <?php
                    $emojis = ['😊', '📅', '💰', '⚠️', '✅', '🚀', '🎯'];
                    foreach ($emojis as $emoji):
                    ?>
                        <button type="button" onclick="insertVariable('<?= $emoji ?>')"
                            class="bg-indigo-600 hover:bg-indigo-700 px-3 py-1 rounded text-sm"><?= $emoji ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-between mt-6">
                <button type="submit"
                    class="bg-green-600 hover:bg-green-700 px-5 py-2 rounded text-white font-semibold">
                    <i class="fas fa-save mr-1"></i>Salvar Alterações
                </button>
                <a href="modelos_criados_locais.php"
                    class="bg-gray-600 hover:bg-gray-700 px-5 py-2 rounded text-white font-semibold">
                    <i class="fas fa-arrow-left mr-1"></i>Voltar
                </a>
            </div>
        </form>
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