<?php
$productsJson = json_encode($products ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
if (!is_string($productsJson)) {
    $productsJson = '[]';
}
?>

<div class="topbar">
    <div>
        <h1>Novo Pedido</h1>
        <p>Criar pedido vinculado a comanda aberta, com adicionais por item quando disponiveis.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Voltar</a>
</div>

<div class="card">
    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/store')) ?>">
        <?= form_security_fields('orders.store') ?>
        <div class="grid two">
            <div class="field">
                <label for="command_id">Comanda aberta</label>
                <select id="command_id" name="command_id" required>
                    <option value="">Selecione</option>
                    <?php foreach (($commands ?? []) as $command): ?>
                        <option value="<?= (int) $command['id'] ?>">
                            <?= $command['table_number'] !== null ? 'Mesa ' . (int) $command['table_number'] : 'Comanda sem mesa' ?>
                            <?= !empty($command['customer_name']) ? '- ' . htmlspecialchars((string) $command['customer_name']) : '' ?>
                            <?= !empty($command['opened_at']) ? '- Aberta em ' . htmlspecialchars((string) $command['opened_at']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="notes">Observacoes gerais</label>
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
        <p>Selecione produto, quantidade, adicionais e observacao por linha.</p>

        <table id="itemsTable">
            <thead>
                <tr>
                    <th style="width:28%">Produto</th>
                    <th style="width:10%">Qtd</th>
                    <th style="width:34%">Adicionais</th>
                    <th style="width:20%">Observacao</th>
                    <th style="width:8%">Acao</th>
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
    const productsById = {};
    const tbody = document.getElementById('itemsBody');
    const addItemBtn = document.getElementById('addItemBtn');

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    products.forEach((product) => {
        const productId = Number(product.id || 0);
        if (productId > 0) {
            productsById[productId] = product;
        }
    });

    const productOptions = () => {
        let options = '<option value="">Selecione</option>';
        products.forEach((product) => {
            const productId = Number(product.id || 0);
            if (productId <= 0) {
                return;
            }

            const regularPrice = Number(product.price || 0);
            const promoPrice = product.promotional_price !== null ? Number(product.promotional_price) : null;
            const effectivePrice = promoPrice !== null ? promoPrice : regularPrice;
            const safeName = escapeHtml(product.name || 'Produto');
            const category = product.category_name ? ` (${escapeHtml(product.category_name)})` : '';
            options += `<option value="${productId}">${safeName}${category} - R$ ${effectivePrice.toFixed(2).replace('.', ',')}</option>`;
        });
        return options;
    };

    const checkedAdditionalIds = (container) => {
        const ids = [];
        container.querySelectorAll('input[type="checkbox"][data-additional-id]').forEach((checkbox) => {
            if (checkbox.checked) {
                ids.push(String(Number(checkbox.getAttribute('data-additional-id') || 0)));
            }
        });
        return ids.filter((id) => Number(id) > 0);
    };

    const enforceMaxSelection = (container, maxSelection) => {
        const selected = checkedAdditionalIds(container);
        if (maxSelection !== null && selected.length > maxSelection) {
            const lastChecked = container.querySelector('input[type="checkbox"][data-last-change="1"]');
            if (lastChecked) {
                lastChecked.checked = false;
                lastChecked.removeAttribute('data-last-change');
            }
            alert(`Este produto permite no maximo ${maxSelection} adicional(is) por item.`);
        }
    };

    const syncAdditionalHidden = (row) => {
        const hidden = row.querySelector('input[name="additional_item_ids[]"]');
        const container = row.querySelector('.additionals-container');
        if (!hidden || !container) {
            return;
        }

        const ids = checkedAdditionalIds(container);
        hidden.value = ids.join(',');
    };

    const renderAdditionals = (row) => {
        const productSelect = row.querySelector('select[name="product_id[]"]');
        const additionalsContainer = row.querySelector('.additionals-container');
        const hidden = row.querySelector('input[name="additional_item_ids[]"]');
        if (!productSelect || !additionalsContainer || !hidden) {
            return;
        }

        const productId = Number(productSelect.value || 0);
        const product = productsById[productId] || null;
        hidden.value = '';

        if (!product || !Array.isArray(product.additionals) || product.additionals.length === 0) {
            additionalsContainer.innerHTML = '<span style="color:#64748b">Sem adicionais disponiveis.</span>';
            return;
        }

        const maxSelection = product.additionals_max_selection !== null
            ? Number(product.additionals_max_selection)
            : null;
        const minSelection = product.additionals_min_selection !== null
            ? Number(product.additionals_min_selection)
            : 0;
        const isRequired = Boolean(product.additionals_is_required);

        let html = '';
        if (maxSelection !== null) {
            html += `<div style="margin-bottom:6px"><small style="color:#64748b">Maximo por item: ${maxSelection}</small></div>`;
        }
        if (isRequired) {
            html += `<div style="margin-bottom:6px"><small style="color:#92400e">Selecao obrigatoria${minSelection > 0 ? ` (min ${minSelection})` : ''}.</small></div>`;
        }

        product.additionals.forEach((additional) => {
            const additionalId = Number(additional.id || 0);
            const additionalName = escapeHtml(additional.name || 'Adicional');
            const additionalPrice = Number(additional.price || 0);

            html += `
                <label style="display:block;font-weight:normal;margin-bottom:4px">
                    <input type="checkbox" data-additional-id="${additionalId}">
                    ${additionalName} - R$ ${additionalPrice.toFixed(2).replace('.', ',')}
                </label>
            `;
        });

        additionalsContainer.innerHTML = html;

        additionalsContainer.querySelectorAll('input[type="checkbox"][data-additional-id]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                additionalsContainer.querySelectorAll('input[type="checkbox"][data-additional-id]').forEach((item) => {
                    item.removeAttribute('data-last-change');
                });
                checkbox.setAttribute('data-last-change', '1');
                enforceMaxSelection(additionalsContainer, maxSelection);
                syncAdditionalHidden(row);
            });
        });
    };

    const addRow = () => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="product_id[]" required>
                    ${productOptions()}
                </select>
            </td>
            <td>
                <input name="quantity[]" type="number" min="1" step="1" value="1" required>
            </td>
            <td>
                <div class="additionals-container" style="max-height:140px;overflow:auto;border:1px solid #e5e7eb;border-radius:8px;padding:8px">
                    <span style="color:#64748b">Selecione um produto para ver os adicionais.</span>
                </div>
                <input type="hidden" name="additional_item_ids[]" value="">
            </td>
            <td>
                <input name="item_notes[]" type="text" placeholder="Opcional">
            </td>
            <td>
                <button class="btn secondary remove-item" type="button">Remover</button>
            </td>
        `;
        tbody.appendChild(tr);

        const select = tr.querySelector('select[name="product_id[]"]');
        if (select) {
            select.addEventListener('change', () => renderAdditionals(tr));
        }
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
