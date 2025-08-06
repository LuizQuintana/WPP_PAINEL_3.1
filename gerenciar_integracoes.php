<?php
require_once 'config/db.php';
require_once 'header.php';

$message = '';

// Buscar integrações existentes
$integracoes = [];
try {
    $stmt = $pdo->query("SELECT * FROM integracoes ORDER BY nome ASC");
    $integracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Erro ao buscar integrações: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Integrações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- O tema_global.css será carregado pelo header.php -->
    <style>
        /* Estilos específicos para esta página, complementando o tema global */
        .main-container {
            margin-left: 26%;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .table th,
        .table td {
            vertical-align: middle;
        }

        .form-check-input:checked {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        #form-integracao-container {
            display: none; /* Começa oculto */
        }

.mb-4{
display:flex !important;
flex-direction: column;}

h2{
font-size:1.3em !important;
}

        /* Media query para responsividade */
        @media (max-width: 992px) {
            .main-container {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="main-container">
        <!-- Container da Lista de Integrações -->
        <div id="lista-integracoes-container" style="display: block;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-plug"></i> Gerenciar Integrações</h2>
                <button id="btn-add-new" class="btn btn-primary"><i class="fas fa-plus"></i> Adicionar Nova Integração</button>
            </div>

            <?php if (!empty($message)) : ?>
                <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Tipo</th>
                                    <th>Ativo</th>
                                    <th>Criado Em</th>
                                    <th>Atualizado Em</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($integracoes)) : ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">Nenhuma integração encontrada.</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($integracoes as $integracao) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars($integracao['nome']) ?></td>
                                            <td><?= htmlspecialchars($integracao['tipo']) ?></td>
                                            <td>
                                                <?php if ($integracao['ativo']) : ?>
                                                    <span class="badge bg-success">Sim</span>
                                                <?php else : ?>
                                                    <span class="badge bg-danger">Não</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($integracao['created_at'])) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($integracao['updated_at'])) ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-warning me-2 btn-edit" data-id="<?= $integracao['id'] ?>"><i class="fas fa-edit"></i> Editar</button>
                                                <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $integracao['id'] ?>"><i class="fas fa-trash"></i> Excluir</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Container do Formulário de Criação/Edição -->
        <div id="form-integracao-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 id="form-title">Adicionar Nova Integração</h2>
                <div>
                    <button id="btn-cancel" class="btn btn-light"><i class="fas fa-times"></i> Cancelar</button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form id="integracao-form" method="POST" action="salvar_integracao.php">
                        <input type="hidden" id="integracao-id" name="id">

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome da Integração:</label>
                            <input type="text" id="nome" name="nome" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo de Integração:</label>
                            <select id="tipo" name="tipo" class="form-select" required>
                                <option value="">Selecione o Tipo</option>
                                <option value="mksolutions">MK Solutions</option>
                                <option value="360dialog">360Dialog</option>
                                <!-- Adicionar outros tipos de integração aqui -->
                            </select>
                        </div>

                        <div id="config-fields" class="mb-3 p-3 border rounded" style="display: none;">
                            <h5>Configurações Específicas</h5>
                            <!-- Campos de configuração dinâmicos serão injetados aqui -->
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1" checked>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Integração</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const listaContainer = document.getElementById('lista-integracoes-container');
            const formContainer = document.getElementById('form-integracao-container');
            const btnAddNew = document.getElementById('btn-add-new');
            const btnCancel = document.getElementById('btn-cancel');
            const formTitle = document.getElementById('form-title');
            const integracaoForm = document.getElementById('integracao-form');
            const tipoSelect = document.getElementById('tipo');
            const configFieldsContainer = document.getElementById('config-fields');

            const integrationConfigs = {
                mksolutions: [
                    { name: 'url', label: 'URL da API', type: 'text', placeholder: 'Ex: https://api.mksolutions.com.br', required: true },
                    { name: 'token', label: 'Token de Acesso', type: 'text', placeholder: 'Seu token de acesso', required: true },
                    { name: 'usuario', label: 'Usuário', type: 'text', placeholder: 'Usuário da API', required: true },
                    { name: 'senha', label: 'Senha', type: 'password', placeholder: 'Senha da API', required: true }
                ],
                '360dialog': [
                    { name: 'api_key', label: 'API Key', type: 'text', placeholder: 'Sua chave da API 360Dialog', required: true },
                    { name: 'waba_id', label: 'WABA ID', type: 'text', placeholder: 'ID da sua conta WABA', required: true }
                ]
            };

            // Alterna entre a view de lista e a de formulário
            function showFormView(integracao = null) {
                listaContainer.style.display = 'none';
                formContainer.style.display = 'block';
                integracaoForm.reset();
                configFieldsContainer.innerHTML = '';
                configFieldsContainer.style.display = 'none';

                if (integracao) {
                    formTitle.textContent = `Editar Integração: ${integracao.nome}`;
                    document.getElementById('integracao-id').value = integracao.id;
                    document.getElementById('nome').value = integracao.nome;
                    document.getElementById('tipo').value = integracao.tipo;
                    document.getElementById('ativo').checked = !!parseInt(integracao.ativo);

                    // Dispara o evento change para carregar os campos de configuração
                    tipoSelect.dispatchEvent(new Event('change'));

                    // Preenche os campos de configuração específicos
                    if (integracao.config_json) {
                        const config = JSON.parse(integracao.config_json);
                        for (const key in config) {
                            if (config.hasOwnProperty(key)) {
                                const input = document.getElementById(key);
                                if (input) {
                                    input.value = config[key];
                                }
                            }
                        }
                    }
                } else {
                    formTitle.textContent = 'Adicionar Nova Integração';
                    document.getElementById('integracao-id').value = '';
                }
            }

            function showListView() {
                listaContainer.style.display = 'block';
                formContainer.style.display = 'none';
            }

            btnAddNew.addEventListener('click', () => showFormView(null));
            btnCancel.addEventListener('click', showListView);

            // Renderiza campos de configuração específicos para o tipo selecionado
            tipoSelect.addEventListener('change', function() {
                const tipo = this.value;
                configFieldsContainer.innerHTML = '';
                if (tipo && integrationConfigs[tipo]) {
                    integrationConfigs[tipo].forEach(field => {
                        const div = document.createElement('div');
                        div.className = 'mb-3';
                        let inputHtml = '';
                        if (field.type === 'textarea') {
                            inputHtml = `<textarea id="${field.name}" name="config_${field.name}" class="form-control" rows="3" placeholder="${field.placeholder}" ${field.required ? 'required' : ''}></textarea>`;
                        } else {
                            inputHtml = `<input type="${field.type}" id="${field.name}" name="config_${field.name}" class="form-control" placeholder="${field.placeholder}" ${field.required ? 'required' : ''}>`;
                        }
                        div.innerHTML = `
                            <label for="${field.name}" class="form-label">${field.label}:</label>
                            ${inputHtml}
                        `;
                        configFieldsContainer.appendChild(div);
                    });
                    configFieldsContainer.style.display = 'block';
                } else {
                    configFieldsContainer.style.display = 'none';
                }
            });

            // Lógica para o botão de Editar
            document.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const integracaoId = this.dataset.id;
                    fetch(`get_integracao_details.php?id=${integracaoId}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showFormView(result.data);
                            } else {
                                alert('Erro ao buscar detalhes da integração: ' + result.message);
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            alert('Não foi possível conectar ao servidor.');
                        });
                });
            });

            // Lógica para o botão de Excluir
            document.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function() {
                    const integracaoId = this.dataset.id;
                    if (confirm('Tem certeza que deseja excluir esta integração?')) {
                        // Usar fetch para exclusão para não recarregar a página inteira
                        fetch('excluir_integracao.php?id=' + integracaoId, {
                            method: 'GET' // ou 'POST' se o seu script de exclusão espera POST
                        })
                        .then(response => response.json())
                        .then(result => {
                            if(result.success) {
                                window.location.reload(); // Recarrega para mostrar a lista atualizada
                            } else {
                                alert("Erro ao excluir: " + result.message);
                            }
                        })
                        .catch(() => alert("Erro de comunicação ao tentar excluir."));
                    }
                });
            });
        });
    </script>
</body>

</html>
