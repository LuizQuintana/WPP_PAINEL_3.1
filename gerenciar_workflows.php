<?php
require_once 'config/db.php';

$message = '';

// Processar ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $nome = $_POST['nome'] ?? '';
        $gatilho = $_POST['gatilho'] ?? '';
        $condicoes_json = $_POST['condicoes_json_hidden'] ?? '';
        $acoes_json = $_POST['acoes_json_hidden'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        json_decode($condicoes_json);
        if (json_last_error() !== JSON_ERROR_NONE) $message = "Erro: Condições JSON inválidas.";
        json_decode($acoes_json);
        if (json_last_error() !== JSON_ERROR_NONE) $message = "Erro: Ações JSON inválidas.";

        if (empty($message)) {
            if ($action === 'add') {
                $sql = "INSERT INTO workflows (nome, gatilho, condicoes_json, acoes_json, ativo) VALUES (:nome, :gatilho, :condicoes_json, :acoes_json, :ativo)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(compact('nome', 'gatilho', 'condicoes_json', 'acoes_json', 'ativo'));
                $message = "Workflow adicionado com sucesso!";
            } else {
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $sql = "UPDATE workflows SET nome = :nome, gatilho = :gatilho, condicoes_json = :condicoes_json, acoes_json = :acoes_json, ativo = :ativo WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(compact('nome', 'gatilho', 'condicoes_json', 'acoes_json', 'ativo', 'id'));
                    $message = "Workflow atualizado com sucesso!";
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $sql = "DELETE FROM workflows WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $message = "Workflow excluído com sucesso!";
        }
    }
}

// Buscar workflows existentes
$workflows = [];
try {
    $stmt = $pdo->query("SELECT * FROM workflows ORDER BY nome ASC");
    $workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Erro ao buscar workflows: " . $e->getMessage();
}

// Inclui o cabeçalho UMA VEZ
require_once 'header.php';
?>

