<?php
$tables = is_array($tables ?? null) ? $tables : [];
$totalTables = count($tables);
$freeTables = 0;
$busyTables = 0;
$waitingTables = 0;

foreach ($tables as $table) {
    $status = (string) ($table['status'] ?? '');
    if ($status === 'livre') {
        $freeTables++;
    } elseif ($status === 'ocupada') {
        $busyTables++;
    } elseif ($status === 'aguardando_fechamento') {
        $waitingTables++;
    }
}
?>

<style>
    .command-open-page{display:grid;gap:16px}
    .kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:12px}
    .kpi-item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .kpi-item strong{display:block;font-size:24px;line-height:1.1}
    .kpi-item span{color:#64748b;font-size:12px}
    .command-form-layout{display:grid;grid-template-columns:1.3fr 1fr;gap:16px}
    .command-form-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
    .steps{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .step-pill{padding:4px 10px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:12px}
    .meta-list{display:grid;gap:8px;margin-top:10px}
    .meta-item{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .meta-item strong{display:block;font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.03em}
    .meta-item span{font-size:15px;color:#111827}
    .next-actions{margin-top:12px;padding:10px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff}
    .next-actions p{margin:0;color:#334155}
    @media (max-width:980px){
        .kpi-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}
        .command-form-layout{grid-template-columns:1fr}
    }
</style>

<div class="command-open-page">
    <div class="topbar">
        <div>
            <h1>Abrir Comanda</h1>
            <p>Formulario operacional no mesmo padrão visual de produtos e mesas.</p>
        </div>
        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/commands')) ?>">Voltar para comandas</a>
    </div>

    <div class="kpi-grid">
        <div class="kpi-item"><strong><?= $totalTables ?></strong><span>Mesas selecionaveis</span></div>
        <div class="kpi-item"><strong><?= $freeTables ?></strong><span>Mesas livres</span></div>
        <div class="kpi-item"><strong><?= $busyTables ?></strong><span>Mesas ocupadas</span></div>
        <div class="kpi-item"><strong><?= $waitingTables ?></strong><span>Aguardando fechamento</span></div>
    </div>

    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/commands/store')) ?>">
        <?= form_security_fields('commands.store') ?>

        <div class="command-form-layout">
            <div class="command-form-card">
                <div class="steps">
                    <span class="step-pill">1. Mesa</span>
                    <span class="step-pill">2. Cliente</span>
                    <span class="step-pill">3. Observações</span>
                </div>

                <div class="grid two">
                    <div class="field">
                        <label for="table_id">Mesa</label>
                        <select id="table_id" name="table_id" required>
                            <?php if (empty($tables)): ?>
                                <option value="">Nenhuma mesa disponivel para abertura</option>
                            <?php else: ?>
                                <option value="">Selecione</option>
                                <?php foreach ($tables as $table): ?>
                                    <?php
                                    $tableId = (int) ($table['id'] ?? 0);
                                    $tableNumber = (int) ($table['number'] ?? 0);
                                    $tableName = trim((string) ($table['name'] ?? ''));
                                    $tableStatus = (string) ($table['status'] ?? '');
                                    $tableCapacity = $table['capacity'] !== null ? (int) $table['capacity'] : null;
                                    ?>
                                    <option
                                        value="<?= $tableId ?>"
                                        data-table-number="<?= $tableNumber ?>"
                                        data-table-name="<?= htmlspecialchars($tableName !== '' ? $tableName : 'Mesa ' . str_pad((string) $tableNumber, 2, '0', STR_PAD_LEFT)) ?>"
                                        data-table-status="<?= htmlspecialchars(status_label('table_status', $tableStatus)) ?>"
                                        data-table-capacity="<?= $tableCapacity !== null ? $tableCapacity : '' ?>"
                                    >
                                        Mesa <?= $tableNumber ?> - <?= htmlspecialchars($tableName !== '' ? $tableName : 'Sem nome') ?>
                                        (<?= htmlspecialchars(status_label('table_status', $tableStatus)) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small style="color:#64748b">Mesas bloqueadas nao ficam disponiveis para selecao.</small>
                    </div>

                    <div class="field">
                        <label for="customer_name">Nome do cliente</label>
                        <input id="customer_name" name="customer_name" type="text" required placeholder="Ex.: Joao da Silva">
                    </div>
                </div>

                <div class="field">
                    <label for="notes">Observações</label>
                    <textarea id="notes" name="notes" rows="4" placeholder="Ex.: Cliente prefere atendimento rapido e sem cebola."></textarea>
                </div>

                <button class="btn" type="submit" <?= empty($tables) ? 'disabled' : '' ?>>Abrir comanda</button>
            </div>

            <div class="command-form-card">
                <div class="steps">
                    <span class="step-pill">Resumo</span>
                </div>

                <div class="next-actions">
                    <p>Revise os dados antes de confirmar a abertura da comanda.</p>
                </div>

                <div class="meta-list">
                    <div class="meta-item">
                        <strong>Mesa selecionada</strong>
                        <span id="summaryTable">Nenhuma mesa selecionada</span>
                    </div>
                    <div class="meta-item">
                        <strong>Status da mesa</strong>
                        <span id="summaryStatus">-</span>
                    </div>
                    <div class="meta-item">
                        <strong>Capacidade</strong>
                        <span id="summaryCapacity">-</span>
                    </div>
                    <div class="meta-item">
                        <strong>Cliente</strong>
                        <span id="summaryCustomer">Não informado</span>
                    </div>
                    <div class="meta-item">
                        <strong>Observações</strong>
                        <span id="summaryNotes">Sem observações</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(() => {
    const tableSelect = document.getElementById('table_id');
    const customerInput = document.getElementById('customer_name');
    const notesInput = document.getElementById('notes');
    const summaryTable = document.getElementById('summaryTable');
    const summaryStatus = document.getElementById('summaryStatus');
    const summaryCapacity = document.getElementById('summaryCapacity');
    const summaryCustomer = document.getElementById('summaryCustomer');
    const summaryNotes = document.getElementById('summaryNotes');

    const updateSummary = () => {
        const selected = tableSelect instanceof HTMLSelectElement
            ? tableSelect.options[tableSelect.selectedIndex] || null
            : null;

        if (selected && selected.value !== '') {
            const number = String(selected.getAttribute('data-table-number') || '').trim();
            const name = String(selected.getAttribute('data-table-name') || '').trim();
            const status = String(selected.getAttribute('data-table-status') || '').trim();
            const capacity = String(selected.getAttribute('data-table-capacity') || '').trim();

            if (summaryTable) {
                summaryTable.textContent = number !== '' ? `Mesa ${number} - ${name}` : name;
            }
            if (summaryStatus) {
                summaryStatus.textContent = status !== '' ? status : '-';
            }
            if (summaryCapacity) {
                summaryCapacity.textContent = capacity !== '' ? `${capacity} pessoa(s)` : 'Não informada';
            }
        } else {
            if (summaryTable) {
                summaryTable.textContent = 'Nenhuma mesa selecionada';
            }
            if (summaryStatus) {
                summaryStatus.textContent = '-';
            }
            if (summaryCapacity) {
                summaryCapacity.textContent = '-';
            }
        }

        if (summaryCustomer) {
            const customerValue = customerInput instanceof HTMLInputElement ? customerInput.value.trim() : '';
            summaryCustomer.textContent = customerValue !== '' ? customerValue : 'Não informado';
        }

        if (summaryNotes) {
            const notesValue = notesInput instanceof HTMLTextAreaElement ? notesInput.value.trim() : '';
            summaryNotes.textContent = notesValue !== '' ? notesValue : 'Sem observações';
        }
    };

    if (tableSelect) {
        tableSelect.addEventListener('change', updateSummary);
    }
    if (customerInput) {
        customerInput.addEventListener('input', updateSummary);
    }
    if (notesInput) {
        notesInput.addEventListener('input', updateSummary);
    }

    updateSummary();
})();
</script>
