<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once "return_tokem.php"; // Inclui o token
include_once "conn.php";         // Inclui a conexão com o banco de dados

// Configurações da API
$api_url = "http://138.118.247.66:8080/mk/WSMKFaturasAbertas.rule?sys=MK0";

// Verifica se o token foi retornado corretamente
if (empty($tokem_retornado)) {
    echo "Erro: Token não encontrado!";
    exit;
}

// Consulta todas as réguas ativas
try {
    $sql = "SELECT `id`, `intervalo`, `hora`, `modelo_mensagem`, `status`, `REGUA_ID`, `data_criacao` 
            FROM `REGUAS_CRIADAS` WHERE status = 'ATIVO'";
    $stmt = $conn->query($sql);

    if ($stmt->rowCount() > 0) {
        // Processa cada régua ativa
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $intervalo  = $row['intervalo'];      // Pode ser positivo (fatura a vencer) ou negativo (fatura atrasada)
            $hora       = $row['hora'];           // Hora para ajuste, se necessário
            $regua_id   = $row['REGUA_ID'];
            $modeloMsg  = $row['modelo_mensagem'];
            $dataCriacao = $row['data_criacao'];


            // Verifica se a hora atual é uma hora antes da hora da régua
            $horaAtual = new DateTime();
            echo "Hora atual do sistema: " . $horaAtual->format('H:i') . "<br>";

            // Verifica o formato da hora e cria o objeto DateTime corretamente
            if (!empty($hora)) {
                // Determina o formato correto da hora baseado no conteúdo
                if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
                    $horaRegua = DateTime::createFromFormat('H:i', $hora);
                } else if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
                    $horaRegua = DateTime::createFromFormat('H:i:s', $hora);
                } else {
                    echo "Regua {$regua_id}: Formato de hora não reconhecido ({$hora})<br>";
                    continue;
                }

                if ($horaRegua === false) {
                    echo "Regua {$regua_id}: Erro ao processar hora ({$hora})<br>";
                    continue;
                }

                // Cria uma cópia da hora da régua e subtrai 1 hora
                $horaLimiteExecucao = clone $horaRegua;
                $horaLimiteExecucao->modify('-1 hour');

                // Compara apenas horas e minutos
                if ($horaAtual->format('H:i') != $horaLimiteExecucao->format('H:i')) {
                    echo "Regua {$regua_id}: Aguardando horário de execução (" .
                        $horaLimiteExecucao->format('H:i') . " - uma hora antes de " .
                        $horaRegua->format('H:i') . ")<br>";
                    continue;
                }

                echo "Regua {$regua_id}: Executando uma hora antes do horário programado (" .
                    $horaLimiteExecucao->format('H:i') . " para régua das " .
                    $horaRegua->format('H:i') . ")<br>";
            } else {
                echo "Regua {$regua_id}: Valor de hora vazio<br>";
                continue;
            }

            // Calcula a data de vencimento baseada no intervalo da régua.
            // Exemplo: Se o intervalo é 5, pega a data de hoje + 5 dias.
            // Se o intervalo for negativo, será uma fatura atrasada.
            $dataFatura = date('Y-m-d', strtotime("$intervalo days"));

            var_dump($dataFatura); // Exibe a data de vencimento calculada



            // Monta a URL para buscar faturas com vencimento na data calculada
            $data_venc = "$api_url&token=$tokem_retornado&dt_venc_inicio=$dataFatura&dt_venc_fim=$dataFatura";

            // Obtém os dados da API
            $response = get_api_data($data_venc);



            if ($response !== false) {
                $response_data = json_decode($response, true);

                if (isset($response_data['ListaFaturas']) && !empty($response_data['ListaFaturas'])) {
                    foreach ($response_data['ListaFaturas'] as $fatura) {
                        // Usa a data calculada para vencimento ou, se necessário, adapte para pegar do retorno da API
                        $dataFaturaAtual = $dataFatura; // Supondo que $dataFatura já esteja definida

                        // Cria objetos DateTime para comparar a data atual com a data da fatura
                        $hoje = new DateTime();
                        $dataFaturaObj = new DateTime($dataFaturaAtual);

                        // Obtém a diferença com sinal
                        $diffDays = (int)$hoje->diff($dataFaturaObj)->format('%R%a');

                        /*                         echo "difdays: " . $diffDays . "<br>";
 */
                        // Determina se está atrasada: se diffDays for negativo, significa que a fatura já passou
                        $isOverdue = ($diffDays < 0);

                        // Monta o array com os dados da fatura, definindo os status conforme a diferença de dias
                        $dados_fatura = [
                            'cd_fatura'                       => $fatura['cd_fatura'],
                            'nome'                            => $fatura['nome'],
                            'valor'                           => $fatura['valor'],
                            'data_vencimento'                 => $dataFaturaAtual,
                            'regua_id'                        => $regua_id,
                            'FATURA_HOJE'                     => (!$isOverdue && $diffDays == 0) ? 'Sim' : 'Não',
                            'FATURA_5_DIAS'                   => (!$isOverdue && $diffDays == 5) ? 'Sim' : 'Não',
                            'FATURA_ATRASADA_5_DIAS'          => ($isOverdue && $diffDays == -5) ? 'Sim' : 'Não',
                            'FATURA_ATRASADA_12_DIAS'         => ($isOverdue && $diffDays == -12) ? 'Sim' : 'Não',
                            'FATURA_ATRASADA_20_DIAS'         => ($isOverdue && $diffDays == -20) ? 'Sim' : 'Não',
                            'FATURA_ATRASADA_30_DIAS'         => ($isOverdue && $diffDays == -30) ? 'Sim' : 'Não',
                            'FATURA_ATRASADA_42_DIAS'         => ($isOverdue && $diffDays == -42) ? 'Sim' : 'Não',
                            'FATURA_ATRASADA_52_DIAS'         => ($isOverdue && $diffDays == -52) ? 'Sim' : 'Não',
                            'FATURA_ATRASADA_62_DIAS'         => ($isOverdue && $diffDays == -62) ? 'Sim' : 'Não',
                            'enviado'                         => 'Não',
                            'data_atual'                      => date('Y-m-d'),
                            'created_at'                      => date('Y-m-d H:i:s')
                        ];

                        // Insere a fatura no banco de dados
                        inserir_fatura($dados_fatura);
                    }
                } else {
                    echo "Nenhuma fatura encontrada para a régua {$regua_id}.<br>";
                }
            } else {
                echo "Erro ao obter dados da API para a régua {$regua_id}.<br>";
            }

            echo "<br><br>"; // Espaço entre as réguas
        }
    } else {
        echo "Nenhuma régua ativa encontrada.";
    }
} catch (PDOException $e) {
    echo "Erro na consulta das réguas: " . $e->getMessage();
}

