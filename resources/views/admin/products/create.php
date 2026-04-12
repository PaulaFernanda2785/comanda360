<div class="topbar">
    <div>
        <h1>Novo Produto</h1>
        <p>Cadastro inicial do módulo de produtos.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/products')) ?>">Voltar</a>
</div>

<div class="card">
    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/products/store')) ?>">
        <div class="grid two">
            <div class="field">
                <label for="name">Nome</label>
                <input id="name" name="name" type="text" required>
            </div>

            <div class="field">
                <label for="slug">Slug</label>
                <input id="slug" name="slug" type="text" required>
            </div>
        </div>

        <div class="grid two">
            <div class="field">
                <label for="category_id">Categoria</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>">
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="sku">SKU</label>
                <input id="sku" name="sku" type="text">
            </div>
        </div>

        <div class="field">
            <label for="description">Descrição</label>
            <textarea id="description" name="description" rows="4"></textarea>
        </div>

        <div class="grid two">
            <div class="field">
                <label for="price">Preço</label>
                <input id="price" name="price" type="number" step="0.01" min="0" required>
            </div>

            <div class="field">
                <label for="promotional_price">Preço promocional</label>
                <input id="promotional_price" name="promotional_price" type="number" step="0.01" min="0">
            </div>
        </div>

        <div class="grid two">
            <div class="field">
                <label for="display_order">Ordem de exibição</label>
                <input id="display_order" name="display_order" type="number" min="0" value="0">
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <div>
                    <label><input type="checkbox" name="is_featured"> Destaque</label><br>
                    <label><input type="checkbox" name="is_active" checked> Ativo</label><br>
                    <label><input type="checkbox" name="is_paused"> Pausado</label><br>
                    <label><input type="checkbox" name="allows_notes" checked> Permite observações</label><br>
                    <label><input type="checkbox" name="has_additionals"> Possui adicionais</label>
                </div>
            </div>
        </div>

        <button class="btn" type="submit">Salvar produto</button>
    </form>
</div>
