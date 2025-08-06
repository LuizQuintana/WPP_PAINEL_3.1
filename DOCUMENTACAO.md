# Documentação do Projeto WPP_PAINEL

## 1. Descrição do Projeto

O WPP_PAINEL é um sistema de gerenciamento de envios de mensagens em massa via WhatsApp, integrado a uma API (como a do WPP-Connect ou 360dialog). Ele permite o agendamento de envios, a criação de modelos de mensagens e, com as últimas atualizações, o gerenciamento de respostas de clientes.

## 2. Estrutura de Arquivos

- **/api**: Contém os scripts que fazem a interface com a API do WhatsApp.
  - `wpp_api.php`: Funções para interagir com a API (enviar mensagens, verificar status, etc.).
  - `webhook_handler.php`: Endpoint que recebe as notificações de novas mensagens (webhooks).

- **/config**: Arquivos de configuração do sistema.
  - `config.php`: Configurações gerais.
  - `conn.php` e `db.php`: Configurações de conexão com o banco de dados.
  - `config_wpp.php`: Configurações específicas da API do WhatsApp.

- **/public**: Arquivos acessíveis publicamente, que compõem a interface do usuário.
  - `index.php`: Página inicial.
  - `envios.php`: Página para gerenciar os envios.
  - `modelos.php`: Página para criar modelos de mensagens.
  - `respostas.php`: **(Novo)** Caixa de entrada para visualizar as respostas dos clientes.
  - `responder.php`: **(Novo)** Página para responder a uma mensagem específica.

- **/storage**: Armazenamento de dados.
  - `tokens.json`: Armazena os tokens de sessão da API.
  - `webhooks.log`: Log de todos os webhooks recebidos.

## 3. Banco de Dados

O sistema utiliza um banco de dados MySQL para armazenar informações. As tabelas a seguir foram adicionadas ou modificadas para gerenciar as respostas dos clientes e os workflows.

### Tabela: `respostas_clientes`

| Coluna             | Tipo          | Descrição                                                                 |
| ------------------ | ------------- | ------------------------------------------------------------------------- |
| `id`               | `INT`         | Identificador único da resposta (chave primária, auto-incremento).        |
| `remetente`        | `VARCHAR(255)`| Número de telefone do cliente que enviou a mensagem.                      |
| `mensagem`         | `TEXT`        | Conteúdo da mensagem recebida.                                            |
| `categoria`        | `VARCHAR(50)` | Categoria da mensagem (ex: 'PIX', 'Geral').                               |
| `data_recebimento` | `DATETIME`    | Data e hora em que a mensagem foi recebida.                               |
| `lida`             | `BOOLEAN`     | Status que indica se a mensagem foi lida por um operador (padrão: `FALSE`). |
| `data_leitura`     | `DATETIME`    | Data e hora em que a mensagem foi marcada como lida.                      |
| `respondida`       | `BOOLEAN`     | Status que indica se a mensagem foi respondida (padrão: `FALSE`).         |
| `data_resposta`    | `DATETIME`    | Data e hora em que a resposta foi enviada.                                |
| `resposta`         | `TEXT`        | Conteúdo da resposta enviada pelo operador.                               |
| `operador_id`      | `INT`         | ID do operador que respondeu à mensagem (futura implementação).           |
| `created_at`       | `TIMESTAMP`   | Data e hora de criação do registro.                                       |
| `updated_at`       | `TIMESTAMP`   | Data e hora da última atualização do registro.                            |

### Comando SQL para Criação da Tabela `respostas_clientes`