// Função para fazer a requisição cURL e retornar os dados da API
function get_api_data($url)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Erro na requisição: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return $response;
}

function inserir_fatura($fatura)
{
    include "conn.php";
    global $conn;

    // Verifica se a fatura já existe no banco de dados
    $check = $conn->prepare("SELECT COUNT(*) FROM FATURAS_A_VENCER 
                             WHERE CD_FATURA = :cd_fatura 
                               AND DATA_VENCIMENTO = :data_vencimento");
    $check->execute([
        ':cd_fatura' => $fatura['cd_fatura'],
        ':data_vencimento' => $fatura['data_vencimento']
    ]);
    $exists = $check->fetchColumn();

    if ($exists > 0) {
        echo "Fatura " . $fatura['cd_fatura'] . " já existe para a régua .<br>";
        return;
    }

    // Insere a nova fatura, se ainda não existir
    $stmt = $conn->prepare("
        INSERT INTO FATURAS_A_VENCER (
            CD_FATURA, NOME, VALOR, DATA_VENCIMENTO, REGUA_ID,
            FATURA_HOJE, FATURA_5_DIAS,
            FATURA_ATRASADA_5_DIAS, FATURA_ATRASADA_12_DIAS,
            FATURA_ATRASADA_20_DIAS, FATURA_ATRASADA_30_DIAS,
            FATURA_ATRASADA_42_DIAS, FATURA_ATRASADA_52_DIAS,
            FATURA_ATRASADA_62_DIAS, ENVIADO, DATA_ATUAL, CREATED_AT
        ) VALUES (
            :cd_fatura, :nome, :valor, :data_vencimento, :regua_id,
            :FATURA_HOJE, :FATURA_5_DIAS,
            :FATURA_ATRASADA_5_DIAS, :FATURA_ATRASADA_12_DIAS,
            :FATURA_ATRASADA_20_DIAS, :FATURA_ATRASADA_30_DIAS,
            :FATURA_ATRASADA_42_DIAS, :FATURA_ATRASADA_52_DIAS,
            :FATURA_ATRASADA_62_DIAS, :enviado, :data_atual, :created_at
        )
    ");

    $stmt->execute([
        ':cd_fatura' => $fatura['cd_fatura'],
        ':nome' => $fatura['nome'],
        ':valor' => $fatura['valor'],
        ':data_vencimento' => $fatura['data_vencimento'],
        ':regua_id' => $fatura['regua_id'],
        ':FATURA_HOJE' => $fatura['FATURA_HOJE'],
        ':FATURA_5_DIAS' => $fatura['FATURA_5_DIAS'],
        ':FATURA_ATRASADA_5_DIAS' => $fatura['FATURA_ATRASADA_5_DIAS'],
        ':FATURA_ATRASADA_12_DIAS' => $fatura['FATURA_ATRASADA_12_DIAS'],
        ':FATURA_ATRASADA_20_DIAS' => $fatura['FATURA_ATRASADA_20_DIAS'],
        ':FATURA_ATRASADA_30_DIAS' => $fatura['FATURA_ATRASADA_30_DIAS'],
        ':FATURA_ATRASADA_42_DIAS' => $fatura['FATURA_ATRASADA_42_DIAS'],
        ':FATURA_ATRASADA_52_DIAS' => $fatura['FATURA_ATRASADA_52_DIAS'],
        ':FATURA_ATRASADA_62_DIAS' => $fatura['FATURA_ATRASADA_62_DIAS'],
        ':enviado' => $fatura['enviado'],
        ':data_atual' => $fatura['data_atual'],
        ':created_at' => $fatura['created_at']
    ]);

    echo "Fatura " . $fatura['cd_fatura'] . " inserida com sucesso.<br>";
}





function get_api_data2($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Desabilita verificação SSL (não recomendado para produção)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch) || $http_code !== 200) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return json_decode($response, true);
}

