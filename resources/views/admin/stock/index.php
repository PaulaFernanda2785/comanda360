<?php
$stockPanel = is_array($stockPanel ?? null) ? $stockPanel : [];
$summary = is_array($stockPanel['summary'] ?? null) ? $stockPanel['summary'] : [];
$items = is_array($stockPanel['items'] ?? null) ? $stockPanel['items'] : [];
$movements = is_array($stockPanel['movements'] ?? null) ? $stockPanel['movements'] : [];
$filters = is_array($stockPanel['filters'] ?? null) ? $stockPanel['filters'] : [];
$itemPagination = is_array($stockPanel['item_pagination'] ?? null) ? $stockPanel['item_pagination'] : [];
$movementPagination = is_array($stockPanel['movement_pagination'] ?? null) ? $stockPanel['movement_pagination'] : [];
$products = is_array($stockPanel['products'] ?? null) ? $stockPanel['products'] : [];
$unitOptions = is_array($stockPanel['unit_options'] ?? null) ? $stockPanel['unit_options'] : [];
$referenceTypeOptions = is_array($stockPanel['reference_type_options'] ?? null) ? $stockPanel['reference_type_options'] : [];
$canManageStock = (bool) ($canManageStock ?? false);

$stockSearch = trim((string) ($filters['search'] ?? ''));
$stockStatus = trim((string) ($filters['status'] ?? ''));
$stockAlert = trim((string) ($filters['alert'] ?? ''));
$stockMovementSearch = trim((string) ($filters['movement_search'] ?? ''));
$stockMovementType = trim((string) ($filters['movement_type'] ?? ''));

$statusOptions = [
    '' => 'Todos os status',
    'ativo' => 'Ativos',
    'inativo' => 'Inativos',
];

$alertOptions = [
    '' => 'Todos os alertas',
    'low' => 'Estoque baixo',
    'out' => 'Sem saldo',
];

$movementTypeOptions = [
    '' => 'Todos os tipos',
    'entry' => 'Entradas',
    'exit' => 'Saídas',
    'adjustment' => 'Ajustes',
];

$referenceTypeLabels = [
    'manual' => 'Manual',
    'purchase' => 'Compra',
    'consumption' => 'Consumo',
    'inventory_count' => 'Inventário',
    'waste' => 'Perda',
    'production' => 'Produção',
];

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$returnQuery = http_build_query($currentQuery);

$buildStockUrl = static function (array $overrides = []) use ($currentQuery): string {
    $params = array_merge($currentQuery, $overrides);

    foreach ($params as $key => $value) {
        if (in_array($key, ['stock_search', 'stock_status', 'stock_alert', 'stock_page', 'stock_movement_search', 'stock_movement_type', 'stock_movement_page'], true)
            && trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/admin/stock' . ($query !== '' ? '?' . $query : ''));
};

$formatDate = static function (mixed $value, bool $withTime = true): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
};

$formatQty = static function (mixed $value): string {
    return number_format((float) $value, 3, ',', '.');
};

$stockAlertMeta = static function (array $item): array {
    $alert = (string) ($item['stock_alert'] ?? 'normal');

    return match ($alert) {
        'out' => ['label' => 'Sem saldo', 'class' => 'badge status-overdue', 'note' => 'Sem disponibilidade operacional.'],
        'low' => ['label' => 'Estoque baixo', 'class' => 'badge status-pending', 'note' => 'Abaixo do minimo definido.'],
        default => ['label' => 'Controlado', 'class' => 'badge status-active', 'note' => 'Saldo acima do minimo.'],
    };
};

$extractIds = static function (string $raw): array {
    $result = [];
    foreach (explode(',', $raw) as $value) {
        $value = trim($value);
        if ($value !== '' && ctype_digit($value)) {
            $result[(int) $value] = true;
        }
    }

    return array_map('intval', array_keys($result));
};

$extractNames = static function (string $raw): array {
    $result = [];
    foreach (explode('||', $raw) as $value) {
        $value = trim($value);
        if ($value !== '') {
            $result[] = $value;
        }
    }

    return $result;
};

$renderProductPicker = static function (string $pickerId, array $products, array $selectedIds, string $note): void {
    $selectedMap = [];
    foreach ($selectedIds as $selectedId) {
        $selectedMap[(int) $selectedId] = true;
    }

    $selectedNames = [];
    foreach ($products as $product) {
        $productId = (int) ($product['id'] ?? 0);
        if ($productId > 0 && isset($selectedMap[$productId])) {
            $selectedNames[] = (string) ($product['name'] ?? 'Produto');
        }
    }
    ?>
    <div class="stock-product-picker" data-stock-product-picker>
        <div class="stock-picker-head">
            <div>
                <strong class="stock-picker-title">Seleção de produtos</strong>
                <p class="stock-picker-note">Pesquise, marque os produtos dependentes e mantenha o vínculo organizado no mesmo cadastro.</p>
            </div>
            <div class="stock-picker-stats">
                <span class="stock-picker-stat">
                    <strong data-stock-picker-count><?= count($selectedIds) ?></strong>
                    <small>selecionados</small>
                </span>
                <span class="stock-picker-stat">
                    <strong><?= count($products) ?></strong>
                    <small>disponíveis</small>
                </span>
            </div>
        </div>

        <?php if ($products === []): ?>
            <div class="stock-empty">Nenhum produto disponível para vínculo.</div>
        <?php else: ?>
            <div class="stock-picker-toolbar">
                <div class="field">
                    <label for="<?= htmlspecialchars($pickerId) ?>_search">Buscar produto</label>
                    <input
                        id="<?= htmlspecialchars($pickerId) ?>_search"
                        type="text"
                        placeholder="Nome, SKU ou categoria"
                        data-stock-picker-search
                    >
                </div>
                <div class="stock-picker-tags" data-stock-picker-tags>
                    <?php if ($selectedNames === []): ?>
                        <span class="stock-picker-placeholder">Nenhum produto selecionado.</span>
                    <?php else: ?>
                        <?php foreach (array_slice($selectedNames, 0, 4) as $selectedName): ?>
                            <span class="stock-picker-tag"><?= htmlspecialchars($selectedName) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($selectedNames) > 4): ?>
                            <span class="stock-picker-tag">+<?= count($selectedNames) - 4 ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stock-product-grid" data-stock-picker-grid>
                <?php foreach ($products as $product): ?>
                    <?php
                    $productId = (int) ($product['id'] ?? 0);
                    $checked = isset($selectedMap[$productId]);
                    $optionId = $pickerId . '_product_' . $productId;
                    $productName = trim((string) ($product['name'] ?? 'Produto'));
                    $categoryName = trim((string) ($product['category_name'] ?? 'Sem categoria'));
                    $productSku = trim((string) ($product['sku'] ?? ''));
                    $searchText = strtolower($productName . ' ' . $categoryName . ' ' . $productSku);
                    ?>
                    <label
                        class="stock-product-option"
                        for="<?= htmlspecialchars($optionId) ?>"
                        data-stock-filter-text="<?= htmlspecialchars($searchText) ?>"
                    >
                        <input
                            id="<?= htmlspecialchars($optionId) ?>"
                            type="checkbox"
                            name="product_ids[]"
                            value="<?= $productId ?>"
                            data-stock-picker-checkbox
                            data-product-name="<?= htmlspecialchars($productName) ?>"
                            <?= $checked ? 'checked' : '' ?>
                        >
                        <span class="stock-product-option-body">
                            <span class="stock-product-option-head">
                                <span class="stock-product-option-check"></span>
                                <span class="stock-product-option-main">
                                    <strong><?= htmlspecialchars($productName) ?></strong>
                                    <small><?= htmlspecialchars($categoryName) ?></small>
                                </span>
                            </span>
                            <span class="stock-product-option-meta">
                                <span class="stock-product-meta-pill">Produto</span>
                                <?php if ($productSku !== ''): ?>
                                    <span class="stock-product-meta-pill">SKU <?= htmlspecialchars($productSku) ?></span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="stock-empty" data-stock-picker-empty hidden>Nenhum produto encontrado para esse filtro.</div>
        <?php endif; ?>

        <span class="stock-form-note"><?= htmlspecialchars($note) ?></span>
    </div>
    <?php
};
?>

