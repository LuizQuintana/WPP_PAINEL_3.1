<?php
require "config/conn.php"; // Conexão com banco

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Captura e debug dos valores recebidos
        /*         var_dump($_POST); // DEBUG: Veja se os valores estão chegando
        echo "<br>"; */

        // Captura os dados do formulário
        $nome_regua = $_POST['name_regua']; // Nome correto
        $intervalo = $_POST['Intervalo']; // Nome correto
        $hora = $_POST['Hora']; // Nome correto
        $modelo = $_POST['Modelo_de_mensagem']; // Nome correto
        $status = $_POST['status']; // Nome correto
        $REGUA_ID = $_POST['tipo']; // Nome correto

        /*         echo $nome_regua;
 */
        if (
            !isset($_POST['name_regua']) || trim($_POST['name_regua']) === '' ||
            !isset($_POST['Intervalo']) || trim($_POST['Intervalo']) === '' ||
            !isset($_POST['Hora']) || trim($_POST['Hora']) === '' ||
            !isset($_POST['Modelo_de_mensagem']) || trim($_POST['Modelo_de_mensagem']) === '' ||
            !isset($_POST['status']) || trim($_POST['status']) === '' ||
            !isset($_POST['tipo']) || trim($_POST['tipo']) === ''
        ) {
            echo "Erro: Preencha todos os campos!";
        }


        // Prepara a query SQL
        $sql = "INSERT INTO REGUAS_CRIADAS (nome, intervalo, hora, modelo_mensagem, status, REGUA_ID) VALUES (:nome, :intervalo, :hora, :modelo, :status, :tipo)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":nome", $nome_regua, PDO::PARAM_STR);
        $stmt->bindParam(":intervalo", $intervalo, PDO::PARAM_INT);
        $stmt->bindParam(":hora", $hora, PDO::PARAM_STR);
        $stmt->bindParam(":modelo", $modelo, PDO::PARAM_STR);
        $stmt->bindParam(":status", $status, PDO::PARAM_STR);
        $stmt->bindParam(":tipo", $REGUA_ID, PDO::PARAM_STR);

        // Executa e verifica se deu certo
        if ($stmt->execute()) {
            echo "<p style='color:green;'>Agendamento salvo com sucesso!</p>";
        } else {
            echo "<p style='color:red;'>Erro ao salvar no banco!</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red;'>Erro SQL: " . $e->getMessage() . "</p>";
    }
}