// Consulta os registros onde CELULAR é NULL
$query = "SELECT * FROM FATURAS_A_VENCER WHERE CELULAR OR LD IS NULL";
$stmt  = $conn->prepare($query);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($registros)) {
    echo "Nenhum registro encontrado com CELULAR = NULL.";
}

// Para cada registro, consulta a API utilizando o cd_fatura
foreach ($registros as $registro) {
    // Obtém o cd_fatura do registro
    $cd_fatura = $registro['CD_FATURA'];

    // Monta a URL da API para consulta utilizando o cd_fatura
    $url_api = "http://138.118.247.66:8080/mk/WSMKLDViaSMS.rule?sys=MK0&token="
        . $tokem_retornado . "&cd_fatura=" . urlencode($cd_fatura);

    // Chama a API e decodifica a resposta
    $api_response = get_api_data2($url_api);

    // Verifica se a resposta é válida e contém registros em "DadosFatura"
    if ($api_response && isset($api_response['DadosFatura']) && count($api_response['DadosFatura']) > 0) {
        // Usa o primeiro registro retornado pela API
        $resultado = $api_response['DadosFatura'][0];
        $codigo_cliente    = $resultado['codigopessoa'];
        $celular           = $resultado['celular'];
        /* print_r($celular); */
        $ld                = $resultado['ld'];

        // Atualiza o registro na base de dados
        $update_query = "UPDATE FATURAS_A_VENCER 
                        SET CODIGO_CLIENTE = :codigo_cliente, 
                            CELULAR = :celular,
                            LD = :ld
                        WHERE ID = :id";
        $stmt_update = $conn->prepare($update_query);
        $stmt_update->execute([
            ':codigo_cliente' => $codigo_cliente,
            ':celular'        => $celular,
            ':ld'             => $ld,
            ':id'             => $registro['ID']
        ]);
    } else {
        echo "API não retornou dados válidos para a fatura: " . $cd_fatura . "<br>";
    }
    // Aguarda 1 segundo entre as requisições para evitar sobrecarga na API
    sleep(1);
    // Adicione um delay de 1 segundo entre as requisições
}