```sql
CREATE TABLE IF NOT EXISTS respostas_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    categoria VARCHAR(50) DEFAULT 'Geral' NOT NULL,
    data_recebimento DATETIME NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    data_leitura DATETIME,
    respondida BOOLEAN DEFAULT FALSE,
    data_resposta DATETIME,
    resposta TEXT,
    operador_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tabela: `workflows`

| Coluna             | Tipo          | Descrição                                                                 |
| ------------------ | ------------- | ------------------------------------------------------------------------- |
| `id`               | `INT`         | Identificador único do workflow (chave primária, auto-incremento).        |
| `nome`             | `VARCHAR(255)`| Nome descritivo do workflow.                                              |
| `gatilho`          | `VARCHAR(100)`| Evento que dispara o workflow (ex: `mensagem_recebida`).                  |
| `condicoes_json`   | `TEXT`        | Condições em formato JSON para ativar o workflow.                         |
| `acoes_json`       | `TEXT`        | Ações em formato JSON a serem executadas pelo workflow.                   |
| `ativo`            | `BOOLEAN`     | Indica se o workflow está ativo (padrão: `TRUE`).                         |
| `created_at`       | `TIMESTAMP`   | Data e hora de criação do registro.                                       |
| `updated_at`       | `TIMESTAMP`   | Data e hora da última atualização do registro.                            |

### Comando SQL para Criação da Tabela `workflows`

```sql
CREATE TABLE IF NOT EXISTS workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    gatilho VARCHAR(100) NOT NULL,
    condicoes_json TEXT,
    acoes_json TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 4. Workflow de Respostas

1.  **Recebimento:** A API do WhatsApp envia um webhook para o `webhook_handler.php` sempre que uma nova mensagem é recebida.
2.  **Classificação e Armazenamento:** O `webhook_handler.php` classifica a mensagem (ex: 'PIX', 'Geral') e a salva na tabela `respostas_clientes`.
3.  **Processamento de Workflow:** Após o armazenamento, o `webhook_handler.php` invoca o `workflow_engine.php` para avaliar e executar workflows baseados na mensagem recebida.
4.  **Visualização:** As mensagens salvas são exibidas na página `respostas.php` (Caixa de Entrada), com filtros por categoria.
5.  **Ação:** Um operador pode clicar em "Responder" para ser direcionado à página `responder.php`.
6.  **Envio:** Na página de resposta, o operador digita a mensagem e a envia. O sistema utiliza a função `sendMessage` para enviar a resposta ao cliente.
7.  **Atualização:** Após o envio, a mensagem original é marcada como "respondida" no banco de dados.

## 5. Configuração

Para que o sistema funcione corretamente, é necessário configurar:

1.  **Banco de Dados:** Ajuste as credenciais nos arquivos `config/conn.php` e `config/db.php`.
2.  **API do WhatsApp:** Insira a `secretKey` e a `baseUrl` corretas no arquivo `config/config_wpp.php`.
3.  **Webhook:** Configure na sua plataforma de API do WhatsApp (ex: 360dialog) para que os webhooks de novas mensagens sejam enviados para o seguinte URL:
    `http://SEU_DOMINIO/webhook_handler.php`

## 6. Sistema de Workflow

O sistema de workflow permite automatizar respostas e ações com base em regras definidas. Ele é composto por:

-   **`workflow_engine.php`**: O motor que processa as mensagens recebidas, avalia as condições definidas nos workflows e executa as ações correspondentes.
-   **Tabela `workflows`**: Armazena a definição de cada fluxo de trabalho, incluindo seu nome, gatilho, condições e ações em formato JSON.

### Como funciona:

1.  Uma mensagem é recebida pelo `webhook_handler.php` e salva no banco de dados.
2.  O `webhook_handler.php` passa os dados da mensagem para o `WorkflowEngine`.
3.  O `WorkflowEngine` consulta a tabela `workflows` em busca de fluxos ativos com o gatilho `mensagem_recebida`.
4.  Para cada workflow encontrado, as `condicoes_json` são avaliadas. As condições podem incluir:
    *   `contem_palavra`: A mensagem deve conter todas as palavras especificadas.
    *   `nao_contem_palavra`: A mensagem não deve conter nenhuma das palavras especificadas.
    *   `categoria_igual`: A categoria da mensagem deve ser igual à especificada.
5.  Se todas as condições de um workflow forem atendidas, as `acoes_json` são executadas em sequência. As ações podem incluir:
    *   `enviar_mensagem`: Envia uma mensagem de template para o remetente.
    *   `atualizar_status_db`: Atualiza um campo específico na tabela `respostas_clientes`.
    *   `notificar_operador`: (A ser implementado) Envia uma notificação para um operador.

