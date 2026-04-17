<?php
$summary = is_array($summary ?? null) ? $summary : [];
$productTabs = is_array($productTabs ?? null) ? $productTabs : [];
$categories = is_array($categories ?? null) ? $categories : [];
$canManageProducts = !empty($canManageProducts);
$canCreateProducts = !empty($canCreateProducts);
?>

<style>
    .products-page{display:grid;gap:16px}
    .kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:12px}
    .kpi-item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .kpi-item strong{display:block;font-size:24px;line-height:1.1}
    .kpi-item span{color:#64748b;font-size:12px}
    .category-manager{display:grid;grid-template-columns:1.2fr 1.8fr;gap:12px}
    .category-list{display:grid;gap:8px;max-height:340px;overflow:auto;padding-right:4px}
    .category-row{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .category-row summary{cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:8px}
    .category-row summary strong{font-size:14px}
    .category-row[open]{background:#fff}
    .tabs-wrap{display:flex;gap:8px;overflow:auto;padding-bottom:2px}
    .tab-btn{border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:8px 12px;cursor:pointer;white-space:nowrap}
    .tab-btn.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
    .tab-panel{display:none}
    .tab-panel.active{display:block}
    .products-grid{display:grid;grid-template-columns:repeat(3,minmax(240px,1fr));gap:12px}
    .product-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:10px}
    .product-card.search-hit{outline:2px solid #60a5fa;outline-offset:1px}
    .product-head{display:flex;gap:10px}
    .product-thumb{width:76px;height:76px;border-radius:10px;background:linear-gradient(135deg,#dbeafe,#e2e8f0);overflow:hidden;flex-shrink:0}
    .product-thumb img{width:100%;height:100%;object-fit:cover}
    .product-meta p{margin:6px 0 0;color:#475569;font-size:13px;line-height:1.35}
    .product-actions{display:flex;gap:6px;flex-wrap:wrap}
    .btn-additionals{background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;border:1px solid #0f766e}
    .btn-additionals:hover{filter:brightness(1.04)}
    .search-row{display:grid;grid-template-columns:1fr auto;gap:8px}
    .search-info{color:#64748b;font-size:12px;margin-top:6px}
    @media (max-width:1000px){
        .kpi-grid{grid-template-columns:repeat(3,minmax(120px,1fr))}
        .category-manager{grid-template-columns:1fr}
        .products-grid{grid-template-columns:repeat(2,minmax(220px,1fr))}
    }
    @media (max-width:680px){
        .kpi-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}
        .products-grid{grid-template-columns:1fr}
    }
</style>

<div class="products-page">
    <div class="topbar">
        <div>
            <h1>Produtos</h1>
            <p>Painel de catalogo com abas por categoria, filtro inteligente e acoes rapidas.</p>
        </div>
        <?php if ($canCreateProducts): ?>
            <a class="btn" href="<?= htmlspecialchars(base_url('/admin/products/create')) ?>">Novo produto</a>
        <?php endif; ?>
    </div>

    <div class="kpi-grid">
        <div class="kpi-item"><strong><?= (int) ($summary['total'] ?? 0) ?></strong><span>Produtos</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['active'] ?? 0) ?></strong><span>Ativos</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['paused'] ?? 0) ?></strong><span>Pausados</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['featured'] ?? 0) ?></strong><span>Destaques</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['with_additionals'] ?? 0) ?></strong><span>Com adicionais</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['categories_active'] ?? 0) ?>/<?= (int) ($summary['categories_total'] ?? 0) ?></strong><span>Categorias ativas</span></div>
    </div>

    <?php if ($canManageProducts): ?>
        <div class="card">
            <h3 style="margin-top:0">Gerenciar categorias</h3>
            <div class="category-manager">
                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/products/categories/store')) ?>">
                    <?= form_security_fields('products.category.store') ?>
                    <div class="field">
                        <label for="category_name">Nome da categoria</label>
                        <input id="category_name" name="name" type="text" required placeholder="Ex.: Lanches artesanais">
                    </div>
                    <div class="grid two">
                        <div class="field">
                            <label for="category_slug">Slug (opcional)</label>
                            <input id="category_slug" name="slug" type="text" placeholder="Ex.: lanches-artesanais">
                        </div>
                        <div class="field">
                            <label for="category_display_order">Ordem</label>
                            <input id="category_display_order" name="display_order" type="number" min="0" value="0" placeholder="Ex.: 10">
                        </div>
                    </div>
                    <div class="field">
                        <label for="category_description">Descrição</label>
                        <input id="category_description" name="description" type="text" placeholder="Ex.: Produtos preparados na chapa">
                    </div>
                    <div class="field">
                        <label for="category_status">Status</label>
                        <select id="category_status" name="status">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                    <button class="btn" type="submit">Cadastrar categoria</button>
                </form>

                <div class="category-list">
                    <?php if (empty($categories)): ?>
                        <div class="category-row">Nenhuma categoria cadastrada.</div>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <details class="category-row">
                                <summary>
                                    <div>
                                        <strong><?= htmlspecialchars((string) ($category['name'] ?? 'Categoria')) ?></strong><br>
                                        <small style="color:#64748b">
                                            <?= htmlspecialchars((string) ($category['slug'] ?? '-')) ?> |
                                            <?= (int) ($category['products_count'] ?? 0) ?> produto(s)
                                        </small>
                                    </div>
                                    <span class="badge <?= htmlspecialchars((string) (($category['status'] ?? '') === 'ativo' ? 'status-active' : 'status-inactive')) ?>">
                                        <?= htmlspecialchars((string) (($category['status'] ?? '') === 'ativo' ? 'Ativa' : 'Inativa')) ?>
                                    </span>
                                </summary>
                                <div style="margin-top:10px">
                                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/products/categories/update')) ?>" style="margin-bottom:8px">
                                        <?= form_security_fields('products.category.update') ?>
                                        <input type="hidden" name="category_id" value="<?= (int) ($category['id'] ?? 0) ?>">
                                        <div class="grid two">
                                            <div class="field">
                                                <label>Nome</label>
                                                <input name="name" type="text" required value="<?= htmlspecialchars((string) ($category['name'] ?? '')) ?>">
                                            </div>
                                            <div class="field">
                                                <label>Slug</label>
                                                <input name="slug" type="text" value="<?= htmlspecialchars((string) ($category['slug'] ?? '')) ?>">
                                            </div>
                                        </div>
                                        <div class="grid two">
                                            <div class="field">
                                                <label>Ordem</label>
                                                <input name="display_order" type="number" min="0" value="<?= (int) ($category['display_order'] ?? 0) ?>">
                                            </div>
                                            <div class="field">
                                                <label>Status</label>
                                                <select name="status">
                                                    <option value="ativo" <?= (string) ($category['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                                    <option value="inativo" <?= (string) ($category['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label>Descrição</label>
                                            <input name="description" type="text" value="<?= htmlspecialchars((string) ($category['description'] ?? '')) ?>">
                                        </div>
                                        <button class="btn secondary" type="submit">Salvar categoria</button>
                                    </form>

                                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/products/categories/delete')) ?>" onsubmit="return confirm('Excluir esta categoria?');">
                                        <?= form_security_fields('products.category.delete') ?>
                                        <input type="hidden" name="category_id" value="<?= (int) ($category['id'] ?? 0) ?>">
                                        <button class="btn secondary" type="submit">Excluir categoria</button>
                                    </form>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="search-row">
            <input id="productSearch" type="text" placeholder="Buscar produto por nome, descricao, slug, preco ou categoria">
            <button id="clearSearch" class="btn secondary" type="button">Limpar</button>
        </div>
        <div id="searchInfo" class="search-info">Digite para filtrar de forma inteligente.</div>
    </div>

    <div class="tabs-wrap" id="categoryTabs">
        <?php if (empty($productTabs)): ?>
            <span class="badge status-default">Nenhuma categoria com produtos.</span>
        <?php else: ?>
            <?php foreach ($productTabs as $index => $tab): ?>
                <button
                    type="button"
                    class="tab-btn<?= $index === 0 ? ' active' : '' ?>"
                    data-tab-id="category-<?= (int) ($tab['id'] ?? 0) ?>"
                    data-tab-name="<?= htmlspecialchars((string) ($tab['name'] ?? 'Categoria')) ?>"
                >
                    <?= htmlspecialchars((string) ($tab['name'] ?? 'Categoria')) ?>
                    (<?= count(is_array($tab['products'] ?? null) ? $tab['products'] : []) ?>)
                </button>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (empty($productTabs)): ?>
        <div class="card">Nenhum produto encontrado.</div>
    <?php else: ?>
        <?php foreach ($productTabs as $index => $tab): ?>
            <?php $products = is_array($tab['products'] ?? null) ? $tab['products'] : []; ?>
            <div class="card tab-panel<?= $index === 0 ? ' active' : '' ?>" data-panel-id="category-<?= (int) ($tab['id'] ?? 0) ?>">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                    <h3 style="margin:0"><?= htmlspecialchars((string) ($tab['name'] ?? 'Categoria')) ?></h3>
                    <span class="badge <?= htmlspecialchars((string) (($tab['status'] ?? '') === 'ativo' ? 'status-active' : 'status-inactive')) ?>">
                        <?= htmlspecialchars((string) (($tab['status'] ?? '') === 'ativo' ? 'Ativa' : 'Inativa')) ?>
                    </span>
                </div>

                <?php if (!empty($tab['description'])): ?>
                    <p style="margin-top:0;color:#64748b"><?= htmlspecialchars((string) $tab['description']) ?></p>
                <?php endif; ?>

                <div class="products-grid">
                    <?php if (empty($products)): ?>
                        <div class="product-card">
                            Nenhum produto nesta categoria.
                        </div>
                    <?php endif; ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $searchText = strtolower(trim(implode(' ', [
                            (string) ($product['name'] ?? ''),
                            (string) ($product['slug'] ?? ''),
                            (string) ($product['description'] ?? ''),
                            (string) ($product['category_name'] ?? ''),
                            (string) ($product['sku'] ?? ''),
                            (string) ($product['price'] ?? ''),
                            (string) ($product['promotional_price'] ?? ''),
                        ])));
                        ?>
                        <div class="product-card" data-search="<?= htmlspecialchars($searchText) ?>">
                            <div class="product-head">
                                <div class="product-thumb">
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="<?= htmlspecialchars(product_image_url((string) $product['image_path'])) ?>" alt="<?= htmlspecialchars((string) ($product['name'] ?? 'Produto')) ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="product-meta">
                                    <strong><?= htmlspecialchars((string) ($product['name'] ?? 'Produto')) ?></strong><br>
                                    <small style="color:#64748b"><?= htmlspecialchars((string) ($product['slug'] ?? '-')) ?></small>
                                    <p><?= htmlspecialchars((string) ($product['description'] ?? 'Sem descricao cadastrada.')) ?></p>
                                </div>
                            </div>

                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <?php if ((int) ($product['is_active'] ?? 0) === 1 && (int) ($product['is_paused'] ?? 0) === 0): ?>
                                    <span class="badge status-active">Ativo</span>
                                <?php elseif ((int) ($product['is_paused'] ?? 0) === 1): ?>
                                    <span class="badge status-waiting">Pausado</span>
                                <?php else: ?>
                                    <span class="badge status-inactive">Inativo</span>
                                <?php endif; ?>

                                <?php if ((int) ($product['is_featured'] ?? 0) === 1): ?>
                                    <span class="badge status-trial">Destaque</span>
                                <?php endif; ?>

                                <?php if ((int) ($product['has_additionals'] ?? 0) === 1): ?>
                                    <span class="badge status-received">
                                        Adicionais: <?= (int) ($product['additionals_count'] ?? 0) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:8px;flex-wrap:wrap">
                                <div>
                                    <strong>R$ <?= number_format((float) ($product['price'] ?? 0), 2, ',', '.') ?></strong><br>
                                    <?php if ($product['promotional_price'] !== null): ?>
                                        <small style="color:#166534">Promo: R$ <?= number_format((float) $product['promotional_price'], 2, ',', '.') ?></small>
                                    <?php endif; ?>
                                </div>

                                <?php if ($canManageProducts): ?>
                                    <div class="product-actions">
                                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/products/edit?product_id=' . (int) $product['id'])) ?>">Editar</a>
                                        <a class="btn btn-additionals" href="<?= htmlspecialchars(base_url('/admin/products/additionals?product_id=' . (int) $product['id'])) ?>">Adicionais</a>
                                        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/products/delete')) ?>" onsubmit="return confirm('Excluir este produto?');">
                                            <?= form_security_fields('products.delete') ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <button class="btn secondary" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(() => {
    const tabButtons = Array.from(document.querySelectorAll('.tab-btn[data-tab-id]'));
    const tabPanels = Array.from(document.querySelectorAll('.tab-panel[data-panel-id]'));
    const searchInput = document.getElementById('productSearch');
    const clearSearchButton = document.getElementById('clearSearch');
    const searchInfo = document.getElementById('searchInfo');
    const categoryNameInput = document.getElementById('category_name');
    const categorySlugInput = document.getElementById('category_slug');

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const setActiveTab = (tabId, skipFilter = false) => {
        tabButtons.forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-tab-id') === tabId);
        });
        tabPanels.forEach((panel) => {
            panel.classList.toggle('active', panel.getAttribute('data-panel-id') === tabId);
        });
        if (!skipFilter) {
            applyFilter();
        }
    };

    const activePanel = () => tabPanels.find((panel) => panel.classList.contains('active')) || null;

    const applyFilter = () => {
        const rawQuery = searchInput ? searchInput.value : '';
        const tokens = normalize(rawQuery).split(/\s+/).filter(Boolean);
        const panelStats = [];

        tabPanels.forEach((panel) => {
            const cards = Array.from(panel.querySelectorAll('.product-card[data-search]'));
            let visibleCount = 0;
            let firstVisibleCard = null;

            cards.forEach((card) => {
                card.classList.remove('search-hit');
                const haystack = normalize(card.getAttribute('data-search') || '');
                const match = tokens.every((token) => haystack.includes(token));
                card.style.display = match ? '' : 'none';
                if (match) {
                    visibleCount++;
                    if (firstVisibleCard === null) {
                        firstVisibleCard = card;
                    }
                }
            });

            panelStats.push({
                panel,
                visibleCount,
                firstVisibleCard,
            });
        });

        const active = activePanel();
        const activeStat = panelStats.find((item) => item.panel === active) ?? null;

        if (tokens.length > 0) {
            const firstMatched = panelStats.find((item) => item.visibleCount > 0) ?? null;
            if (firstMatched !== null && firstMatched.panel !== active) {
                setActiveTab(firstMatched.panel.getAttribute('data-panel-id') || '', true);
            }
        }

        const finalActive = activePanel();
        const finalActiveStat = panelStats.find((item) => item.panel === finalActive) ?? activeStat;
        if (finalActiveStat && finalActiveStat.firstVisibleCard) {
            finalActiveStat.firstVisibleCard.classList.add('search-hit');
            if (tokens.length > 0) {
                finalActiveStat.firstVisibleCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        if (searchInfo) {
            if (tokens.length === 0) {
                searchInfo.textContent = 'Digite para filtrar de forma inteligente.';
            } else {
                const activeButton = tabButtons.find((button) => button.classList.contains('active')) || null;
                const categoryName = activeButton ? (activeButton.getAttribute('data-tab-name') || 'Categoria') : 'Categoria';
                const visibleCount = finalActiveStat ? finalActiveStat.visibleCount : 0;
                const totalVisible = panelStats.reduce((sum, item) => sum + item.visibleCount, 0);
                searchInfo.textContent = totalVisible > 0
                    ? `Filtro ativo: ${totalVisible} resultado(s). Direcionado para ${categoryName} com ${visibleCount} produto(s).`
                    : 'Nenhum produto encontrado para o filtro informado.';
            }
        }
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => setActiveTab(button.getAttribute('data-tab-id') || ''));
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
    }

    if (clearSearchButton && searchInput) {
        clearSearchButton.addEventListener('click', () => {
            searchInput.value = '';
            applyFilter();
            searchInput.focus();
        });
    }

    const slugify = (value) => normalize(value).replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    if (categoryNameInput && categorySlugInput) {
        categoryNameInput.addEventListener('input', () => {
            if (categorySlugInput.dataset.touched === '1') {
                return;
            }
            categorySlugInput.value = slugify(categoryNameInput.value);
        });
        categorySlugInput.addEventListener('input', () => {
            categorySlugInput.dataset.touched = '1';
        });
    }

    applyFilter();
})();
</script>