<!-- O conteúdo da página começa aqui -->
<div class="main-container">

    <!-- Visão de Formulário (inicialmente oculta) -->
    <div id="form-view" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 id="form-title">Adicionar Novo Workflow</h2>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" id="workflowForm">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="id" id="workflow_id">
                    <input type="hidden" name="condicoes_json_hidden" id="condicoes_json_hidden">
                    <input type="hidden" name="acoes_json_hidden" id="acoes_json_hidden">

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome:</label>
                        <input type="text" id="nome" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="gatilho" class="form-label">Gatilho:</label>
                        <select id="gatilho" name="gatilho" class="form-select" required>
                            <option value="mensagem_recebida">Mensagem Recebida</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condições:</label>
                        <div id="condicoes-container" class="border p-3 rounded mb-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addConditionRow()">
                            <i class="fas fa-plus"></i> Adicionar Condição
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ações:</label>
                        <div id="acoes-container" class="border p-3 rounded mb-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addActionRow()">
                            <i class="fas fa-plus"></i> Adicionar Ação
                        </button>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1" checked>
                        <label class="form-check-label" for="ativo">Ativo</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                    <button type="button" class="btn btn-secondary" id="btn-cancel"><i class="fas fa-times"></i> Cancelar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Visão de Lista (inicialmente visível) -->
    <div id="list-view">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-cogs"></i> Workflows Existentes</h2>
            <button class="btn btn-primary" id="btn-add-new"><i class="fas fa-plus"></i> Adicionar Novo</button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <?php if (empty($workflows)): ?>
                <div class="col">
                    <p>Nenhum workflow cadastrado.</p>
                </div>
            <?php else: ?>
                <?php foreach ($workflows as $workflow): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card workflow-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($workflow['nome']) ?></h5>
                                <p class="card-text mb-1"><strong>Gatilho:</strong> <?= htmlspecialchars($workflow['gatilho']) ?></p>
                                <p class="card-text"><strong>Ativo:</strong> <?= $workflow['ativo'] ? '<span class="badge bg-success">Sim</span>' : '<span class="badge bg-danger">Não</span>' ?></p>
                                <p class="card-text"><strong>Condições:</strong></p>
                                <pre class="bg-dark text-white p-2 rounded small"><?= htmlspecialchars(json_encode(json_decode($workflow['condicoes_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                <p class="card-text"><strong>Ações:</strong></p>
                                <pre class="bg-dark text-white p-2 rounded small"><?= htmlspecialchars(json_encode(json_decode($workflow['acoes_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-warning btn-edit" data-workflow='<?= htmlspecialchars(json_encode($workflow)) ?>'><i class="fas fa-edit"></i> Editar</button>
                                    <a href="whats_workflow.php" class="btn btn-sm btn-info"><i class="fas fa-project-diagram"></i> Designer Visual</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $workflow['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div> <!-- Fim do .main-container -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const listView = document.getElementById('list-view');
    const formView = document.getElementById('form-view');
    const btnAddNew = document.getElementById('btn-add-new');
    const btnCancel = document.getElementById('btn-cancel');
    const formTitle = document.getElementById('form-title');
    const workflowForm = document.getElementById('workflowForm');

    const condicoesContainer = document.getElementById('condicoes-container');
    const condicoesJsonHidden = document.getElementById('condicoes_json_hidden');
    const acoesContainer = document.getElementById('acoes-container');
    const acoesJsonHidden = document.getElementById('acoes_json_hidden');
    let conditionCounter = 0;
    let actionCounter = 0;

    const conditionFields = { 'mensagem_recebida': [ { value: 'body', text: 'Corpo da Mensagem' }, { value: 'from', text: 'Remetente' }, { value: 'category', text: 'Categoria' } ] };
    const operators = [ { value: 'contains', text: 'Contém' }, { value: 'equals', text: 'É igual a' }, { value: 'starts_with', text: 'Começa com' }, { value: 'ends_with', text: 'Termina com' } ];
    const actionTypes = { 'enviar_mensagem': { text: 'Enviar Mensagem', params: [ { name: 'template', label: 'Template (Nome do Modelo)', type: 'text', required: true }, { name: 'variables', label: 'Variáveis (JSON)', type: 'textarea', placeholder: 'Ex: {"1":"Valor1"}' } ] }, 'mksolutions_consult_invoice': { text: 'MK Solutions: Consultar Fatura', params: [ { name: 'client_document', label: 'Documento do Cliente (CPF/CNPJ)', type: 'text', placeholder: 'Ex: {{remetente}} ou 123.456.789-00', required: true }, { name: 'invoice_code', label: 'Código da Fatura (Opcional)', type: 'text', placeholder: 'Ex: 12345' } ] }, 'mksolutions_open_ticket': { text: 'MK Solutions: Abrir Chamado', params: [ { name: 'client_document', label: 'Documento do Cliente (CPF/CNPJ)', type: 'text', placeholder: 'Ex: {{remetente}} ou 123.456.789-00', required: true }, { name: 'subject', label: 'Assunto do Chamado', type: 'text', placeholder: 'Ex: Problema de Conexão', required: true }, { name: 'description', label: 'Descrição do Chamado', type: 'textarea', placeholder: 'Detalhes do problema', required: true } ] }, 'mksolutions_generate_pix': { text: 'MK Solutions: Gerar PIX', params: [ { name: 'client_document', label: 'Documento do Cliente (CPF/CNPJ)', type: 'text', placeholder: 'Ex: {{remetente}} ou 123.456.789-00' }, { name: 'invoice_code', label: 'Código da Fatura (Opcional)', type: 'text', placeholder: 'Ex: 12345' }, { name: 'client_code', label: 'Código do Cliente (Opcional)', type: 'text', placeholder: 'Ex: 9876' } ] } };

    const showForm = () => { listView.style.display = 'none'; formView.style.display = 'block'; };
    const showList = () => { listView.style.display = 'block'; formView.style.display = 'none'; };

    btnAddNew.addEventListener('click', () => {
        resetForm();
        formTitle.textContent = 'Adicionar Novo Workflow';
        showForm();
    });
    btnCancel.addEventListener('click', showList);

    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function() {
            const workflow = JSON.parse(this.dataset.workflow);
            editWorkflow(workflow);
        });
    });

    window.addConditionRow = (field = '', operator = '', value = '') => {
        conditionCounter++;
        const rowId = `condition-row-${conditionCounter}`;
        const newRow = document.createElement('div');
        newRow.className = 'row g-2 mb-2 align-items-center';
        newRow.id = rowId;
        newRow.innerHTML = `<div class="col-md-4"><select class="form-select form-select-sm condition-field">${conditionFields['mensagem_recebida'].map(f => `<option value="${f.value}" ${f.value === field ? 'selected' : ''}>${f.text}</option>`).join('')}</select></div><div class="col-md-3"><select class="form-select form-select-sm condition-operator">${operators.map(op => `<option value="${op.value}" ${op.value === operator ? 'selected' : ''}>${op.text}</option>`).join('')}</select></div><div class="col-md-4"><input type="text" class="form-control form-control-sm condition-value" placeholder="Valor" value="${value}"></div><div class="col-md-1"><button type="button" class="btn btn-sm btn-danger" onclick="removeElement('${rowId}')"><i class="fas fa-times"></i></button></div>`;
        condicoesContainer.appendChild(newRow);
    };

    window.addActionRow = (type = '', params = {}) => {
        actionCounter++;
        const rowId = `action-row-${actionCounter}`;
        const newRow = document.createElement('div');
        newRow.className = 'action-row border p-3 rounded mb-2';
        newRow.id = rowId;
        newRow.innerHTML = `<div class="row g-2 align-items-center mb-2"><div class="col-md-5"><label class="form-label small">Tipo de Ação:</label><select class="form-select form-select-sm action-type" onchange="updateActionParams(this, '${rowId}')"><option value="">Selecione</option>${Object.keys(actionTypes).map(key => `<option value="${key}" ${key === type ? 'selected' : ''}>${actionTypes[key].text}</option>`).join('')}</select></div><div class="col-md-6 action-params-container"></div><div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-danger" onclick="removeElement('${rowId}')"><i class="fas fa-times"></i></button></div></div>`;
        acoesContainer.appendChild(newRow);
        if (type) updateActionParams(newRow.querySelector('.action-type'), rowId, params);
    };

    window.updateActionParams = (selectElement, rowId, initialParams = {}) => {
        const actionType = selectElement.value;
        const paramsContainer = document.getElementById(rowId).querySelector('.action-params-container');
        paramsContainer.innerHTML = '';
        if (actionType && actionTypes[actionType]) {
            actionTypes[actionType].params.forEach(param => {
                const val = initialParams[param.name] || '';
                let inputHtml = param.type === 'textarea'
                    ? `<textarea class="form-control form-control-sm action-param" data-param-name="${param.name}" placeholder="${param.placeholder || ''}" rows="2">${val}</textarea>`
                    : `<input type="text" class="form-control form-control-sm action-param" data-param-name="${param.name}" placeholder="${param.placeholder || ''}" value="${val}">`;
                paramsContainer.innerHTML += `<div class="mb-1"><label class="form-label small">${param.label}:</label>${inputHtml}</div>`;
            });
        }
    };
    
    window.removeElement = (id) => document.getElementById(id)?.remove();

    const generateJson = (container, hiddenInput) => {
        const items = [];
        if (container === condicoesContainer) {
            container.querySelectorAll('.row').forEach(row => {
                const field = row.querySelector('.condition-field').value;
                const operator = row.querySelector('.condition-operator').value;
                const value = row.querySelector('.condition-value').value;
                if (field && operator) items.push({ field, operator, value });
            });
        } else {
            container.querySelectorAll('.action-row').forEach(row => {
                const type = row.querySelector('.action-type').value;
                if (type) {
                    const params = {};
                    row.querySelectorAll('.action-param').forEach(input => params[input.dataset.paramName] = input.value);
                    items.push({ type, ...params });
                }
            });
        }
        hiddenInput.value = JSON.stringify(items);
    };

    workflowForm.addEventListener('submit', () => {
        generateJson(condicoesContainer, condicoesJsonHidden);
        generateJson(acoesContainer, acoesJsonHidden);
    });

    const resetForm = () => {
        workflowForm.reset();
        document.getElementById('action').value = 'add';
        document.getElementById('workflow_id').value = '';
        condicoesContainer.innerHTML = '';
        acoesContainer.innerHTML = '';
        conditionCounter = 0;
        actionCounter = 0;
    };

    const editWorkflow = (workflow) => {
        resetForm();
        formTitle.textContent = `Editar Workflow: ${workflow.nome}`;
        document.getElementById('action').value = 'edit';
        document.getElementById('workflow_id').value = workflow.id;
        document.getElementById('nome').value = workflow.nome;
        document.getElementById('gatilho').value = workflow.gatilho;
        document.getElementById('ativo').checked = !!parseInt(workflow.ativo);
        try {
            const condicoes = JSON.parse(workflow.condicoes_json || '[]');
            if(Array.isArray(condicoes)) condicoes.forEach(c => addConditionRow(c.field, c.operator, c.value));
        } catch(e) { console.error('JSON Condições inválido', e); }
        try {
            const acoes = JSON.parse(workflow.acoes_json || '[]');
            if(Array.isArray(acoes)) acoes.forEach(a => addActionRow(a.type, a));
        } catch(e) { console.error('JSON Ações inválido', e); }
        showForm();
        window.scrollTo(0, 0);
    };
});
</script>
</body>
</html>