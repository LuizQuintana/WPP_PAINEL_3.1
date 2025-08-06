<?php
include 'config/conn.php'; // Conexão com o banco de dados
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Modelos de Mensagem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .rich-text-editor {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.75rem;
            min-height: 200px;
            background-color: #206510;
            font-family: inherit;
            white-space: pre-wrap;
            line-height: 1.5;
        }

        .rich-text-editor:focus {
            outline: none;
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .list-option-item {
            display: flex;
            gap: 10px;
            align-items: start;
        }

        #sidebar-templates {
            position: fixed;
            top: 0;
            right: -300px;
            width: 300px;
            height: 100vh;
            background: white;
            border-left: 1px solid #ddd;
            padding: 20px;
            transition: right 0.3s ease;
            z-index: 1050;
            overflow-y: auto;
        }

        #sidebar-templates.visible {
            right: 0;
        }

        .main-container {
            padding: 20px;
        }

        #form-modelo-container {
            display: none;
        }

        .card-header-custom {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .editor-toolbar {
            margin-bottom: 10px;
        }

        .preview-box {
            background-color: #0d50a5;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 15px;
            margin-top: 10px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

  <div class="main-container">

        <!-- Container da Lista de Modelos -->
        <div id="lista-modelos-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fab fa-whatsapp"></i> Modelos de Mensagem</h2>
                <button id="btn-add-new" class="btn btn-primary"><i class="fas fa-plus"></i> Adicionar Novo Modelo</button>
            </div>

            <?php if (isset($_GET['msg'])) : ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome do Modelo</th>
                                <th>Conteúdo</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt = $conn->query("SELECT * FROM DBA_MODELOS_MSG ORDER BY id DESC");
                                $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if ($modelos) {
                                    foreach ($modelos as $modelo) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($modelo['nome']) . "</td>";
                                        echo "<td>" . htmlspecialchars(substr($modelo['conteudo'], 0, 100)) . "...</td>";
                                        echo "<td class='text-end'>";
                                        echo "<button class='btn btn-sm btn-outline-primary me-2 btn-edit' data-id='" . $modelo['id'] . "'><i class='fas fa-edit'></i> Editar</button>";
                                        echo "<a href='#' class='btn btn-sm btn-outline-danger btn-delete' data-id='" . $modelo['id'] . "'><i class='fas fa-trash'></i> Excluir</a>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo '<tr><td colspan="3" class="text-center">Nenhum modelo encontrado.</td></tr>';
                                }
                            } catch (PDOException $e) {
                                echo '<tr><td colspan="3" class="text-center text-danger">Erro ao buscar modelos: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Container do Formulário de Criação/Edição -->
        <div id="form-modelo-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 id="form-title">Criar Novo Modelo</h2>
                <div>
                    <button id="btn-choose-template" class="btn btn-secondary"><i class="fas fa-list-alt"></i> Escolher Modelo</button>
                    <button id="btn-cancel" class="btn btn-light">Cancelar</button>
                </div>
            </div>

            <form id="modelo-form" method="POST" action="salvar_modelo_unificado.php">
                <input type="hidden" id="modelo-id" name="modelo_id">
                <div class="row">
                    <!-- Coluna Esquerda: Formulário Principal -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header card-header-custom">
                                Detalhes do Modelo
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="canal" class="form-label">Canal</label>
                                    <select id="canal" name="canal" class="form-select">
                                        <option value="1">Canal Principal (360Dialog)</option>
                                        <option value="2">Canal Secundário (Fortics)</option>
                                        <option value="3">WPP_CONNECT</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="nome_modelo" class="form-label">Nome do modelo</label>
                                    <input type="text" id="nome_modelo" name="nome_modelo" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="categoria" class="form-label">Categoria</label>
                                    <select id="categoria" name="categoria" class="form-select">
                                        <option value="SERVICE">SERVICE</option>
                                        <option value="MARKETING">MARKETING</option>
                                        <option value="AUTHENTICATION">AUTHENTICATION</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="idioma" class="form-label">Idioma</label>
                                    <select id="idioma" name="idioma" class="form-select">
                                        <option value="pt_BR">Português (Brasil)</option>
                                        <option value="en_US">Inglês (EUA)</option>
                                    </select>
                                </div>

                                <!-- Cabeçalho -->
                                <div class="mb-3">
                                    <label for="header_type" class="form-label">Tipo de Cabeçalho</label>
                                    <select id="header_type" name="header_type" class="form-select">
                                        <option value="NONE">Nenhum</option>
                                        <option value="TEXT">Texto</option>
                                        <option value="IMAGE">Imagem</option>
                                    </select>
                                </div>
                                <div id="header_content_text" class="mb-3" style="display: none;">
                                    <label for="header_text" class="form-label">Texto do Cabeçalho</label>
                                    <input type="text" id="header_text" name="header_text" class="form-control">
                                </div>

                                <!-- Corpo da Mensagem -->
                                <div class="mb-3">
                                    <label class="form-label">Corpo da Mensagem</label>
                                    <div class="editor-toolbar btn-group btn-group-sm" role="group">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">Variáveis</button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('{{1}}')">Nome do Cliente</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('{{2}}')">Número da Fatura</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('{{3}}')">Valor</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('{{4}}')">Data de Vencimento</a></li>
                                            </ul>
                                        </div>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">Emojis</button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('😊')">😊 Rosto Sorridente</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('👍')">👍 Polegar para Cima</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('❤️')">❤️ Coração</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('📅')">📅 Calendário</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('💰')">💰 Saco de Dinheiro</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('⚠️')">⚠️ Aviso</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('✅')">✅ Verificado</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('🚀')">🚀 Foguete</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="insertVar('🎯')">🎯 Alvo</a></li>
                                            </ul>
                                        </div>
                                        <button type="button" class="btn btn-light" onclick="formatText('bold')">*Negrito*</button>
                                        <button type="button" class="btn btn-light" onclick="formatText('italic')">_Itálico_</button>
                                        <button type="button" class="btn btn-light" onclick="formatText('line')">Nova Linha</button>
                                    </div>
                                    <div id="rich-text-editor" class="rich-text-editor" contenteditable="true" placeholder="Digite sua mensagem aqui..."></div>
                                    <textarea name="conteudo" id="conteudo-hidden" style="display:none;"></textarea>

                                    <!-- Preview da mensagem -->
                                    <div class="mt-3">
                                        <label class="form-label">Preview (como aparecerá no WhatsApp):</label>
                                        <div id="message-preview" class="preview-box"></div>
                                    </div>

                                    <small class="text-muted">
                                        Dica: Use *texto* para negrito, _texto_ para itálico no WhatsApp
                                    </small>
                                </div>

                                <!-- Rodapé -->
                                <div class="mb-3">
                                    <label for="footer_text" class="form-label">Rodapé</label>
                                    <input type="text" id="footer_text" name="footer_text" class="form-control" maxlength="60">
                                </div>

                                <!-- Seção de Ação com Lista -->
                                <div class="card mt-4">
                                    <div class="card-header">
                                        Ação com Lista (Botões Interativos)
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="list_title" class="form-label">Título da Lista <small class="text-muted">(obrigatório se usar botões)</small></label>
                                            <input type="text" id="list_title" name="list_title" class="form-control" placeholder="Ex: Escolha uma opção">
                                        </div>
                                        <div class="mb-3">
                                            <label for="list_button_text" class="form-label">Texto do Botão Principal</label>
                                            <input type="text" id="list_button_text" name="list_button_text" class="form-control" placeholder="Ex: Ver Opções">
                                        </div>
                                        <hr>
                                        <h6>Opções da Lista</h6>
                                        <div id="list-options-container">
                                            <!-- Opções adicionadas dinamicamente aqui -->
                                        </div>
                                        <button type="button" id="btn-add-option" class="btn btn-sm btn-outline-success mt-2"><i class="fas fa-plus"></i> Adicionar Opção</button>
                                        <button type="button" id="btn-clear-options" class="btn btn-sm btn-outline-warning mt-2"><i class="fas fa-broom"></i> Limpar Opções</button>
                                    </div>
                                </div>

                                <div class="mt-4 text-end">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Salvar Modelo</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar para Selecionar Modelos -->
    <div id="sidebar-templates">
        <h5><i class="fas fa-th-list"></i> Templates Prontos</h5>
        <hr>
        <div class="list-group">
            <a href="#" class="list-group-item list-group-item-action" onclick="selectTemplate('Cobranca_Vencimento')">Cobrança de Vencimento</a>
            <a href="#" class="list-group-item list-group-item-action" onclick="selectTemplate('Boas_Vindas')">Boas-Vindas Cliente</a>
            <a href="#" class="list-group-item list-group-item-action" onclick="selectTemplate('Pesquisa_Satisfacao')">Pesquisa de Satisfação</a>
        </div>
        <button class="btn btn-sm btn-outline-secondary mt-3" onclick="toggleSidebar()">Fechar</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const listaContainer = document.getElementById('lista-modelos-container');
            const formContainer = document.getElementById('form-modelo-container');
            const btnAddNew = document.getElementById('btn-add-new');
            const btnCancel = document.getElementById('btn-cancel');
            const formTitle = document.getElementById('form-title');
            const modeloForm = document.getElementById('modelo-form');
            const richTextEditor = document.getElementById('rich-text-editor');
            const hiddenTextarea = document.getElementById('conteudo-hidden');
            const messagePreview = document.getElementById('message-preview');
            const headerTypeSelect = document.getElementById('header_type');
            const headerContentText = document.getElementById('header_content_text');
            const btnAddOption = document.getElementById('btn-add-option');
            const btnClearOptions = document.getElementById('btn-clear-options');
            const listOptionsContainer = document.getElementById('list-options-container');
            const sidebar = document.getElementById('sidebar-templates');
            const btnChooseTemplate = document.getElementById('btn-choose-template');

            // Função para converter o HTML do editor em texto puro para o WhatsApp
            function normalizeTextForWhatsApp(html) {
                if (!html) return '';

                let text = html;

                // 1. Converte tags de quebra de linha (<br>, <p>, <div>) para o caractere \n
                text = text.replace(/<br\s*\/?>/gi, '\n');
                text = text.replace(/<\/p>/gi, '\n');
                text = text.replace(/<div>/gi, '\n'); // Início de div também pode ser uma quebra

                // 2. Remove todas as outras tags HTML para limpar o conteúdo
                text = text.replace(/<[^>]+>/g, '');

                // 3. Decodifica entidades HTML (como &nbsp; ou &amp;) para seus caracteres correspondentes
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = text;
                text = tempDiv.textContent || tempDiv.innerText || '';

                // 4. Remove espaços extras ao redor dos marcadores de formatação do WhatsApp
                text = text.replace(/\*\s*(.*?)\s*\*/g, '*$1*');
                text = text.replace(/_\s*(.*?)\s*_/g, '_$1_');
                text = text.replace(/~\s*(.*?)\s*~/g, '~$1~');

                // 5. Garante que não haja mais de duas quebras de linha seguidas
                text = text.replace(/\n{3,}/g, '\n\n');

                // 6. Remove espaços em branco no início e no fim do texto
                return text.trim();
            }

            // Função para atualizar o preview da mensagem
            function updatePreview() {
                const normalized = normalizeTextForWhatsApp(richTextEditor.innerHTML);
                messagePreview.textContent = normalized;
            }

            // Atualiza o preview sempre que o conteúdo do editor mudar
            richTextEditor.addEventListener('input', updatePreview);
            richTextEditor.addEventListener('paste', function(e) {
                // Usa um timeout para garantir que o conteúdo colado seja processado
                setTimeout(updatePreview, 10);
            });

            // Sincroniza o editor com o textarea oculto antes de submeter o formulário
            modeloForm.addEventListener('submit', function(e) {
                hiddenTextarea.value = normalizeTextForWhatsApp(richTextEditor.innerHTML);

                // Validação: se há opções de lista, o título da lista é obrigatório
                const hasOptions = listOptionsContainer.children.length > 0;
                const listTitle = document.getElementById('list_title').value.trim();

                if (hasOptions && !listTitle) {
                    alert('Se você adicionar opções de lista, é obrigatório preencher o "Título da Lista".');
                    document.getElementById('list_title').focus();
                    e.preventDefault(); // Impede o envio do formulário
                    return false;
                }

                // Se não houver opções, limpa os campos da lista para não enviar dados desnecessários
                if (!hasOptions) {
                    document.getElementById('list_title').value = '';
                    document.getElementById('list_button_text').value = '';
                }
            });

            // Alterna entre a view de lista e a de formulário
            function showFormView() {
                listaContainer.style.display = 'none';
                formContainer.style.display = 'block';
                formTitle.textContent = 'Criar Novo Modelo';
                modeloForm.reset();
                richTextEditor.innerHTML = ''; // Limpa o editor
                messagePreview.textContent = '';
                listOptionsContainer.innerHTML = '';
                document.getElementById('modelo-id').value = '';
                optionCounter = 0;
            }

            function showListView() {
                listaContainer.style.display = 'block';
                formContainer.style.display = 'none';
            }

            btnAddNew.addEventListener('click', showFormView);
            btnCancel.addEventListener('click', showListView);

            // Mostra/oculta campo de texto do cabeçalho
            headerTypeSelect.addEventListener('change', function() {
                headerContentText.style.display = this.value === 'TEXT' ? 'block' : 'none';
            });

            // Adicionar opção na lista
            let optionCounter = 0;
            btnAddOption.addEventListener('click', function() {
                optionCounter++;
                const optionId = `option_${optionCounter}`;
                const newOption = document.createElement('div');
                newOption.className = 'list-option-item mb-2';
                newOption.id = optionId;
                newOption.innerHTML = `
                    <div class="flex-grow-1">
                        <input type="text" name="list_options[${optionCounter}][title]" class="form-control form-control-sm" placeholder="Título da Opção ${optionCounter}" required>
                        <input type="text" name="list_options[${optionCounter}][description]" class="form-control form-control-sm mt-1" placeholder="Descrição (opcional)">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('${optionId}').remove()"><i class="fas fa-times"></i></button>
                `;
                listOptionsContainer.appendChild(newOption);
            });

            // Limpar todas as opções
            btnClearOptions.addEventListener('click', function() {
                if (confirm('Tem certeza que deseja remover todas as opções?')) {
                    listOptionsContainer.innerHTML = '';
                    optionCounter = 0;
                }
            });

            // Inserir variável no editor
            window.insertVar = function(variable) {
                richTextEditor.focus();
                document.execCommand('insertText', false, variable);
                updatePreview();
            }

            // Função para formatação de texto
            window.formatText = function(type) {
                richTextEditor.focus();
                const selection = window.getSelection();

                if (type === 'line') {
                    document.execCommand('insertHTML', false, '<br><br>');
                } else if (type === 'bold') {
                    const selectedText = selection.toString();
                    if (selectedText) {
                        document.execCommand('insertText', false, `*${selectedText}*`);
                    } else {
                        document.execCommand('insertText', false, '**');
                        const range = selection.getRangeAt(0);
                        range.setStart(range.startContainer, range.startOffset - 1);
                        selection.removeAllRanges();
                        selection.addRange(range);
                    }
                } else if (type === 'italic') {
                    const selectedText = selection.toString();
                    if (selectedText) {
                        document.execCommand('insertText', false, `_${selectedText}_`);
                    } else {
                        document.execCommand('insertText', false, '__');
                        const range = selection.getRangeAt(0);
                        range.setStart(range.startContainer, range.startOffset - 1);
                        selection.removeAllRanges();
                        selection.addRange(range);
                    }
                }
                updatePreview();
            }

            // Sidebar
            window.toggleSidebar = function() {
                sidebar.classList.toggle('visible');
            }
            btnChooseTemplate.addEventListener('click', toggleSidebar);

            // Selecionar um template
            window.selectTemplate = function(templateName) {
                showFormView();
                formTitle.textContent = `Editando Template: ${templateName}`;

                if (templateName === 'Cobranca_Vencimento') {
                    document.getElementById('nome_modelo').value = 'Cobrança de Fatura';
                    const templateText = `Olá {{1}}! Bem-vindo ao canal oficial da NETCOL no WhatsApp!\n\n✅ Passando para lembrar que sua fatura vence hoje, pagando em dia você garante o seu desconto.\n\n📄 *Fatura:* {{2}}\n💰 *Valor:* {{3}}\n📅 *Vencimento:* {{4}}\n\n💳 Para efetuar o pagamento, clique no botão abaixo.\n📲 Precisa de ajuda? Fale com um de nossos consultores clicando no botão abaixo.\n\n*Se o pagamento já foi realizado, favor desconsiderar esta mensagem.*\n\nA NETCOL agradece a sua parceria! 😉`;
                    
                    // Converte \n para <br> para exibição no editor
                    richTextEditor.innerHTML = templateText.replace(/\n/g, '<br>');
                    updatePreview();

                    document.getElementById('list_title').value = 'Escolha uma opção';
                    document.getElementById('list_button_text').value = 'Ver opções';

                    // Adicionar opções padrão
                    optionCounter = 0;
                    btnAddOption.click();
                    document.querySelector('input[name="list_options[1][title]"]').value = 'PIX COPIA E COLA';
                    document.querySelector('input[name="list_options[1][description]"]').value = 'Gerar código PIX para pagamento';

                    btnAddOption.click();
                    document.querySelector('input[name="list_options[2][title]"]').value = 'FALAR COM ATENDENTE';
                    document.querySelector('input[name="list_options[2][description]"]').value = 'Conversar com nosso suporte';

                } else if (templateName === 'Boas_Vindas') {
                    document.getElementById('nome_modelo').value = 'Mensagem de Boas-Vindas';
                    richTextEditor.innerHTML = 'Olá {{1}}, seja bem-vindo(a) à nossa empresa! Estamos felizes em ter você conosco. 😊';
                    document.getElementById('footer_text').value = 'Equipe de Sucesso do Cliente';
                    updatePreview();
                }
                toggleSidebar();
            }

            // Lógica para o botão de Editar
            document.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const modeloId = this.dataset.id;
                    fetch(`get_modelo_details.php?id=${modeloId}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                const data = result.data;
                                const conteudo = data.conteudo;

                                showFormView();
                                formTitle.textContent = `Editando Modelo: ${data.nome}`;
                                document.getElementById('modelo-id').value = data.id;
                                document.getElementById('nome_modelo').value = data.nome;

                                if (conteudo) {
                                    document.getElementById('canal').value = conteudo.canal || '1';
                                    document.getElementById('categoria').value = conteudo.categoria || 'SERVICE';
                                    document.getElementById('idioma').value = conteudo.idioma || 'pt_BR';

                                    // Converte as quebras de linha (\n) do banco para <br> para exibição correta no editor
                                    const bodyText = conteudo.body || '';
                                    richTextEditor.innerHTML = bodyText.replace(/\n/g, '<br>');
                                    updatePreview();

                                    document.getElementById('footer_text').value = conteudo.footer || '';

                                    if (conteudo.header) {
                                        document.getElementById('header_type').value = conteudo.header.type || 'NONE';
                                        document.getElementById('header_text').value = conteudo.header.text || '';
                                        headerTypeSelect.dispatchEvent(new Event('change'));
                                    }

                                    if (conteudo.action && conteudo.action.list) {
                                        const list = conteudo.action.list;
                                        document.getElementById('list_title').value = list.title || '';
                                        document.getElementById('list_button_text').value = list.button_text || '';
                                        listOptionsContainer.innerHTML = '';
                                        optionCounter = 0;
                                        if (list.options) {
                                            for (const key in list.options) {
                                                if (list.options.hasOwnProperty(key)) {
                                                    const option = list.options[key];
                                                    optionCounter++;
                                                    const optionId = `option_${optionCounter}`;
                                                    const newOption = document.createElement('div');
                                                    newOption.className = 'list-option-item mb-2';
                                                    newOption.id = optionId;
                                                    newOption.innerHTML = `
                                                        <div class="flex-grow-1">
                                                            <input type="text" name="list_options[${optionCounter}][title]" class="form-control form-control-sm" placeholder="Título da Opção ${optionCounter}" value="${option.title || ''}" required>
                                                            <input type="text" name="list_options[${optionCounter}][description]" class="form-control form-control-sm mt-1" placeholder="Descrição (opcional)" value="${option.description || ''}">
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('${optionId}').remove()"><i class="fas fa-times"></i></button>
                                                    `;
                                                    listOptionsContainer.appendChild(newOption);
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                alert('Erro ao carregar os dados do modelo: ' + result.message);
                            }
                        })
                        .catch(error => {
                            console.error('Erro:', error);
                            alert('Erro ao carregar os dados do modelo.');
                        });
                });
            });

            // Lógica para o botão de Excluir
            document.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modeloId = this.dataset.id;

                    if (confirm('Tem certeza que deseja excluir este modelo? Esta ação não pode ser desfeita.')) {
                        const formData = new FormData();
                        formData.append('id', modeloId);

                        fetch(`excluir_modelo.php`, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(result => {
                                if (result.success) {
                                    alert('Modelo excluído com sucesso!');
                                    location.reload();
                                } else {
                                    alert('Erro ao excluir modelo: ' + (result.message || 'Erro desconhecido.'));
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                alert('Ocorreu um erro na comunicação com o servidor.');
                            });
                    }
                });
            });

            // Adiciona suporte para copiar/colar com formatação básica
            richTextEditor.addEventListener('paste', function(e) {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text/plain');
                // Insere o texto como texto puro, que será normalizado corretamente
                document.execCommand('insertText', false, text);
                // A normalização já é chamada pelo evento 'input'
            });

            // Adiciona suporte para arrastar e soltar texto
            richTextEditor.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            richTextEditor.addEventListener('drop', function(e) {
                e.preventDefault();
                const text = e.dataTransfer.getData('text/plain');
                document.execCommand('insertText', false, text);
            });

            // Adiciona suporte para teclas de atalho
            richTextEditor.addEventListener('keydown', function(e) {
                // Ctrl+B para negrito
                if (e.ctrlKey && e.key === 'b') {
                    e.preventDefault();
                    formatText('bold');
                }
                // Ctrl+I para itálico
                else if (e.ctrlKey && e.key === 'i') {
                    e.preventDefault();
                    formatText('italic');
                }
                // Enter para nova linha
                else if (e.key === 'Enter') {
                    e.preventDefault();
                    document.execCommand('insertHTML', false, '<br>');
                }
            });

            // Inicializa o preview
            updatePreview();
        });
    </script>
</body>

</html>
