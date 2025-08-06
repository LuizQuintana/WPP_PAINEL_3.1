<?php

class MKSolutionsAPI
{
    private $apiUrl;
    private $username;
    private $password;
    private $token;
    private $lastError;

    public function __construct($apiUrl, $username, $password)
    {
        $this->apiUrl = rtrim($apiUrl, '/'); // Garante que não haja barra dupla
        $this->username = $username;
        $this->password = $password;
        $this->token = null;
        $this->lastError = null;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function authenticate()
    {
        $this->lastError = null;
        $endpoint = '/mk/WSMKAutenticacao.rule'; // Exemplo de endpoint de autenticação
        $params = [
            'sys' => 'MK0',
            'user' => $this->username,
            'pass' => $this->password
        ];

        $response = $this->_makeRequest($endpoint, 'GET', $params);

        if ($response && isset($response['token'])) {
            $this->token = $response['token'];
            return true;
        } else {
            $this->lastError = 'Falha na autenticação: ' . ($response['message'] ?? 'Resposta inválida.');
            return false;
        }
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getClientByDocument($documento)
    {
        $this->lastError = null;
        if (!$this->token && !$this->authenticate()) {
            return false;
        }

        $endpoint = '/mk/WSMKConsultarCliente.rule'; // Exemplo de endpoint
        $params = [
            'sys' => 'MK0',
            'token' => $this->token,
            'Documento' => $documento
        ];

        $response = $this->_makeRequest($endpoint, 'GET', $params);

        if ($response && isset($response['cliente'])) {
            return $response['cliente'];
        } else {
            $this->lastError = 'Erro ao buscar cliente: ' . ($response['message'] ?? 'Cliente não encontrado ou erro na API.');
            return false;
        }
    }

    public function getInvoicesByClient($codigoCliente)
    {
        $this->lastError = null;
        if (!$this->token && !$this->authenticate()) {
            return false;
        }

        $endpoint = '/mk/WSMKConsultarFatura.rule'; // Exemplo de endpoint
        $params = [
            'sys' => 'MK0',
            'token' => $this->token,
            'CodigoCliente' => $codigoCliente
        ];

        $response = $this->_makeRequest($endpoint, 'GET', $params);

        if ($response && isset($response['faturas'])) {
            return $response['faturas'];
        } else {
            $this->lastError = 'Erro ao buscar faturas: ' . ($response['message'] ?? 'Faturas não encontradas ou erro na API.');
            return false;
        }
    }

    public function generatePix($documento = null, $codigoFatura = null, $codigoCliente = null)
    {
        $this->lastError = null;
        if (!$this->token && !$this->authenticate()) {
            return false;
        }

        $endpoint = '/mk/WSMKRetornarCopieColaPix.rule';
        $params = [
            'sys' => 'MK0',
            'token' => $this->token,
        ];

        if ($documento) $params['Documento'] = $documento;
        if ($codigoFatura) $params['CodigoFatura'] = $codigoFatura;
        if ($codigoCliente) $params['CodigoCliente'] = $codigoCliente;

        if (empty($documento) && empty($codigoFatura) && empty($codigoCliente)) {
            $this->lastError = 'Pelo menos um dos parâmetros (Documento, CodigoFatura, CodigoCliente) é obrigatório para gerar PIX.';
            return false;
        }

        $response = $this->_makeRequest($endpoint, 'GET', $params);

        if ($response && isset($response['chave_pix'])) {
            return $response['chave_pix'];
        } else {
            $this->lastError = 'Erro ao gerar PIX: ' . ($response['message'] ?? 'Chave PIX não gerada ou erro na API.');
            return false;
        }
    }

    public function openTicket($clientId, $subject, $description)
    {
        $this->lastError = null;
        if (!$this->token && !$this->authenticate()) {
            return false;
        }

        // TODO: Substitua '/mk/WSMKOpenTicket.rule' pelo endpoint real da API do MK Solutions para abrir chamados.
        // TODO: Ajuste os parâmetros de acordo com a documentação da API de abertura de chamados.
        $endpoint = '/mk/WSMKOpenTicket.rule'; 
        $params = [
            'sys' => 'MK0',
            'token' => $this->token,
            'CodigoCliente' => $clientId,
            'Assunto' => $subject,
            'Descricao' => $description
        ];

        $response = $this->_makeRequest($endpoint, 'POST', $params); // Assumindo que é um POST

        if ($response && isset($response['success']) && $response['success'] === true) {
            return true; // Chamado aberto com sucesso
        } else {
            $this->lastError = 'Erro ao abrir chamado: ' . ($response['message'] ?? 'Resposta inválida ou erro desconhecido.');
            return false;
        }
    }

    private function _makeRequest($endpoint, $method, $params = [])
    {
        $url = $this->apiUrl . $endpoint;
        $queryString = http_build_query($params);

        if ($method === 'GET') {
            $url .= '?' . $queryString;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para desenvolvimento, remover em produção
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Apenas para desenvolvimento, remover em produção

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $this->lastError = 'Erro cURL: ' . curl_error($ch);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $decodedResponse;
        } else {
            $this->lastError = 'Erro na requisição (HTTP ' . $httpCode . '): ' . ($decodedResponse['message'] ?? $response);
            return false;
        }
    }
}

?>