### Exemplo de Workflow (JSON para `condicoes_json` e `acoes_json`):

**Workflow: Suporte Geral**

-   **Nome:** Suporte Geral
-   **Gatilho:** `mensagem_recebida`
-   **Condições (JSON):**
    ```json
    {
      "nao_contem_palavra": ["pix", "fatura", "boleto", "pagamento"]
    }
    ```
-   **Ações (JSON):**
    ```json
    [
      {"tipo": "enviar_mensagem", "template": "agradecimento_contato_suporte"},
      {"tipo": "atualizar_status_db", "campo": "status_workflow", "valor": "em_atendimento"},
      {"tipo": "notificar_operador", "operador_id": 1, "mensagem": "Nova solicitação de suporte geral."} 
    ]
    ```

**Observação:** A implementação da interface para gerenciar os workflows (adicionar, editar, etc.) será o próximo passo.

| Coluna             | Tipo          | Descrição                                                                 |
| ------------------ | ------------- | ------------------------------------------------------------------------- |
| `id`               | `INT`         | Identificador único da resposta (chave primária, auto-incremento).        |
| `remetente`        | `VARCHAR(255)`| Número de telefone do cliente que enviou a mensagem.                      |
| `mensagem`         | `TEXT`        | Conteúdo da mensagem recebida.                                            |
| `data_recebimento` | `DATETIME`    | Data e hora em que a mensagem foi recebida.                               |
| `lida`             | `BOOLEAN`     | Status que indica se a mensagem foi lida por um operador (padrão: `FALSE`). |
| `data_leitura`     | `DATETIME`    | Data e hora em que a mensagem foi marcada como lida.                      |
| `respondida`       | `BOOLEAN`     | Status que indica se a mensagem foi respondida (padrão: `FALSE`).         |
| `data_resposta`    | `DATETIME`    | Data e hora em que a resposta foi enviada.                                |
| `resposta`         | `TEXT`        | Conteúdo da resposta enviada pelo operador.                               |
| `operador_id`      | `INT`         | ID do operador que respondeu à mensagem (futura implementação).           |
| `created_at`       | `TIMESTAMP`   | Data e hora de criação do registro.                                       |
| `updated_at`       | `TIMESTAMP`   | Data e hora da última atualização do registro.                            |

### Comando SQL para Criação da Tabela

```sql
CREATE TABLE IF NOT EXISTS respostas_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    data_recebimento DATETIME NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    data_leitura DATETIME,
    respondida BOOLEAN DEFAULT FALSE,
    data_resposta DATETIME,
    resposta TEXT,
    operador_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 4. Workflow de Respostas

1.  **Recebimento:** A API do WhatsApp envia um webhook para o `api/webhook_handler.php` sempre que uma nova mensagem é recebida.
2.  **Armazenamento:** O `webhook_handler.php` processa o webhook e salva os dados da mensagem (remetente, conteúdo, etc.) na tabela `respostas_clientes`.
3.  **Visualização:** As mensagens salvas são exibidas na página `public/respostas.php` (Caixa de Entrada).
4.  **Ação:** Um operador pode clicar em "Responder" para ser direcionado à página `public/responder.php`.
5.  **Envio:** Na página de resposta, o operador digita a mensagem e a envia. O sistema utiliza a função `sendMessage` para enviar a resposta ao cliente.
6.  **Atualização:** Após o envio, a mensagem original é marcada como "respondida" no banco de dados.

## 5. Configuração

Para que o sistema funcione corretamente, é necessário configurar:

1.  **Banco de Dados:** Ajuste as credenciais nos arquivos `config/conn.php` e `config/db.php`.
2.  **API do WhatsApp:** Insira a `secretKey` e a `baseUrl` corretas no arquivo `config/config_wpp.php`.
3.  **Webhook:** Configure na sua plataforma de API do WhatsApp (ex: 360dialog) para que os webhooks de novas mensagens sejam enviados para o seguinte URL:
    `http://SEU_DOMINIO/api/webhook_handler.php`
