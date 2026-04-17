<?php
$tableData = is_array($table ?? null) ? $table : [];
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$formAction = (string) ($formAction ?? base_url('/admin/tables/store'));
$submitLabel = (string) ($submitLabel ?? 'Salvar mesa');
$numberValue = isset($tableData['number']) ? (string) (int) $tableData['number'] : '';
$capacityValue = isset($tableData['capacity']) && $tableData['capacity'] !== null ? (string) (int) $tableData['capacity'] : '';
$nameValue = trim((string) ($tableData['name'] ?? ''));
$currentStatus = (string) ($tableData['status'] ?? 'livre');
if ($currentStatus === '') {
    $currentStatus = 'livre';
}

$statusOptions = [
    'livre' => [
        'label' => 'Livre',
        'description' => 'Mesa disponivel para abrir nova comanda.',
    ],
    'ocupada' => [
        'label' => 'Ocupada',
        'description' => 'Mesa em atendimento com consumo ativo.',
    ],
    'aguardando_fechamento' => [
        'label' => 'Aguardando fechamento',
        'description' => 'Mesa aguardando conferencia e encerramento.',
    ],
    'bloqueada' => [
        'label' => 'Bloqueada',
        'description' => 'Mesa fora de operacao temporariamente.',
    ],
];
?>

