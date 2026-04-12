<?php
$productsJson = json_encode($products ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
if (!is_string($productsJson)) {
    $productsJson = '[]';
}
?>

<div class="topbar">
    <div>
        <h1>Novo Pedido</h1>
        <p>Criar pedido vinculado a uma comanda aberta.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Voltar</a>
</div>

<div class="card">
    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/store')) ?>">
        <div class="grid two">
            <div class="field">
                <label for="command_id">Comanda aberta</label>
                <select id="command_id" name="command_id" required>
                    <option value="">Selecione</option>
                    <?php foreach (($commands ?? []) as $command): ?>
                        <option value="<?= (int) $command['id'] ?>">
                            #<?= (int) $command['id'] ?>
                            <?= $command['table_number'] !== null ? '- Mesa ' . (int) $command['table_number'] : '' ?>
                            <?= !empty($command['customer_name']) ? '- ' . htmlspecialchars((string) $command['customer_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="notes">Observacoes gerais do pedido</label>
                <input id="notes" name="notes" type="text" placeholder="Opcional">
            </div>
        </div>

        <div class="grid two">
            <div class="field">
                <label for="discount_amount">Desconto (R$)</label>
                <input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0" value="0.00">
            </div>

            <div class="field">
                <label for="delivery_fee">Taxa de entrega (R$)</label>
                <input id="delivery_fee" name="delivery_fee" type="number" step="0.01" min="0" value="0.00">
            </div>
        </div>

        <h3>Itens do pedido</h3>
        <p>Adicione os itens com produto, quantidade e observacao por linha.</p>

        <table id="itemsTable">
            <thead>
                <tr>
                    <th style="width:42%">Produto</th>
                    <th style="width:14%">Qtd</th>
                    <th style="width:34%">Observacao</th>
                    <th style="width:10%">Acao</th>
                </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
        </table>

        <div style="margin-top:12px">
            <button class="btn secondary" id="addItemBtn" type="button">Adicionar item</button>
        </div>

        <div style="margin-top:16px">
            <button class="btn" type="submit">Criar pedido</button>
        </div>
    </form>
</div>

<script>
(() => {
    const products = <?= $productsJson ?>;
    const tbody = document.getElementById('itemsBody');
    const addItemBtn = document.getElementById('addItemBtn');

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const createSelectOptions = () => {
        let options = '<option value=\"\">Selecione</option>';
        products.forEach((product) => {
            const productId = Number(product.id || 0);
            if (productId <= 0) {
                return;
            }

            const regularPrice = Number(product.price || 0);
            const promoPrice = product.promotional_price !== null ? Number(product.promotional_price) : null;
            const effectivePrice = promoPrice !== null ? promoPrice : regularPrice;
            const safeName = escapeHtml(product.name || 'Produto');
            const label = `${safeName} - R$ ${effectivePrice.toFixed(2).replace('.', ',')}`;
            options += `<option value=\"${productId}\">${label}</option>`;
        });
        return options;
    };

    const addRow = () => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="product_id[]" required>
                    ${createSelectOptions()}
                </select>
            </td>
            <td>
                <input name="quantity[]" type="number" min="1" step="1" value="1" required>
            </td>
            <td>
                <input name="item_notes[]" type="text" placeholder="Opcional">
            </td>
            <td>
                <button class="btn secondary remove-item" type="button">Remover</button>
            </td>
        `;
        tbody.appendChild(tr);
    };

    addItemBtn.addEventListener('click', addRow);

    tbody.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.classList.contains('remove-item')) {
            return;
        }

        const row = target.closest('tr');
        if (row) {
            row.remove();
        }

        if (tbody.children.length === 0) {
            addRow();
        }
    });

    addRow();
})();
</script>