<style>
    .stock-page{display:grid;gap:16px}
    .stock-hero{border:1px solid #bfdbfe;background:linear-gradient(120deg,var(--theme-main-card,#0f172a) 0%,#1d4ed8 46%,#0f766e 100%);color:#fff;border-radius:18px;padding:22px;position:relative;overflow:hidden}
    .stock-hero:before{content:"";position:absolute;top:-48px;right:-20px;width:210px;height:210px;border-radius:999px;background:rgba(255,255,255,.09)}
    .stock-hero:after{content:"";position:absolute;bottom:-82px;left:-30px;width:190px;height:190px;border-radius:999px;background:rgba(255,255,255,.08)}
    .stock-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
    .stock-hero h1{margin:0 0 8px;font-size:30px}
    .stock-hero p{margin:0;max-width:860px;color:#dbeafe;line-height:1.6}
    .stock-pills{display:flex;gap:8px;flex-wrap:wrap}
    .stock-pill{border:1px solid rgba(255,255,255,.22);background:rgba(15,23,42,.32);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .stock-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,.95fr);gap:16px;align-items:start}
    .stock-main,.stock-side{display:grid;gap:16px}
    .stock-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .stock-head h2,.stock-head h3{margin:0}
    .stock-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.5}
    .stock-badges{display:flex;gap:8px;flex-wrap:wrap}

    .stock-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .stock-kpi{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#eff6ff);padding:14px}
    .stock-kpi span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .stock-kpi strong{display:block;margin-top:6px;font-size:24px;color:#0f172a}
    .stock-kpi small{display:block;margin-top:4px;color:#475569}

    .stock-filter-grid{display:grid;grid-template-columns:1.35fr 1fr 1fr auto;gap:10px;align-items:end}
    .stock-filter-grid .field{margin:0}
    .stock-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .stock-actions--offset{margin-top:14px}

    .stock-list{display:grid;gap:12px}
    .stock-card{border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(180deg,#fff,#f8fafc);padding:14px;display:grid;gap:12px}
    .stock-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .stock-title{display:grid;gap:6px}
    .stock-title strong{font-size:16px;color:#0f172a}
    .stock-title small{font-size:12px;color:#64748b;line-height:1.45}
    .stock-link-list{display:flex;gap:8px;flex-wrap:wrap}
    .stock-link-pill{padding:6px 10px;border-radius:999px;border:1px solid #cbd5e1;background:#fff;color:#334155;font-size:12px;font-weight:700}
    .stock-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .stock-box{border:1px solid #e2e8f0;background:#fff;border-radius:12px;padding:10px}
    .stock-box span{display:block;font-size:11px;text-transform:uppercase;color:#64748b}
    .stock-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}

    .stock-details{border-top:1px dashed #cbd5e1;padding-top:12px}
    .stock-details summary{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;list-style:none;font-weight:700;color:#0f172a}
    .stock-details summary::-webkit-details-marker{display:none}
    .stock-details-toggle{font-size:11px;color:#1d4ed8;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:4px 9px;font-weight:700}
    .stock-details-body{display:grid;gap:14px;margin-top:12px}
    .stock-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .stock-form-grid .field{margin:0}
    .stock-form-grid .field.full{grid-column:1 / -1}
    .stock-form-note{font-size:12px;color:#64748b;line-height:1.5}

    .stock-product-picker{border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(180deg,#f8fbff,#eef6ff);padding:14px;display:grid;gap:12px}
    .stock-picker-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .stock-picker-title{display:block;font-size:14px;color:#0f172a}
    .stock-picker-note{margin:4px 0 0;color:#64748b;font-size:12px;line-height:1.45;max-width:540px}
    .stock-picker-stats{display:flex;gap:8px;flex-wrap:wrap}
    .stock-picker-stat{min-width:88px;padding:8px 10px;border-radius:12px;border:1px solid #dbeafe;background:#fff;text-align:center}
    .stock-picker-stat strong{display:block;font-size:16px;color:#0f172a}
    .stock-picker-stat small{display:block;margin-top:2px;font-size:11px;color:#64748b}
    .stock-picker-toolbar{display:grid;grid-template-columns:minmax(220px,1fr) minmax(0,1.2fr);gap:10px;align-items:start}
    .stock-picker-toolbar .field{margin:0}
    .stock-picker-tags{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;min-height:44px;padding:10px 12px;border:1px dashed #bfdbfe;border-radius:14px;background:rgba(255,255,255,.8)}
    .stock-picker-tag{padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:12px;font-weight:700}
    .stock-picker-placeholder{color:#64748b;font-size:12px;line-height:1.4}
    .stock-product-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;max-height:280px;overflow:auto;padding-right:4px}
    .stock-product-option{position:relative;display:block;border:1px solid #dbeafe;border-radius:16px;background:rgba(255,255,255,.94);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease,background .18s ease;cursor:pointer}
    .stock-product-option:hover{transform:translateY(-1px);border-color:#93c5fd;box-shadow:0 10px 20px rgba(14,116,144,.08)}
    .stock-product-option input{position:absolute;opacity:0;pointer-events:none}
    .stock-product-option-body{display:grid;gap:10px;padding:12px}
    .stock-product-option-head{display:flex;gap:10px;align-items:flex-start}
    .stock-product-option-check{width:20px;height:20px;border-radius:7px;border:1px solid #94a3b8;background:#fff;box-shadow:inset 0 0 0 2px rgba(255,255,255,.9);flex:0 0 20px;margin-top:1px}
    .stock-product-option-main{display:grid;gap:4px}
    .stock-product-option-main strong{display:block;color:#0f172a;font-size:13px;line-height:1.35}
    .stock-product-option-main small{display:block;color:#64748b;font-size:11px;line-height:1.4}
    .stock-product-option-meta{display:flex;gap:6px;flex-wrap:wrap}
    .stock-product-meta-pill{padding:5px 8px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:700}
    .stock-product-option input:checked + .stock-product-option-body{background:linear-gradient(180deg,#eff6ff,#dbeafe);border-radius:15px}
    .stock-product-option input:checked + .stock-product-option-body .stock-product-option-check{border-color:#1d4ed8;background:linear-gradient(180deg,#2563eb,#1d4ed8);box-shadow:inset 0 0 0 5px #dbeafe}
    .stock-product-option input:checked + .stock-product-option-body .stock-product-meta-pill{background:#fff;color:#1d4ed8}
    .stock-product-option.is-hidden{display:none}

    .stock-summary-grid{display:grid;gap:8px}
    .stock-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
    .stock-summary-item strong{color:#0f172a}
    .stock-summary-item span{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}

    .stock-empty{padding:14px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.5}
    .stock-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .stock-pagination-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .stock-page-btn{display:inline-block;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none}
    .stock-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .stock-page-ellipsis{color:#64748b;padding:0 2px}

    .stock-movement-list{display:grid;gap:10px}
    .stock-movement{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);padding:12px;display:grid;gap:10px}
    .stock-movement-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .stock-movement-meta{display:grid;gap:4px}
    .stock-movement-meta strong{color:#0f172a;font-size:14px}
    .stock-movement-meta small{color:#64748b;font-size:12px;line-height:1.4}
    .stock-movement-amount{font-size:18px;font-weight:800;color:#0f172a}
    .stock-movement-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
    .stock-movement-box{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:10px}
    .stock-movement-box span{display:block;font-size:11px;text-transform:uppercase;color:#64748b}
    .stock-movement-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}
    .stock-strip{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 12px;border:1px solid #dbeafe;background:#eff6ff;border-radius:12px}
    .stock-strip strong{color:#0f172a}

    @media (max-width:1180px){
        .stock-layout{grid-template-columns:1fr}
    }
    @media (max-width:980px){
        .stock-kpis,.stock-filter-grid,.stock-info,.stock-form-grid,.stock-movement-grid,.stock-product-grid,.stock-picker-toolbar{grid-template-columns:1fr 1fr}
    }
    @media (max-width:760px){
        .stock-kpis,.stock-filter-grid,.stock-info,.stock-form-grid,.stock-movement-grid,.stock-product-grid,.stock-picker-toolbar{grid-template-columns:1fr}
        .stock-hero h1{font-size:24px}
    }
</style>

<div class="stock-page">
    <div class="stock-hero">
        <div class="stock-hero-body">
            <div>
                <h1>Estoque</h1>
                <p>Controle objetivo de saldo, risco de reposição e histórico auditável. O foco aqui não é cadastro volumoso: é manter o item certo, saldo confiável e relação clara entre o insumo e os produtos que dependem dele.</p>
            </div>
            <div class="stock-pills">
                <span class="stock-pill">Itens: <?= htmlspecialchars((string) ($summary['total_items'] ?? 0)) ?></span>
                <span class="stock-pill">Baixo: <?= htmlspecialchars((string) ($summary['low_stock_items'] ?? 0)) ?></span>
                <span class="stock-pill">Sem saldo: <?= htmlspecialchars((string) ($summary['out_of_stock_items'] ?? 0)) ?></span>
                <span class="stock-pill">Movimentos: <?= htmlspecialchars((string) ($summary['total_movements'] ?? 0)) ?></span>
            </div>
        </div>
    </div>

    <div class="stock-layout">
        <div class="stock-main">
            <section class="card">
                <div class="stock-head">
                    <div>
                        <h2>Painel do estoque</h2>
                        <p class="stock-note">Filtro e leitura rápida para enxergar a operação em risco. O cadastro agora aceita um item compartilhado por vários produtos, mantendo um único saldo centralizado para o controle do insumo.</p>
                    </div>
                    <div class="stock-badges">
                        <?php if (!empty($summary['last_item_update_at'])): ?>
                            <span class="badge">Última atualização: <?= htmlspecialchars($formatDate($summary['last_item_update_at'])) ?></span>
                        <?php endif; ?>
                        <span class="badge">Produtos ligados: <?= htmlspecialchars((string) ($summary['linked_products'] ?? 0)) ?></span>
                        <span class="badge">Itens com vínculo: <?= htmlspecialchars((string) ($summary['linked_items'] ?? 0)) ?></span>
                    </div>
                </div>

                <div class="stock-kpis" style="margin-top:16px">
                    <div class="stock-kpi">
                        <span>Itens ativos</span>
                        <strong><?= htmlspecialchars((string) ($summary['active_items'] ?? 0)) ?></strong>
                        <small>Base operacional em uso</small>
                    </div>
                    <div class="stock-kpi">
                        <span>Estoque baixo</span>
                        <strong><?= htmlspecialchars((string) ($summary['low_stock_items'] ?? 0)) ?></strong>
                        <small>Reposição perto do limite</small>
                    </div>
                    <div class="stock-kpi">
                        <span>Sem saldo</span>
                        <strong><?= htmlspecialchars((string) ($summary['out_of_stock_items'] ?? 0)) ?></strong>
                        <small>Impacto direto na operação</small>
                    </div>
                    <div class="stock-kpi">
                        <span>Ajustes</span>
                        <strong><?= htmlspecialchars((string) ($summary['adjustment_count'] ?? 0)) ?></strong>
                        <small>Correção de inventário</small>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/admin/stock')) ?>" style="margin-top:16px">
                    <input type="hidden" name="stock_movement_search" value="<?= htmlspecialchars($stockMovementSearch) ?>">
                    <input type="hidden" name="stock_movement_type" value="<?= htmlspecialchars($stockMovementType) ?>">
                    <input type="hidden" name="stock_movement_page" value="<?= htmlspecialchars((string) ($movementPagination['page'] ?? 1)) ?>">

                    <div class="stock-filter-grid">
                        <div class="field">
                            <label for="stock_search">Busca</label>
                            <input id="stock_search" name="stock_search" type="text" value="<?= htmlspecialchars($stockSearch) ?>" placeholder="Item, SKU, produto ou ID">
                        </div>
                        <div class="field">
                            <label for="stock_status">Status</label>
                            <select id="stock_status" name="stock_status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $stockStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="stock_alert">Alerta</label>
                            <select id="stock_alert" name="stock_alert">
                                <?php foreach ($alertOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $stockAlert === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="stock-actions">
                            <input type="hidden" name="stock_page" value="1">
                            <button class="btn" type="submit">Filtrar painel</button>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/stock')) ?>">Limpar</a>
                        </div>
                    </div>
                </form>

                <div class="stock-list" style="margin-top:16px">
                    <?php if ($items === []): ?>
                        <div class="stock-empty">Nenhum item de estoque encontrado para os filtros aplicados.</div>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <?php
                        $alertMeta = $stockAlertMeta($item);
                        $linkedProductIds = $extractIds((string) ($item['linked_product_ids'] ?? ''));
                        $linkedProductNames = $extractNames((string) ($item['linked_product_names'] ?? ''));
                        ?>
                        <article class="stock-card">
                            <div class="stock-card-top">
                                <div class="stock-title">
                                    <strong>#<?= (int) ($item['id'] ?? 0) ?> - <?= htmlspecialchars((string) ($item['name'] ?? 'Item')) ?></strong>
                                    <small>
                                        SKU: <?= htmlspecialchars((string) ($item['sku'] ?? 'Não informado')) ?>
                                        | Vínculos: <?= htmlspecialchars((string) count($linkedProductNames)) ?>
                                        | Unidade: <?= htmlspecialchars(strtoupper((string) ($item['unit_of_measure'] ?? 'un'))) ?>
                                    </small>
                                </div>
                                <div class="stock-badges">
                                    <span class="<?= htmlspecialchars($alertMeta['class']) ?>"><?= htmlspecialchars($alertMeta['label']) ?></span>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('stock_item_status', $item['status'] ?? null)) ?>"><?= htmlspecialchars(status_label('stock_item_status', $item['status'] ?? null)) ?></span>
                                </div>
                            </div>

                            <div class="stock-link-list">
                                <?php if ($linkedProductNames === []): ?>
                                    <span class="stock-link-pill">Sem produto vinculado</span>
                                <?php else: ?>
                                    <?php foreach ($linkedProductNames as $linkedName): ?>
                                        <span class="stock-link-pill"><?= htmlspecialchars($linkedName) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="stock-info">
                                <div class="stock-box">
                                    <span>Saldo atual</span>
                                    <strong><?= htmlspecialchars($formatQty($item['current_quantity'] ?? 0)) ?> <?= htmlspecialchars((string) ($item['unit_of_measure'] ?? 'un')) ?></strong>
                                </div>
                                <div class="stock-box">
                                    <span>Estoque minimo</span>
                                    <strong>
                                        <?= ($item['minimum_quantity'] ?? null) !== null
                                            ? htmlspecialchars($formatQty($item['minimum_quantity'])) . ' ' . htmlspecialchars((string) ($item['unit_of_measure'] ?? 'un'))
                                            : 'Não definido' ?>
                                    </strong>
                                </div>
                                <div class="stock-box">
                                    <span>Risco</span>
                                    <strong><?= htmlspecialchars($alertMeta['note']) ?></strong>
                                </div>
                                <div class="stock-box">
                                    <span>Última atualização</span>
                                    <strong><?= htmlspecialchars($formatDate($item['updated_at'] ?? $item['created_at'] ?? null)) ?></strong>
                                </div>
                            </div>

                            <?php if ($canManageStock): ?>
                                <details class="stock-details">
                                    <summary>
                                        <span>Editar cadastro e movimentar</span>
                                        <span class="stock-details-toggle">Expandir / recolher</span>
                                    </summary>

                                    <div class="stock-details-body">
                                        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/stock/items/update')) ?>">
                                            <?= form_security_fields('stock.items.update.' . (int) ($item['id'] ?? 0)) ?>
                                            <input type="hidden" name="stock_item_id" value="<?= (int) ($item['id'] ?? 0) ?>">
                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                            <div class="stock-form-grid">
                                                <div class="field">
                                                    <label for="stock_name_<?= (int) ($item['id'] ?? 0) ?>">Nome</label>
                                                    <input id="stock_name_<?= (int) ($item['id'] ?? 0) ?>" name="name" type="text" required value="<?= htmlspecialchars((string) ($item['name'] ?? '')) ?>">
                                                </div>
                                                <div class="field">
                                                    <label for="stock_sku_<?= (int) ($item['id'] ?? 0) ?>">SKU</label>
                                                    <input id="stock_sku_<?= (int) ($item['id'] ?? 0) ?>" name="sku" type="text" value="<?= htmlspecialchars((string) ($item['sku'] ?? '')) ?>">
                                                </div>
                                                <div class="field">
                                                    <label for="stock_unit_<?= (int) ($item['id'] ?? 0) ?>">Unidade</label>
                                                    <select id="stock_unit_<?= (int) ($item['id'] ?? 0) ?>" name="unit_of_measure" required>
                                                        <?php foreach ($unitOptions as $unitOption): ?>
                                                            <option value="<?= htmlspecialchars((string) $unitOption) ?>" <?= (string) ($item['unit_of_measure'] ?? 'un') === (string) $unitOption ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars(strtoupper((string) $unitOption)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="stock_minimum_<?= (int) ($item['id'] ?? 0) ?>">Estoque mínimo</label>
                                                    <input id="stock_minimum_<?= (int) ($item['id'] ?? 0) ?>" name="minimum_quantity" type="number" min="0" step="0.001" value="<?= ($item['minimum_quantity'] ?? null) !== null ? htmlspecialchars(number_format((float) $item['minimum_quantity'], 3, '.', '')) : '' ?>">
                                                </div>
                                                <div class="field">
                                                    <label for="stock_status_edit_<?= (int) ($item['id'] ?? 0) ?>">Status</label>
                                                    <select id="stock_status_edit_<?= (int) ($item['id'] ?? 0) ?>" name="status" required>
                                                        <option value="ativo" <?= (string) ($item['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                                        <option value="inativo" <?= (string) ($item['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                                    </select>
                                                </div>
                                                <div class="field full">
                                                    <label>Produtos que usam este item</label>
                                                    <?php $renderProductPicker('stock_item_' . (int) ($item['id'] ?? 0), $products, $linkedProductIds, 'Um item pode abastecer vários produtos. O saldo continua único e centralizado neste registro de estoque.'); ?>
                                                </div>
                                            </div>

                                            <div class="stock-actions stock-actions--offset">
                                                <button class="btn" type="submit">Salvar item</button>
                                                <span class="stock-form-note">O saldo atual não é alterado aqui. Use a movimentação abaixo para manter trilha auditável.</span>
                                            </div>
                                        </form>

                                        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/stock/movements/store')) ?>">
                                            <?= form_security_fields('stock.movements.store.' . (int) ($item['id'] ?? 0)) ?>
                                            <input type="hidden" name="stock_item_id" value="<?= (int) ($item['id'] ?? 0) ?>">
                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                            <div class="stock-form-grid">
                                                <div class="field">
                                                    <label for="stock_movement_type_<?= (int) ($item['id'] ?? 0) ?>">Tipo de movimentação</label>
                                                    <select id="stock_movement_type_<?= (int) ($item['id'] ?? 0) ?>" name="movement_type" required>
                                                        <option value="entry">Entrada</option>
                                                        <option value="exit">Saída</option>
                                                        <option value="adjustment">Ajuste</option>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="stock_quantity_<?= (int) ($item['id'] ?? 0) ?>">Quantidade</label>
                                                    <input id="stock_quantity_<?= (int) ($item['id'] ?? 0) ?>" name="quantity" type="number" min="0.001" step="0.001" placeholder="Use para entrada ou saida">
                                                </div>
                                                <div class="field">
                                                    <label for="stock_target_<?= (int) ($item['id'] ?? 0) ?>">Saldo alvo</label>
                                                    <input id="stock_target_<?= (int) ($item['id'] ?? 0) ?>" name="target_quantity" type="number" min="0" step="0.001" placeholder="Use apenas em ajuste">
                                                </div>
                                                <div class="field">
                                                    <label for="stock_reference_type_<?= (int) ($item['id'] ?? 0) ?>">Origem</label>
                                                    <select id="stock_reference_type_<?= (int) ($item['id'] ?? 0) ?>" name="reference_type">
                                                        <?php foreach ($referenceTypeOptions as $referenceType): ?>
                                                            <option value="<?= htmlspecialchars((string) $referenceType) ?>">
                                                                <?= htmlspecialchars($referenceTypeLabels[(string) $referenceType] ?? (string) $referenceType) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="field full">
                                                    <label for="stock_reason_<?= (int) ($item['id'] ?? 0) ?>">Motivo</label>
                                                    <input id="stock_reason_<?= (int) ($item['id'] ?? 0) ?>" name="reason" type="text" maxlength="255" placeholder="Compra, consumo interno, inventário, perda ou produção">
                                                </div>
                                            </div>

                                            <div class="stock-actions stock-actions--offset">
                                                <button class="btn" type="submit">Registrar movimento</button>
                                                <span class="stock-form-note">Entrada e saída usam quantidade. Ajuste usa saldo alvo. Misturar os dois enfraquece a confiabilidade do histórico.</span>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ((int) ($itemPagination['total'] ?? 0) > 0): ?>
                    <div class="stock-pagination" style="margin-top:14px">
                        <div class="stock-note">
                            Exibindo <?= htmlspecialchars((string) ($itemPagination['from'] ?? 0)) ?> a <?= htmlspecialchars((string) ($itemPagination['to'] ?? 0)) ?> de <?= htmlspecialchars((string) ($itemPagination['total'] ?? 0)) ?> itens.
                        </div>
                        <?php if ((int) ($itemPagination['last_page'] ?? 1) > 1): ?>
                            <div class="stock-pagination-controls">
                                <?php if ((int) ($itemPagination['page'] ?? 1) > 1): ?>
                                    <a class="stock-page-btn" href="<?= htmlspecialchars($buildStockUrl(['stock_page' => ((int) $itemPagination['page']) - 1])) ?>">Anterior</a>
                                <?php endif; ?>
                                <?php
                                $lastRenderedPage = 0;
                                foreach (is_array($itemPagination['pages'] ?? null) ? $itemPagination['pages'] : [] as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($lastRenderedPage > 0 && $pageNumber - $lastRenderedPage > 1): ?>
                                        <span class="stock-page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a class="stock-page-btn<?= $pageNumber === (int) ($itemPagination['page'] ?? 1) ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildStockUrl(['stock_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                    <?php $lastRenderedPage = $pageNumber; ?>
                                <?php endforeach; ?>
                                <?php if ((int) ($itemPagination['page'] ?? 1) < (int) ($itemPagination['last_page'] ?? 1)): ?>
                                    <a class="stock-page-btn" href="<?= htmlspecialchars($buildStockUrl(['stock_page' => ((int) $itemPagination['page']) + 1])) ?>">Proxima</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="stock-side">
            <section class="card">
                <div class="stock-head">
                    <div>
                        <h3>Resumo operacional</h3>
                        <p class="stock-note">Leitura curta para entender onde o estoque pressiona compra, produção ou cadastro.</p>
                    </div>
                </div>
                <div class="stock-summary-grid">
                    <div class="stock-summary-item"><strong>Total de itens</strong><span><?= htmlspecialchars((string) ($summary['total_items'] ?? 0)) ?></span></div>
                    <div class="stock-summary-item"><strong>Itens ativos</strong><span><?= htmlspecialchars((string) ($summary['active_items'] ?? 0)) ?></span></div>
                    <div class="stock-summary-item"><strong>Itens com vínculo</strong><span><?= htmlspecialchars((string) ($summary['linked_items'] ?? 0)) ?></span></div>
                    <div class="stock-summary-item"><strong>Produtos ligados</strong><span><?= htmlspecialchars((string) ($summary['linked_products'] ?? 0)) ?></span></div>
                    <div class="stock-summary-item"><strong>Sem saldo</strong><span><?= htmlspecialchars((string) ($summary['out_of_stock_items'] ?? 0)) ?></span></div>
                    <div class="stock-summary-item"><strong>Estoque baixo</strong><span><?= htmlspecialchars((string) ($summary['low_stock_items'] ?? 0)) ?></span></div>
                    <div class="stock-summary-item"><strong>Entradas</strong><span><?= htmlspecialchars((string) ($summary['entry_count'] ?? 0)) ?></span></div>
                    <div class="stock-summary-item"><strong>Saídas</strong><span><?= htmlspecialchars((string) ($summary['exit_count'] ?? 0)) ?></span></div>
                    <div class="stock-summary-item"><strong>Último movimento</strong><span><?= htmlspecialchars($formatDate($summary['last_moved_at'] ?? null)) ?></span></div>
                </div>
            </section>

            <?php if ($canManageStock): ?>
                <section class="card">
                    <div class="stock-head">
                        <div>
                            <h3>Novo item</h3>
                            <p class="stock-note">Cadastre só o que exige controle real. Item sem rotina de movimento vira ruído operacional.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/stock/items/store')) ?>">
                        <?= form_security_fields('stock.items.store') ?>

                        <div class="stock-form-grid">
                            <div class="field">
                                <label for="new_stock_name">Nome</label>
                                <input id="new_stock_name" name="name" type="text" required maxlength="150">
                            </div>
                            <div class="field">
                                <label for="new_stock_sku">SKU</label>
                                <input id="new_stock_sku" name="sku" type="text" maxlength="60">
                            </div>
                            <div class="field">
                                <label for="new_stock_unit">Unidade</label>
                                <select id="new_stock_unit" name="unit_of_measure" required>
                                    <?php foreach ($unitOptions as $unitOption): ?>
                                        <option value="<?= htmlspecialchars((string) $unitOption) ?>"><?= htmlspecialchars(strtoupper((string) $unitOption)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="new_stock_initial">Saldo inicial</label>
                                <input id="new_stock_initial" name="initial_quantity" type="number" min="0" step="0.001" value="0.000">
                            </div>
                            <div class="field">
                                <label for="new_stock_minimum">Estoque mínimo</label>
                                <input id="new_stock_minimum" name="minimum_quantity" type="number" min="0" step="0.001">
                            </div>
                            <div class="field">
                                <label for="new_stock_status">Status</label>
                                <select id="new_stock_status" name="status" required>
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                            <div class="field full">
                                <label>Produtos que usam este item</label>
                                <?php $renderProductPicker('new_stock', $products, [], 'Use este vínculo quando o item abastece um ou mais produtos. O relacionamento organiza o controle, mas o consumo automático por venda depende de ficha técnica, que ainda não existe no módulo atual.'); ?>
                            </div>
                        </div>

                        <div class="stock-actions stock-actions--offset">
                            <button class="btn" type="submit">Cadastrar item</button>
                            <span class="stock-form-note">Se houver saldo inicial, o sistema registra a entrada automaticamente para o item não nascer sem histórico.</span>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="card">
                <div class="stock-head">
                    <div>
                        <h3>Movimentações</h3>
                        <p class="stock-note">Histórico de entradas, saídas e ajustes. O filtro da fila agora é independente do painel principal.</p>
                    </div>
                    <div class="stock-badges">
                        <span class="badge">Máximo de 10 por página</span>
                    </div>
                </div>

                <div class="stock-strip" style="margin-top:16px">
                    <strong>Fila operacional de movimentos</strong>
                    <span class="stock-form-note">Use esta lista para ler movimento recente, origem, responsável e motivo sem perder o contexto do cadastro principal.</span>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/admin/stock')) ?>" style="margin-top:16px">
                    <input type="hidden" name="stock_search" value="<?= htmlspecialchars($stockSearch) ?>">
                    <input type="hidden" name="stock_status" value="<?= htmlspecialchars($stockStatus) ?>">
                    <input type="hidden" name="stock_alert" value="<?= htmlspecialchars($stockAlert) ?>">
                    <input type="hidden" name="stock_page" value="<?= htmlspecialchars((string) ($itemPagination['page'] ?? 1)) ?>">

                    <div class="stock-form-grid">
                        <div class="field">
                            <label for="stock_movement_type">Tipo</label>
                            <select id="stock_movement_type" name="stock_movement_type">
                                <?php foreach ($movementTypeOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $stockMovementType === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="stock_movement_search_side">Busca no histórico</label>
                            <input id="stock_movement_search_side" name="stock_movement_search" type="text" value="<?= htmlspecialchars($stockMovementSearch) ?>" placeholder="Item, SKU, motivo ou ID">
                        </div>
                    </div>
                    <div class="stock-actions stock-actions--offset">
                        <input type="hidden" name="stock_movement_page" value="1">
                        <button class="btn" type="submit">Filtrar movimentos</button>
                        <a class="btn secondary" href="<?= htmlspecialchars($buildStockUrl(['stock_movement_search' => '', 'stock_movement_type' => '', 'stock_movement_page' => 1])) ?>">Limpar histórico</a>
                    </div>
                </form>

                <div class="stock-movement-list" style="margin-top:16px">
                    <?php if ($movements === []): ?>
                        <div class="stock-empty">Nenhuma movimentação encontrada para os filtros atuais.</div>
                    <?php endif; ?>

                    <?php foreach ($movements as $movement): ?>
                        <article class="stock-movement">
                            <div class="stock-movement-top">
                                <div class="stock-movement-meta">
                                    <strong>#<?= (int) ($movement['id'] ?? 0) ?> - <?= htmlspecialchars((string) ($movement['stock_item_name'] ?? 'Item')) ?></strong>
                                    <small>
                                        SKU: <?= htmlspecialchars((string) ($movement['stock_item_sku'] ?? 'Não informado')) ?>
                                        | Data: <?= htmlspecialchars($formatDate($movement['moved_at'] ?? null)) ?>
                                    </small>
                                </div>
                                <div style="text-align:right">
                                    <div class="stock-movement-amount"><?= htmlspecialchars($formatQty($movement['quantity'] ?? 0)) ?> <?= htmlspecialchars((string) ($movement['unit_of_measure'] ?? 'un')) ?></div>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('stock_movement_type', $movement['type'] ?? null)) ?>"><?= htmlspecialchars(status_label('stock_movement_type', $movement['type'] ?? null)) ?></span>
                                </div>
                            </div>
                            <div class="stock-movement-grid">
                                <div class="stock-movement-box">
                                    <span>Origem</span>
                                    <strong><?= htmlspecialchars($referenceTypeLabels[(string) ($movement['reference_type'] ?? 'manual')] ?? (string) ($movement['reference_type'] ?? 'manual')) ?></strong>
                                </div>
                                <div class="stock-movement-box">
                                    <span>Responsável</span>
                                    <strong><?= htmlspecialchars((string) ($movement['moved_by_user_name'] ?? 'Sistema')) ?></strong>
                                </div>
                                <div class="stock-movement-box">
                                    <span>Registro</span>
                                    <strong><?= htmlspecialchars($formatDate($movement['created_at'] ?? null)) ?></strong>
                                </div>
                            </div>
                            <?php if (trim((string) ($movement['reason'] ?? '')) !== ''): ?>
                                <div class="stock-form-note"><?= htmlspecialchars((string) ($movement['reason'] ?? '')) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ((int) ($movementPagination['total'] ?? 0) > 0): ?>
                    <div class="stock-pagination" style="margin-top:14px">
                        <div class="stock-note">
                            Exibindo <?= htmlspecialchars((string) ($movementPagination['from'] ?? 0)) ?> a <?= htmlspecialchars((string) ($movementPagination['to'] ?? 0)) ?> de <?= htmlspecialchars((string) ($movementPagination['total'] ?? 0)) ?> movimentos.
                        </div>
                        <?php if ((int) ($movementPagination['last_page'] ?? 1) > 1): ?>
                            <div class="stock-pagination-controls">
                                <?php if ((int) ($movementPagination['page'] ?? 1) > 1): ?>
                                    <a class="stock-page-btn" href="<?= htmlspecialchars($buildStockUrl(['stock_movement_page' => ((int) $movementPagination['page']) - 1])) ?>">Anterior</a>
                                <?php endif; ?>
                                <?php
                                $lastRenderedMovementPage = 0;
                                foreach (is_array($movementPagination['pages'] ?? null) ? $movementPagination['pages'] : [] as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($lastRenderedMovementPage > 0 && $pageNumber - $lastRenderedMovementPage > 1): ?>
                                        <span class="stock-page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a class="stock-page-btn<?= $pageNumber === (int) ($movementPagination['page'] ?? 1) ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildStockUrl(['stock_movement_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                    <?php $lastRenderedMovementPage = $pageNumber; ?>
                                <?php endforeach; ?>
                                <?php if ((int) ($movementPagination['page'] ?? 1) < (int) ($movementPagination['last_page'] ?? 1)): ?>
                                    <a class="stock-page-btn" href="<?= htmlspecialchars($buildStockUrl(['stock_movement_page' => ((int) $movementPagination['page']) + 1])) ?>">Proxima</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>

<script>
    (function () {
        var pickers = document.querySelectorAll('[data-stock-product-picker]');
        if (!pickers.length) {
            return;
        }

        pickers.forEach(function (picker) {
            var searchInput = picker.querySelector('[data-stock-picker-search]');
            var options = Array.prototype.slice.call(picker.querySelectorAll('.stock-product-option'));
            var checkboxes = Array.prototype.slice.call(picker.querySelectorAll('[data-stock-picker-checkbox]'));
            var emptyState = picker.querySelector('[data-stock-picker-empty]');
            var countNode = picker.querySelector('[data-stock-picker-count]');
            var tagsNode = picker.querySelector('[data-stock-picker-tags]');

            var renderSelected = function () {
                if (!countNode || !tagsNode) {
                    return;
                }

                var selected = checkboxes.filter(function (checkbox) {
                    return checkbox.checked;
                }).map(function (checkbox) {
                    return checkbox.getAttribute('data-product-name') || 'Produto';
                });

                countNode.textContent = String(selected.length);

                if (!selected.length) {
                    tagsNode.innerHTML = '<span class="stock-picker-placeholder">Nenhum produto selecionado.</span>';
                    return;
                }

                var tags = selected.slice(0, 4).map(function (name) {
                    return '<span class="stock-picker-tag">' + name.replace(/[&<>"]/g, function (char) {
                        return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[char] || char;
                    }) + '</span>';
                });

                if (selected.length > 4) {
                    tags.push('<span class="stock-picker-tag">+' + (selected.length - 4) + '</span>');
                }

                tagsNode.innerHTML = tags.join('');
            };

            var applyFilter = function () {
                var term = searchInput ? searchInput.value.trim().toLowerCase() : '';
                var visibleCount = 0;

                options.forEach(function (option) {
                    var haystack = option.getAttribute('data-stock-filter-text') || '';
                    var match = term === '' || haystack.indexOf(term) !== -1;
                    option.classList.toggle('is-hidden', !match);
                    if (match) {
                        visibleCount += 1;
                    }
                });

                if (emptyState) {
                    emptyState.hidden = visibleCount > 0;
                }
            };

            if (searchInput) {
                searchInput.addEventListener('input', applyFilter);
            }

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', renderSelected);
            });

            renderSelected();
            applyFilter();
        });
    }());
</script>