// Consulta os registros onde CELULAR é 55 ou CODIGO_CLIENTE é NULL
$query1 = "SELECT * FROM FATURAS_A_VENCER WHERE CELULAR = '55' OR CODIGO_CLIENTE IS NULL";
$stmt1  = $conn->prepare($query1);
$stmt1->execute();
$registros1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (empty($registros1)) {
    echo "Nenhum registro encontrado com CELULAR = 55 ou CODIGO_CLIENTE = NULL.";
}

// Para cada registro, consulta a API utilizando o cd_fatura
foreach ($registros1 as $registro) {
    // Obtém o cd_fatura do registro
    $cd_fatura = $registro['CD_FATURA'];

    // Monta a URL da API para consulta utilizando o cd_fatura
    $url_api = "http://138.118.247.66:8080/mk/WSMKLDViaSMS.rule?sys=MK0&token="
        . $tokem_retornado . "&cd_fatura=" . urlencode($cd_fatura);

    // Chama a API e decodifica a resposta
    $api_response = get_api_data2($url_api);

    // Verifica se a resposta é válida e contém registros em "DadosFatura"
    if ($api_response && isset($api_response['DadosFatura']) && count($api_response['DadosFatura']) > 0) {
        // Usa o primeiro registro retornado pela API
        $resultado = $api_response['DadosFatura'][0];
        $codigo_cliente = $resultado['codigopessoa'];
        $celular        = $resultado['celular'];
        /* print_r($celular); */
        $ld             = $resultado['ld'];

        // Atualiza o registro na base de dados
        $update_query = "UPDATE FATURAS_A_VENCER 
                        SET CODIGO_CLIENTE = :codigo_cliente,
                            CONTATO_PRINCIPAL = :celular,
                            CELULAR = :celular,
                            LD = :ld
                        WHERE ID = :id";
        $stmt_update = $conn->prepare($update_query);
        $stmt_update->execute([
            ':codigo_cliente' => $codigo_cliente,
            ':celular'        => $celular,
            ':ld'             => $ld,
            ':id'             => $registro['ID']
        ]);
    } else {
        // Verifica se a API retornou a mensagem específica de erro
        if (
            isset($api_response['Mensagem']) && $api_response['Mensagem'] === 'Fatura não localizada.' &&
            isset($api_response['status']) && $api_response['status'] === 'ERRO'
        ) {
            echo "Fatura não localizada, será excluída: " . $cd_fatura . "<br>";

            // Insere no log de exclusões
            $log_query = "INSERT INTO FATURAS_EXCLUIDAS (ID_FATURA, CD_FATURA, MOTIVO, DATA_EXCLUSAO)
                          VALUES (:id_fatura, :cd_fatura, :motivo, NOW())";
            $stmt_log = $conn->prepare($log_query);
            $stmt_log->execute([
                ':id_fatura' => $registro['ID'],
                ':cd_fatura' => $cd_fatura,
                ':motivo'    => 'Fatura não localizada pela API'
            ]);
            echo "Inserindo fatura no FATURAS_EXCLUIDAS: CD_FATURA = $cd_fatura<br>";

            // Remove da tabela FATURAS_A_VENCER
            $delete_query = "DELETE FROM FATURAS_A_VENCER WHERE ID = :id";
            $stmt_delete = $conn->prepare($delete_query);
            $stmt_delete->execute([':id' => $registro['ID']]);
            echo "Fatura removida com sucesso da tabela FATURAS_A_VENCER: ID = {$registro['ID']}<br>";
        } else {
            echo "API não retornou dados válidos para a fatura: " . $cd_fatura . "<br>";
        }
    }

    // Aguarda 1 segundo entre as requisições para evitar sobrecarga na API
    sleep(1);
}



