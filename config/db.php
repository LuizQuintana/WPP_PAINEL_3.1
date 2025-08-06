<?php
$host = '127.0.0.1';
$db   = 'REGUA';
$user = 'MAPEAMENTO';
$pass = 'YO*iN2Tg)[v!!w1q';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro ao conectar no banco: " . $e->getMessage());
}
