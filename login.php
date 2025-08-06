<?php

session_start();
require 'config/conn.php'; // conexão com o banco

$erro = ''; // Inicializa

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';



    if (!empty($usuario) && !empty($senha)) {
        $stmt = $conn->prepare("SELECT ID, NOME, USER, PASS, NIVEL FROM DBA_USER_REGUA WHERE USER = ?");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {

            if (password_verify($senha, $user['PASS'])) {
                $_SESSION['user_id'] = $user['ID'];
                $_SESSION['user_nome'] = $user['NOME'];
                $_SESSION['user_user'] = $user['USER'];
                $_SESSION['user_nivel'] = $user['NIVEL'];

                header("Location: index.php");
                exit;
            } else {
                $erro = "Senha incorreta.";
            }
        } else {
            $erro = "Usuário não encontrado.";
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>

<?php

session_start();
require 'config/conn.php'; // conexão com o banco

$erro = ''; // Inicializa

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';



    if (!empty($usuario) && !empty($senha)) {
        $stmt = $conn->prepare("SELECT ID, NOME, USER, PASS, NIVEL FROM DBA_USER_REGUA WHERE USER = ?");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {

            if (password_verify($senha, $user['PASS'])) {
                $_SESSION['user_id'] = $user['ID'];
                $_SESSION['user_nome'] = $user['NOME'];
                $_SESSION['user_user'] = $user['USER'];
                $_SESSION['user_nivel'] = $user['NIVEL'];

                header("Location: index.php");
                exit;
            } else {
                $erro = "Senha incorreta.";
            }
        } else {
            $erro = "Usuário não encontrado.";
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Login - SendNow</title>
    <link rel="stylesheet" href="styles/html.css">
    <link rel="stylesheet" href="assets/fonts.css">
    <link rel="stylesheet" href="assets/login.css">
</head>

<body>
    <div class="container">
        <div class="left-section">
            <h1>SendNow</h1>
        </div>
        <div class="right-section">
            <div class="login-container">
                <h2>Login</h2>
                <form method="POST">
                    <label for="username">Usuário:</label>
                    <input type="text" id="username" name="usuario" placeholder="Digite seu usuário" required>

                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="senha" placeholder="Digite sua senha" required>

                    <button type="submit">Entrar</button>
                </form>

                <?php if (!empty($erro)) echo "<p class='error'>$erro</p>"; ?>
            </div>
        </div>
    </div>
</body>

</html>