#######################################################################





function deleteFatura($conn, $id_fatura)
{
    try {
        $sql = "DELETE FROM FATURAS_A_VENCER WHERE ID = :id";
        $stmt = $conn->prepare($sql);

        $stmt->bindParam(':id', $id_fatura, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo "Fatura com ID {$id_fatura} excluída com sucesso.<br>";
            return true;
        } else {
            echo "Erro ao excluir fatura com ID {$id_fatura}.<br>";
            return false;
        }
    } catch (PDOException $e) {
        echo "Erro no PDO: " . $e->getMessage() . "<br>";
        return false;
    }
}



// Consulta todos os registros da tabela FATURAS_A_VENCER
$query2 = "SELECT * FROM FATURAS_A_VENCER";
$stmt2 = $conn->prepare($query2);
$stmt2->execute();
$registros3 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($registros3)) {
    echo "Nenhum registro encontrado.";
    exit;
}

// Exibe todos os registros encontrados (debug)
echo "<pre>";
var_dump($registros3);
echo "</pre>";

// Para cada registro, consulta a API utilizando o cd_fatura
foreach ($registros3 as $registro) {
    $cd_cliente = $registro['CODIGO_CLIENTE'];
    $cd_fatura = $registro['CD_FATURA'];
    echo $cd_fatura . "<br>";
    $id_fatura = $registro['ID'];

    echo "<hr>";
    echo "Cliente: " . $cd_cliente . "<br>";
    echo "CD_FATURA (BD): " . $cd_fatura . "<br>";
    echo "ID Fatura: " . $id_fatura . "<br>";

    // Monta a URL da API
    $url_api = "http://138.118.247.66:8080/mk/WSMKFaturasPendentes.rule?sys=MK0&token="
        . $tokem_retornado . "&cd_cliente=" . urlencode($cd_cliente);

    // Chama a API e obtém a resposta
    $json_data = get_api_data2($url_api);

    echo "<pre>";
    echo "<strong>Dados da API para a fatura ID {$id_fatura}:</strong><br>";
    echo "URL: {$url_api}<br>";

    if ($json_data !== null && isset($json_data['FaturasPendentes'])) {
        $faturas = $json_data['FaturasPendentes'];

        // Filtrar fatura correspondente ao CD_FATURA do banco
        $fatura_encontrada = null;
        foreach ($faturas as $fatura) {
            if ((string)$fatura['codfatura'] === (string)$cd_fatura) {
                $fatura_encontrada = $fatura;
                break;
            }
        }

        if ($fatura_encontrada) {
            echo "Fatura correspondente encontrada:<br>";
            echo json_encode($fatura_encontrada, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $descricao = $fatura_encontrada['descricao'];

            if (
                stripos($descricao, "MULTA") !== false ||
                stripos($descricao, "EQUIPAMENTO") !== false
            ) {
                deleteFatura($conn, $id_fatura);
            } else {
                echo "Fatura não é de multa ou equipamento, não será excluída (tipo: {$descricao}).<br>";
            }
        } else {
            echo "Nenhuma fatura encontrada com codfatura = {$cd_fatura}.<br>";
        }
    } else {
        echo "Erro ao obter ou decodificar resposta da API ou dados ausentes.";
    }
    echo "</pre>";

    // Evita sobrecarregar a API
    sleep(1);
}
