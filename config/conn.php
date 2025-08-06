<?php

$servidor = "127.0.0.1";
$usuario = "MAPEAMENTO";
$senha = "YO*iN2Tg)[v!!w1q";
$dbname = "REGUA";

try {
    // Criar a conexão PDO
    $conn = new PDO("mysql:host=$servidor;dbname=$dbname", $usuario, $senha);

    // Definir o modo de erro do PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //echo "Conexão bem-sucedida!";
} catch (PDOException $e) {
    die("Falha na conexão: " . $e->getMessage());
}
