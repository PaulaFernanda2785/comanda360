<div class="topbar">
    <div>
        <h1>Abrir Comanda</h1>
        <p>Abertura manual de comanda por mesa.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/commands')) ?>">Voltar</a>
</div>

<div class="card">
    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/commands/store')) ?>">
        <div class="grid two">
            <div class="field">
                <label for="table_id">Mesa</label>
                <select id="table_id" name="table_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($tables as $table): ?>
                        <option value="<?= (int)$table['id'] ?>">
                            Mesa <?= (int)$table['number'] ?> - <?= htmlspecialchars($table['name']) ?> (<?= htmlspecialchars($table['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="customer_name">Nome do cliente</label>
                <input id="customer_name" name="customer_name" type="text" required>
            </div>
        </div>

        <div class="field">
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" rows="4"></textarea>
        </div>

        <button class="btn" type="submit">Abrir comanda</button>
    </form>
</div>
