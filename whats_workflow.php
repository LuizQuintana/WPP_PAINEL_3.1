<?php
require_once 'config/db.php';
require_once 'header.php'; // Inclui o header para o tema global e Bootstrap

$workflow_id = $_GET['id'] ?? null;
$workflow_data_json = 'null';

if ($workflow_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM workflows WHERE id = :id");
        $stmt->execute(['id' => $workflow_id]);
        $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workflow) {
            // Verifica se o workflow é do tipo visual
            if ($workflow['gatilho'] === 'visual') {
                $acoes = json_decode($workflow['acoes_json'], true);
                $workflow_data = [
                    'id' => $workflow['id'],
                    'name' => $workflow['nome'],
                    'ativo' => (bool)$workflow['ativo'], // Adiciona o status ativo
                    'nodes' => $acoes['nodes'] ?? [],
                    'connections' => $acoes['connections'] ?? []
                ];
                $workflow_data_json = json_encode($workflow_data);
            } else {
                // Se não for, exibe um erro e interrompe o carregamento
                die('<h2>Erro: Não é possível editar este workflow no editor visual.</h2><p>Este workflow foi criado com o sistema antigo. Apenas workflows criados com o editor visual podem ser editados aqui.</p><a href="gerenciar_workflows.php" class="btn btn-primary mt-3">Voltar para a lista</a>');
            }
        }
    } catch (PDOException $e) {
        die('Erro ao carregar o workflow: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark"> <!-- Garante o tema escuro -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Workflow Visual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- O tema_global.css será carregado pelo header.php -->
    <style>
        /* Estilos específicos para o editor visual, complementando o tema global */
        body {
            overflow: hidden; /* Para o editor ocupar a tela toda */
        }
        .editor-container {
            display: flex;
            height: calc(100vh - 56px); /* Ajusta para a altura do header */
            margin-top: 56px; /* Espaço para o header */
        }
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: var(--bg-main); /* Cor do tema global */
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
        }
        .toolbar-left, .toolbar-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        #workflow-name {
            background-color: var(--bg-item);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
        }
        #workflow-name:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .palette {
            width: 280px;
            background-color: var(--bg-panel);
            padding: 15px;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            flex-shrink: 0;
        }
        .palette h3 {
            color: var(--text-main);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .palette-section h4 {
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .palette-item {
            background-color: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 10px;
            margin-bottom: 8px;
            cursor: grab;
            transition: background-color 0.2s ease, border-color 0.2s ease;
            color: var(--text-main);
        }
        .palette-item:hover {
            background-color: var(--bg-hover);
            border-color: var(--accent-color);
        }
        .canvas {
            flex: 1;
            position: relative;
            background-color: var(--bg-main);
            background-image: radial-gradient(var(--border-color) 1px, transparent 0);
            background-size: 20px 20px;
            overflow: hidden;
        }
        .workflow-block {
            position: absolute;
            width: 200px;
            padding: 15px;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            cursor: move;
            user-select: none;
            background-color: var(--bg-item);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: var(--text-main);
        }
        .workflow-block.selected {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.5);
        }
        .block-header {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--accent-color);
        }
        .block-content {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        .connector {
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background-color: var(--text-secondary);
            border: 1px solid var(--border-color);
            cursor: crosshair;
            z-index: 10;
        }
        .connector:hover {
            background-color: var(--accent-color);
            transform: scale(1.1);
        }
        .connector.input { top: -7px; left: 50%; transform: translateX(-50%); }
        .connector.output { bottom: -7px; left: 50%; transform: translateX(-50%); }
        .svg-connections { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; }
        .connection-line { stroke: var(--accent-color); stroke-width: 2; fill: none; }

        /* Modal */
        .modal-content {
            background-color: var(--bg-panel);
            color: var(--text-main);
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
        }
        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }
        .modal-title {
            color: var(--text-main);
        }
        .btn-close {
            filter: invert(1); /* Para ser visível no tema escuro */
        }
        .form-group label {
            color: var(--text-secondary);
        }
        .form-group input, .form-group textarea, .form-group select {
            background-color: var(--bg-main);
            color: var(--text-main);
            border-color: var(--border-color);
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .form-check-input:checked {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        /* Animação de conexão */
        @keyframes pulse { 0% { transform: scale(1.1); box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.7); } 70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); } 100% { transform: scale(1.1); box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); } }
        .workflow-block.connecting .connector.output { background: var(--accent-color); animation: pulse 1.5s infinite; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="editor-container">
        <div class="toolbar">
            <div class="toolbar-left">
                <input type="text" id="workflow-name" class="form-control form-control-sm" placeholder="Nome do Workflow">
                <div class="form-check form-switch ms-3">
                    <input class="form-check-input" type="checkbox" id="workflow-active">
                    <label class="form-check-label text-white" for="workflow-active">Ativo</label>
                </div>
            </div>
            <div class="toolbar-right">
                <button id="clear-btn" class="btn btn-sm btn-secondary">🗑️ Limpar</button>
                <button id="test-btn" class="btn btn-sm btn-info">🧪 Testar</button>
                <button id="export-btn" class="btn btn-sm btn-dark">📥 Exportar</button>
                <button id="save-btn" class="btn btn-sm btn-primary">💾 Salvar</button>
            </div>
        </div>

        <div class="palette">
            <h3>Componentes</h3>
            <div class="palette-section">
                <h4>Gatilhos</h4>
                <div class="palette-item" draggable="true" data-type="trigger" data-subtype="message_received">📨 Mensagem Recebida</div>
            </div>
            <div class="palette-section">
                <h4>Ações</h4>
                <div class="palette-item" draggable="true" data-type="action" data-subtype="send_message">💬 Enviar Mensagem</div>
                <div class="palette-item" draggable="true" data-type="action" data-subtype="mksolutions_consult_invoice">🧾 Consultar Fatura (MK)</div>
                <div class="palette-item" draggable="true" data-type="action" data-subtype="mksolutions_open_ticket">🎫 Abrir Chamado (MK)</div>
                <!-- Adicionar mais ações MK Solutions conforme necessário -->
            </div>
        </div>

        <div class="canvas" id="canvas">
            <svg class="svg-connections" id="svg-connections"></svg>
        </div>
    </div>

    <div class="modal fade" id="edit-modal" tabindex="-1" aria-labelledby="modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="edit-form">
                    <div class="modal-body">
                        <input type="hidden" id="editing-block-id">
                        <div class="mb-3">
                            <label for="block-label" class="form-label">Nome do Bloco</label>
                            <input type="text" id="block-label" class="form-control">
                        </div>
                        <div id="dynamic-fields"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class WorkflowEditor {
            constructor(initialData) {
                this.canvas = document.getElementById('canvas');
                this.svg = document.getElementById('svg-connections');
                this.blocks = new Map();
                this.connections = [];
                this.blockCounter = 0;
                this.selectedBlockId = null;
                this.connectingInfo = null;
                this.workflowId = initialData?.id || null;
                this.workflowNameInput = document.getElementById('workflow-name');
                this.workflowActiveToggle = document.getElementById('workflow-active');

                this.init();
                if (initialData) {
                    this.load(initialData);
                } else {
                    this.createBlock('trigger', 'message_received', 100, 120, { label: 'Mensagem Recebida' });
                }
            }

            init() {
                this.setupEventListeners();
                // Inicializa o modal do Bootstrap
                this.editModal = new bootstrap.Modal(document.getElementById('edit-modal'));
            }

            setupEventListeners() {
                document.querySelectorAll('.palette-item').forEach(item => {
                    item.addEventListener('dragstart', e => {
                        e.dataTransfer.setData('application/json', JSON.stringify({ type: e.target.dataset.type, subtype: e.target.dataset.subtype }));
                    });
                });

                this.canvas.addEventListener('dragover', e => e.preventDefault());
                this.canvas.addEventListener('drop', e => {
                    e.preventDefault();
                    const data = JSON.parse(e.dataTransfer.getData('application/json'));
                    const rect = this.canvas.getBoundingClientRect();
                    this.createBlock(data.type, data.subtype, e.clientX - rect.left, e.clientY - rect.top);
                });

                this.canvas.addEventListener('click', () => this.clearSelection());
                document.getElementById('save-btn').addEventListener('click', () => this.save());
                document.getElementById('export-btn').addEventListener('click', () => this.export());
                document.getElementById('clear-btn').addEventListener('click', () => this.clear());
                document.getElementById('test-btn').addEventListener('click', () => alert('Teste não implementado.'));

                // Modal listeners
                document.getElementById('edit-form').addEventListener('submit', e => this.handleFormSubmit(e));
            }

            createBlock(type, subtype, x, y, data = {}, id = null) {
                const blockId = id || `block-${this.blockCounter++}`;
                if (!id) this.blockCounter = Math.max(this.blockCounter, parseInt(blockId.split('-')[1] || 0) + 1);

                const element = document.createElement('div');
                element.id = blockId;
                element.className = `workflow-block ${type}`;
                element.style.left = `${x}px`;
                element.style.top = `${y}px`;

                element.innerHTML = `
                    <div class="block-header"></div>
                    <div class="block-content"></div>
                    <div class="connector input"></div>
                    <div class="connector output"></div>
                `;

                this.canvas.appendChild(element);
                const block = { id: blockId, element, type, subtype, data: { ...data }, connections: { from: [], to: [] } };
                this.blocks.set(blockId, block);
                this.updateBlockContent(block);
                this.setupBlockEventListeners(element, blockId);
                return block;
            }

            updateBlockContent(block) {
                const config = this.getBlockConfig(block.type, block.subtype);
                block.element.querySelector('.block-header').textContent = `${config.icon} ${block.data.label || config.title}`;
                let description = config.description;
                if (block.subtype === 'send_message') description = block.data.template ? `Template: ${block.data.template}` : 'Clique duplo para editar';
                if (block.subtype.startsWith('mksolutions_')) description = `MK: ${block.data.action_type || 'não definido'}`;
                block.element.querySelector('.block-content').textContent = description;
            }

            setupBlockEventListeners(element, id) {
                element.addEventListener('click', e => { e.stopPropagation(); this.selectBlock(id); });
                element.addEventListener('dblclick', e => { e.stopPropagation(); this.openModal(id); });

                // Dragging
                let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
                element.onmousedown = e => {
                    if (e.target.classList.contains('connector')) return;
                    e.preventDefault();
                    pos3 = e.clientX; pos4 = e.clientY;
                    document.onmouseup = () => { document.onmouseup = null; document.onmousemove = null; };
                    document.onmousemove = move_e => {
                        move_e.preventDefault();
                        pos1 = pos3 - move_e.clientX; pos2 = pos4 - move_e.clientY;
                        pos3 = move_e.clientX; pos4 = move_e.clientY;
                        element.style.top = `${element.offsetTop - pos2}px`;
                        element.style.left = `${element.offsetLeft - pos1}px`;
                        this.updateConnections();
                    };
                };

                // Connectors
                element.querySelector('.connector.output').addEventListener('click', e => {
                    e.stopPropagation();
                    element.classList.add('connecting');
                    this.connectingInfo = { fromId: id };
                });
                element.querySelector('.connector.input').addEventListener('click', e => {
                    e.stopPropagation();
                    if (this.connectingInfo && this.connectingInfo.fromId !== id) {
                        this.createConnection(this.connectingInfo.fromId, id);
                        this.clearSelection();
                    }
                });
            }

            createConnection(fromId, toId) {
                if (this.connections.some(c => c.from === fromId && c.to === toId)) return;
                this.connections.push({ from: fromId, to: toId });
                this.updateConnections();
            }

            updateConnections() {
                this.svg.innerHTML = '';
                this.connections.forEach(conn => {
                    const fromBlock = this.blocks.get(conn.from);
                    const toBlock = this.blocks.get(conn.to);
                    if (!fromBlock || !toBlock) return;

                    const rect = this.canvas.getBoundingClientRect();
                    const fromRect = fromBlock.element.querySelector('.connector.output').getBoundingClientRect();
                    const toRect = toBlock.element.querySelector('.connector.input').getBoundingClientRect();

                    const startX = fromRect.left + fromRect.width / 2 - rect.left;
                    const startY = fromRect.top + fromRect.height / 2 - rect.top;
                    const endX = toRect.left + toRect.width / 2 - rect.left;
                    const endY = toRect.top + toRect.height / 2 - rect.top;

                    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    const d = `M ${startX} ${startY} C ${startX} ${startY + 50}, ${endX} ${endY - 50}, ${endX} ${endY}`;
                    path.setAttribute('d', d);
                    path.setAttribute('class', 'connection-line');
                    this.svg.appendChild(path);
                });
            }

            selectBlock(id) {
                this.clearSelection();
                if (id) {
                    this.selectedBlockId = id;
                    this.blocks.get(id).element.classList.add('selected');
                }
            }

            clearSelection() {
                if (this.selectedBlockId) {
                    this.blocks.get(this.selectedBlockId)?.element.classList.remove('selected');
                    this.selectedBlockId = null;
                }
                if (this.connectingInfo) {
                    this.blocks.get(this.connectingInfo.fromId)?.element.classList.remove('connecting');
                    this.connectingInfo = null;
                }
            }

            openModal(id) {
                const block = this.blocks.get(id);
                if (!block) return;
                const config = this.getBlockConfig(block.type, block.subtype);
                document.getElementById('modal-title').textContent = `Editar: ${config.title}`;
                document.getElementById('editing-block-id').value = id;
                document.getElementById('block-label').value = block.data.label || config.title;

                const dynamicFields = document.getElementById('dynamic-fields');
                dynamicFields.innerHTML = '';

                if (block.subtype === 'send_message') {
                    dynamicFields.innerHTML = `
                        <div class="mb-3">
                            <label for="message-template" class="form-label">Template de Mensagem</label>
                            <input type="text" id="message-template" class="form-control" value="${block.data.template || ''}" placeholder="Nome do template">
                        </div>
                        <div class="mb-3">
                            <label for="message-variables" class="form-label">Variáveis (JSON)</label>
                            <textarea id="message-variables" class="form-control" rows="3" placeholder="Ex: {\"1\":\"Valor1\"}">${JSON.stringify(block.data.variables || {}, null, 2)}</textarea>
                        </div>
                    `;
                } else if (block.subtype.startsWith('mksolutions_')) {
                    let mkFieldsHtml = '';
                    if (block.subtype === 'mksolutions_consult_invoice') {
                        mkFieldsHtml = `
                            <div class="mb-3">
                                <label for="mk-invoice-client-id" class="form-label">ID do Cliente (MK)</label>
                                <input type="text" id="mk-invoice-client-id" class="form-control" value="${block.data.client_id || ''}" placeholder="Ex: {{1}}">
                            </div>
                            <div class="mb-3">
                                <label for="mk-invoice-number" class="form-label">Número da Fatura</label>
                                <input type="text" id="mk-invoice-number" class="form-control" value="${block.data.invoice_number || ''}" placeholder="Opcional">
                            </div>
                        `;
                    } else if (block.subtype === 'mksolutions_open_ticket') {
                        mkFieldsHtml = `
                            <div class="mb-3">
                                <label for="mk-ticket-client-id" class="form-label">ID do Cliente (MK)</label>
                                <input type="text" id="mk-ticket-client-id" class="form-control" value="${block.data.client_id || ''}" placeholder="Ex: {{1}}">
                            </div>
                            <div class="mb-3">
                                <label for="mk-ticket-subject" class="form-label">Assunto do Chamado</label>
                                <input type="text" id="mk-ticket-subject" class="form-control" value="${block.data.subject || ''}" placeholder="Ex: Problema de Conexão">
                            </div>
                            <div class="mb-3">
                                <label for="mk-ticket-description" class="form-label">Descrição do Chamado</label>
                                <textarea id="mk-ticket-description" class="form-control" rows="3" placeholder="Detalhes do problema">${block.data.description || ''}</textarea>
                            </div>
                        `;
                    }
                    dynamicFields.innerHTML = mkFieldsHtml;
                }
                this.editModal.show();
            }

            closeModal() { this.editModal.hide(); }

            handleFormSubmit(e) {
                e.preventDefault();
                const id = document.getElementById('editing-block-id').value;
                const block = this.blocks.get(id);
                if (!block) return;
                block.data.label = document.getElementById('block-label').value;

                if (block.subtype === 'send_message') {
                    block.data.template = document.getElementById('message-template').value;
                    try {
                        block.data.variables = JSON.parse(document.getElementById('message-variables').value);
                    } catch (e) {
                        alert("Formato JSON inválido para variáveis.");
                        return;
                    }
                } else if (block.subtype === 'mksolutions_consult_invoice') {
                    block.data.client_id = document.getElementById('mk-invoice-client-id').value;
                    block.data.invoice_number = document.getElementById('mk-invoice-number').value;
                } else if (block.subtype === 'mksolutions_open_ticket') {
                    block.data.client_id = document.getElementById('mk-ticket-client-id').value;
                    block.data.subject = document.getElementById('mk-ticket-subject').value;
                    block.data.description = document.getElementById('mk-ticket-description').value;
                }
                this.updateBlockContent(block);
                this.closeModal();
            }

            getBlockConfig(type, subtype) {
                const configs = {
                    trigger: { message_received: { icon: '📨', title: 'Mensagem Recebida', description: 'Inicia ao receber mensagem.' } },
                    action: {
                        send_message: { icon: '💬', title: 'Enviar Mensagem', description: 'Envia uma mensagem de texto.' },
                        mksolutions_consult_invoice: { icon: '🧾', title: 'Consultar Fatura (MK)', description: 'Consulta fatura de um cliente no MK Solutions.' },
                        mksolutions_open_ticket: { icon: '🎫', title: 'Abrir Chamado (MK)', description: 'Abre um chamado no MK Solutions.' }
                    }
                };
                return configs[type]?.[subtype] || { icon: '❓', title: 'Desconhecido' };
            }

            serialize() {
                const nodes = Array.from(this.blocks.values()).map(b => ({
                    id: b.id, type: b.type, subtype: b.subtype, data: b.data,
                    position: { left: b.element.style.left, top: b.element.style.top }
                }));
                return { 
                    id: this.workflowId, 
                    name: this.workflowNameInput.value, 
                    ativo: this.workflowActiveToggle.checked, // Inclui o status ativo
                    gatilho: 'visual', // Define o gatilho como visual
                    nodes, 
                    connections: this.connections 
                };
            }

            async save() {
                const data = this.serialize();
                const btn = document.getElementById('save-btn');
                btn.textContent = 'Salvando...'; btn.disabled = true;
                try {
                    const response = await fetch('api/salvar_workflow.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    if (result.success) {
                        if (result.new_id) {
                            this.workflowId = result.new_id;
                            window.history.replaceState(null, null, `?id=${result.new_id}`);
                        }
                        alert(result.message);
                    } else { alert(`Erro: ${result.message}`); }
                } catch (err) { console.error("Erro ao salvar:", err); alert('Erro de comunicação.'); } 
                finally { btn.textContent = '💾 Salvar'; btn.disabled = false; }
            }

            export() {
                const data = JSON.stringify(this.serialize(), null, 2);
                const blob = new Blob([data], { type: 'application/json' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `${this.workflowNameInput.value.replace(/ /g, '_') || 'workflow'}.json`;
                a.click();
                URL.revokeObjectURL(a.href);
            }

            clear(internal = false) {
                if (!internal && !confirm('Limpar todo o workflow?')) return;
                this.blocks.forEach(b => b.element.remove());
                this.blocks.clear();
                this.connections = [];
                this.updateConnections();
                this.blockCounter = 0;
                if (!internal) {
                    this.workflowId = null;
                    window.history.replaceState(null, null, window.location.pathname);
                    this.workflowNameInput.value = 'Novo Workflow';
                    this.workflowActiveToggle.checked = true; // Reset ativo
                    this.createBlock('trigger', 'message_received', 100, 120, { label: 'Mensagem Recebida' });
                }
            }

            load(data) {
                this.clear(true);
                this.workflowId = data.id;
                this.workflowNameInput.value = data.name;
                this.workflowActiveToggle.checked = data.ativo; // Carrega o status ativo
                data.nodes?.forEach(n => this.createBlock(n.type, n.subtype, parseInt(n.position.left), parseInt(n.position.top), n.data, n.id));
                this.connections = data.connections || [];
                this.updateConnections();
            }
        }

        new WorkflowEditor(<?php echo $workflow_data_json; ?>);
    </script>
</body>
</html>