<style>
    .table-form-layout{display:grid;grid-template-columns:1.3fr 1fr;gap:16px}
    .table-form-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
    .steps{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .step-pill{padding:4px 10px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:12px}
    .status-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .status-option{position:relative;border:1px solid #cbd5e1;border-radius:10px;padding:10px;background:#f8fafc;cursor:pointer}
    .status-option input{position:absolute;opacity:0;pointer-events:none}
    .status-option strong{display:block;font-size:13px}
    .status-option small{display:block;color:#64748b;margin-top:3px;font-size:12px;line-height:1.3}
    .status-option.active{border-color:#1d4ed8;background:#dbeafe}
    .meta-list{display:grid;gap:8px;margin-top:10px}
    .meta-item{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .meta-item strong{display:block;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
    .meta-item span{font-size:15px;color:#111827}
    .actions-stack{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .next-actions{margin-top:12px;padding:10px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff}
    .next-actions p{margin:0;color:#334155}
    @media (max-width:980px){
        .table-form-layout{grid-template-columns:1fr}
        .status-grid{grid-template-columns:1fr}
    }
</style>

<div class="topbar">
    <div>
        <h1><?= $isEdit ? 'Editar Mesa' : 'Nova Mesa' ?></h1>
        <p>Formulario operacional no mesmo padrão visual de cadastro de produtos.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/tables')) ?>">Voltar ao painel</a>
</div>

<form method="POST" action="<?= htmlspecialchars($formAction) ?>">
    <?= form_security_fields($isEdit ? 'tables.update' : 'tables.store') ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="table_id" value="<?= (int) ($tableData['id'] ?? 0) ?>">
    <?php endif; ?>

    <div class="table-form-layout">
        <div class="table-form-card">
            <div class="steps">
                <span class="step-pill">1. Identificacao</span>
                <span class="step-pill">2. Capacidade</span>
            </div>

            <div class="grid two">
                <div class="field">
                    <label for="number">Numero da mesa</label>
                    <input id="number" name="number" type="number" min="1" required value="<?= htmlspecialchars($numberValue) ?>" placeholder="Ex.: 12">
                </div>

                <div class="field">
                    <label for="capacity">Capacidade</label>
                    <input id="capacity" name="capacity" type="number" min="1" value="<?= htmlspecialchars($capacityValue) ?>" placeholder="Ex.: 4">
                </div>
            </div>

            <div class="field">
                <label for="name">Nome da mesa</label>
                <input id="name" name="name" type="text" value="<?= htmlspecialchars($nameValue) ?>" placeholder="Ex.: Varanda 01">
            </div>
        </div>

        <div class="table-form-card">
            <div class="steps">
                <span class="step-pill">3. Status operacional</span>
            </div>

            <div class="status-grid" style="margin-top:8px">
                <?php foreach ($statusOptions as $value => $option): ?>
                    <label class="status-option">
                        <input
                            type="radio"
                            name="status"
                            value="<?= htmlspecialchars($value) ?>"
                            <?= $currentStatus === $value ? 'checked' : '' ?>
                        >
                        <strong><?= htmlspecialchars((string) ($option['label'] ?? $value)) ?></strong>
                        <small><?= htmlspecialchars((string) ($option['description'] ?? '')) ?></small>
                    </label>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:14px">
                <button class="btn" type="submit"><?= htmlspecialchars($submitLabel) ?></button>
            </div>

            <div class="next-actions">
                <strong style="display:block;margin-bottom:6px">Resumo da configuracao</strong>
                <p>Revise os dados da mesa antes de salvar.</p>
                <div class="meta-list">
                    <div class="meta-item">
                        <strong>Numero</strong>
                        <span id="summaryNumber"><?= $numberValue !== '' ? htmlspecialchars($numberValue) : '-' ?></span>
                    </div>
                    <div class="meta-item">
                        <strong>Nome</strong>
                        <span id="summaryName"><?= htmlspecialchars($nameValue !== '' ? $nameValue : 'Não informado') ?></span>
                    </div>
                    <div class="meta-item">
                        <strong>Capacidade</strong>
                        <span id="summaryCapacity"><?= $capacityValue !== '' ? htmlspecialchars($capacityValue . ' pessoa(s)') : 'Não informada' ?></span>
                    </div>
                    <div class="meta-item">
                        <strong>Status</strong>
                        <span id="summaryStatus"><?= htmlspecialchars((string) ($statusOptions[$currentStatus]['label'] ?? 'Livre')) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="actions-stack">
                    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/tables/print-qr?table_id=' . (int) ($tableData['id'] ?? 0))) ?>">QR para imprimir</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
(() => {
    const numberInput = document.getElementById('number');
    const capacityInput = document.getElementById('capacity');
    const nameInput = document.getElementById('name');

    const summaryNumber = document.getElementById('summaryNumber');
    const summaryName = document.getElementById('summaryName');
    const summaryCapacity = document.getElementById('summaryCapacity');
    const summaryStatus = document.getElementById('summaryStatus');

    const statusOptions = {
        livre: 'Livre',
        ocupada: 'Ocupada',
        aguardando_fechamento: 'Aguardando fechamento',
        bloqueada: 'Bloqueada',
    };

    const refreshStatusOptions = () => {
        document.querySelectorAll('.status-option').forEach((option) => {
            const control = option.querySelector('input[type="radio"]');
            option.classList.toggle('active', !!control && control.checked);
        });
    };

    const refreshSummary = () => {
        const numberValue = numberInput ? String(numberInput.value || '').trim() : '';
        const nameValue = nameInput ? String(nameInput.value || '').trim() : '';
        const capacityValue = capacityInput ? String(capacityInput.value || '').trim() : '';
        const selectedStatus = document.querySelector('input[name="status"]:checked');
        const statusValue = selectedStatus ? String(selectedStatus.value || '') : 'livre';

        if (summaryNumber) {
            summaryNumber.textContent = numberValue !== '' ? numberValue : '-';
        }

        if (summaryName) {
            summaryName.textContent = nameValue !== '' ? nameValue : 'Não informado';
        }

        if (summaryCapacity) {
            summaryCapacity.textContent = capacityValue !== '' ? `${capacityValue} pessoa(s)` : 'Não informada';
        }

        if (summaryStatus) {
            summaryStatus.textContent = statusOptions[statusValue] || 'Livre';
        }
    };

    if (nameInput) {
        nameInput.addEventListener('input', () => {
            nameInput.dataset.touched = '1';
            refreshSummary();
        });
    }

    if (numberInput) {
        numberInput.addEventListener('input', () => {
            const numberValue = String(numberInput.value || '').trim();
            if (nameInput && nameInput.dataset.touched !== '1' && numberValue !== '') {
                nameInput.value = `Mesa ${String(numberValue).padStart(2, '0')}`;
            }
            refreshSummary();
        });
    }

    if (capacityInput) {
        capacityInput.addEventListener('input', refreshSummary);
    }

    document.querySelectorAll('input[name="status"]').forEach((input) => {
        input.addEventListener('change', () => {
            refreshStatusOptions();
            refreshSummary();
        });
    });

    refreshStatusOptions();
    refreshSummary();
})();
</script>
