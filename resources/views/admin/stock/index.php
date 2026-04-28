<?php
$stockPanel = is_array($stockPanel ?? null) ? $stockPanel : [];
$summary = is_array($stockPanel['summary'] ?? null) ? $stockPanel['summary'] : [];
$items = is_array($stockPanel['items'] ?? null) ? $stockPanel['items'] : [];
$movements = is_array($stockPanel['movements'] ?? null) ? $stockPanel['movements'] : [];
$filters = is_array($stockPanel['filters'] ?? null) ? $stockPanel['filters'] : [];
$itemPagination = is_array($stockPanel['item_pagination'] ?? null) ? $stockPanel['item_pagination'] : [];
$movementPagination = is_array($stockPanel['movement_pagination'] ?? null) ? $stockPanel['movement_pagination'] : [];
$automaticStockPagination = is_array($stockPanel['automatic_stock_pagination'] ?? null) ? $stockPanel['automatic_stock_pagination'] : [];
$products = is_array($stockPanel['products'] ?? null) ? $stockPanel['products'] : [];
$recipeStockItems = is_array($stockPanel['recipe_stock_items'] ?? null) ? $stockPanel['recipe_stock_items'] : [];
$recipeRows = is_array($stockPanel['recipe_rows'] ?? null) ? $stockPanel['recipe_rows'] : [];
$productionAlerts = is_array($stockPanel['production_alerts'] ?? null) ? $stockPanel['production_alerts'] : [];
$insufficientProducts = is_array($productionAlerts['insufficient_products'] ?? null) ? $productionAlerts['insufficient_products'] : [];
$automaticStockProducts = is_array($stockPanel['automatic_stock_products'] ?? null) ? $stockPanel['automatic_stock_products'] : [];
$soldWithoutAutoStock = $automaticStockProducts;
$stockAutomationReady = !empty($stockPanel['stock_automation_ready']);
$unitOptions = is_array($stockPanel['unit_options'] ?? null) ? $stockPanel['unit_options'] : [];
$referenceTypeOptions = is_array($stockPanel['reference_type_options'] ?? null) ? $stockPanel['reference_type_options'] : [];
$canManageStock = (bool) ($canManageStock ?? false);

$stockSearch = trim((string) ($filters['search'] ?? ''));
$stockStatus = trim((string) ($filters['status'] ?? ''));
$stockAlert = trim((string) ($filters['alert'] ?? ''));
$stockMovementSearch = trim((string) ($filters['movement_search'] ?? ''));
$stockMovementType = trim((string) ($filters['movement_type'] ?? ''));
$stockAutoSearch = trim((string) ($filters['automatic_stock_search'] ?? ''));
$stockAutoIssue = trim((string) ($filters['automatic_stock_issue'] ?? ''));

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

$automaticStockIssueOptions = [
    '' => 'Todas as situações',
    'with_recipe' => 'Com ficha técnica',
    'configured' => 'Com baixa regular',
    'missing_recipe' => 'Sem ficha técnica',
    'missing_consumption' => 'Sem baixa registrada',
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
        if (in_array($key, ['stock_search', 'stock_status', 'stock_alert', 'stock_page', 'stock_movement_search', 'stock_movement_type', 'stock_movement_page', 'stock_auto_search', 'stock_auto_issue', 'stock_auto_page'], true)
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
    $formatted = number_format((float) $value, 3, ',', '.');
    return rtrim(rtrim($formatted, '0'), ',');
};

$formatQtyInput = static function (mixed $value): string {
    $formatted = number_format((float) $value, 3, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
};

$recipesByProductId = [];
foreach ($recipeRows as $recipeRow) {
    $recipeProductId = (int) ($recipeRow['product_id'] ?? 0);
    if ($recipeProductId <= 0) {
        continue;
    }
    if (!isset($recipesByProductId[$recipeProductId])) {
        $recipesByProductId[$recipeProductId] = [];
    }
    $recipesByProductId[$recipeProductId][] = [
        'stock_item_id' => (int) ($recipeRow['stock_item_id'] ?? 0),
        'quantity_per_unit' => $formatQtyInput($recipeRow['quantity_per_unit'] ?? 0),
        'consumption_unit' => (string) ($recipeRow['consumption_unit'] ?? $recipeRow['unit_of_measure'] ?? 'un'),
        'waste_percent' => $formatQtyInput($recipeRow['waste_percent'] ?? 0),
        'stock_item_name' => (string) ($recipeRow['stock_item_name'] ?? ''),
        'unit_of_measure' => (string) ($recipeRow['unit_of_measure'] ?? 'un'),
        'current_quantity' => $formatQty($recipeRow['current_quantity'] ?? 0),
        'minimum_quantity' => $formatQty($recipeRow['minimum_quantity'] ?? 0),
        'current_quantity_raw' => (float) ($recipeRow['current_quantity'] ?? 0),
        'minimum_quantity_raw' => (float) ($recipeRow['minimum_quantity'] ?? 0),
        'stock_item_status' => (string) ($recipeRow['stock_item_status'] ?? 'ativo'),
    ];
}

$stockIngredientStatusMeta = static function (array $row): array {
    $current = (float) ($row['current_quantity_raw'] ?? $row['current_quantity'] ?? 0);
    $minimum = (float) ($row['minimum_quantity_raw'] ?? $row['minimum_quantity'] ?? 0);
    $status = (string) ($row['stock_item_status'] ?? 'ativo');

    if ($status !== 'ativo') {
        return ['label' => 'Inativo', 'class' => 'badge status-overdue'];
    }
    if ($current <= 0) {
        return ['label' => 'Sem saldo', 'class' => 'badge status-overdue'];
    }
    if ($minimum > 0 && $current <= $minimum) {
        return ['label' => 'Estoque baixo', 'class' => 'badge status-pending'];
    }

    return ['label' => 'Disponível', 'class' => 'badge status-active'];
};

$buildSuggestions = static function (array $values): array {
    $suggestions = [];
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $suggestions[$value] = true;
        }
    }

    return array_slice(array_keys($suggestions), 0, 120);
};

$productSuggestions = [];
foreach ($products as $product) {
    $productName = trim((string) ($product['name'] ?? ''));
    $productSku = trim((string) ($product['sku'] ?? ''));
    $productCategory = trim((string) ($product['category_name'] ?? ''));
    $productSuggestions[] = $productName;
    $productSuggestions[] = $productSku;
    $productSuggestions[] = $productName . ($productSku !== '' ? ' | SKU ' . $productSku : '') . ($productCategory !== '' ? ' | ' . $productCategory : '');
    $productSuggestions[] = (string) ($product['category_name'] ?? '');
}
$productSuggestions = $buildSuggestions($productSuggestions);

$ingredientSuggestions = [];
foreach ($recipeStockItems as $stockItem) {
    $stockItemName = trim((string) ($stockItem['name'] ?? ''));
    $stockItemSku = trim((string) ($stockItem['sku'] ?? ''));
    $stockItemUnit = strtoupper((string) ($stockItem['unit_of_measure'] ?? 'un'));
    $ingredientSuggestions[] = $stockItemName;
    $ingredientSuggestions[] = $stockItemSku;
    $ingredientSuggestions[] = $stockItemName . ($stockItemSku !== '' ? ' | SKU ' . $stockItemSku : '') . ' | ' . $stockItemUnit;
    $ingredientSuggestions[] = (string) ($stockItem['unit_of_measure'] ?? '');
}
$ingredientSuggestions = $buildSuggestions($ingredientSuggestions);

$stockSearchSuggestions = array_merge($productSuggestions, $ingredientSuggestions);
foreach ($items as $item) {
    $stockSearchSuggestions[] = (string) ($item['name'] ?? '');
    $stockSearchSuggestions[] = (string) ($item['sku'] ?? '');
    $stockSearchSuggestions[] = (string) ($item['linked_product_names'] ?? '');
}
$stockSearchSuggestions = $buildSuggestions($stockSearchSuggestions);

$movementSearchSuggestions = $stockSearchSuggestions;
foreach ($movements as $movement) {
    $movementSearchSuggestions[] = (string) ($movement['reason'] ?? '');
}
$movementSearchSuggestions = $buildSuggestions($movementSearchSuggestions);

$automaticStockStatusMeta = static function (array $row): array {
    $issue = (string) ($row['issue_type'] ?? '');

    return match ($issue) {
        'missing_recipe' => ['label' => 'Sem ficha técnica', 'class' => 'badge status-overdue'],
        'missing_consumption' => ['label' => 'Sem baixa registrada', 'class' => 'badge status-pending'],
        default => ['label' => 'Com ficha técnica', 'class' => 'badge status-active'],
    };
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
                        list="stock_product_suggestions"
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
    .stock-smart-select-search{margin-bottom:6px}

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

    .stock-recipe-builder{display:grid;gap:12px}
    .stock-recipe-grid{display:grid;gap:8px}
    .stock-recipe-row{display:grid;grid-template-columns:minmax(180px,1.4fr) minmax(120px,.65fr) minmax(90px,.45fr) minmax(100px,.5fr) auto;gap:8px;align-items:end}
    .stock-recipe-row .field{margin:0}
    .stock-recipe-remove{align-self:end;min-height:42px;white-space:nowrap}
    .stock-recipe-current{display:flex;gap:8px;flex-wrap:wrap}
    .stock-recipe-pill{padding:6px 10px;border-radius:999px;border:1px solid #bfdbfe;background:#eff6ff;color:#1e3a8a;font-size:12px;font-weight:700}
    .stock-auto-card{gap:12px}
    .stock-auto-card[open] .stock-auto-edit-toggle{display:none}
    .stock-auto-recipe-summary{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:10px;display:grid;gap:8px}
    .stock-auto-recipe-summary strong{font-size:12px;color:#0f172a}
    .stock-auto-ingredient-list{display:grid;gap:8px}
    .stock-auto-ingredient{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;padding:8px 10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}
    .stock-auto-ingredient span{font-size:12px;color:#334155}
    .stock-auto-recipe-editor{border-top:1px solid #e2e8f0;padding-top:12px}
    .stock-auto-recipe-editor[hidden]{display:none}

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
        .stock-recipe-row{grid-template-columns:1fr}
        .stock-hero h1{font-size:24px}
    }
</style>

<div class="stock-page">
    <datalist id="stock_search_suggestions">
        <?php foreach ($stockSearchSuggestions as $suggestion): ?>
            <option value="<?= htmlspecialchars((string) $suggestion) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <datalist id="stock_product_suggestions">
        <?php foreach ($productSuggestions as $suggestion): ?>
            <option value="<?= htmlspecialchars((string) $suggestion) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <datalist id="stock_ingredient_suggestions">
        <?php foreach ($ingredientSuggestions as $suggestion): ?>
            <option value="<?= htmlspecialchars((string) $suggestion) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <datalist id="stock_movement_suggestions">
        <?php foreach ($movementSearchSuggestions as $suggestion): ?>
            <option value="<?= htmlspecialchars((string) $suggestion) ?>"></option>
        <?php endforeach; ?>
    </datalist>

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
                    <input type="hidden" name="stock_auto_search" value="<?= htmlspecialchars($stockAutoSearch) ?>">
                    <input type="hidden" name="stock_auto_issue" value="<?= htmlspecialchars($stockAutoIssue) ?>">
                    <input type="hidden" name="stock_auto_page" value="<?= htmlspecialchars((string) ($automaticStockPagination['page'] ?? 1)) ?>">

                    <div class="stock-filter-grid">
                        <div class="field">
                            <label for="stock_search">Busca</label>
                            <input id="stock_search" name="stock_search" type="text" value="<?= htmlspecialchars($stockSearch) ?>" placeholder="Item, SKU, produto ou ID" list="stock_search_suggestions" autocomplete="off">
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
                                                    <input id="stock_minimum_<?= (int) ($item['id'] ?? 0) ?>" name="minimum_quantity" type="number" min="0" step="0.001" value="<?= ($item['minimum_quantity'] ?? null) !== null ? htmlspecialchars($formatQtyInput($item['minimum_quantity'])) : '' ?>">
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
                                                    <input id="stock_quantity_<?= (int) ($item['id'] ?? 0) ?>" name="quantity" type="number" min="0.001" step="0.001" placeholder="Use para entrada ou saída">
                                                </div>
                                                <div class="field">
                                                    <label for="stock_target_<?= (int) ($item['id'] ?? 0) ?>">Saldo correto após ajuste</label>
                                                    <input id="stock_target_<?= (int) ($item['id'] ?? 0) ?>" name="target_quantity" type="number" min="0" step="0.001" placeholder="Saldo final contado">
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
                                                <span class="stock-form-note">Entrada e saída usam quantidade. Ajuste usa o saldo correto após contagem. Misturar os dois enfraquece a confiabilidade do histórico.</span>
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
                                    <a class="stock-page-btn" href="<?= htmlspecialchars($buildStockUrl(['stock_page' => ((int) $itemPagination['page']) + 1])) ?>">Próxima</a>
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

            <section class="card">
                <div class="stock-head">
                    <div>
                        <h3>Baixa automática</h3>
                        <p class="stock-note">Gerencie a ficha técnica dos produtos que participam da baixa automática. A baixa acontece quando o pedido fica pago e finalizado.</p>
                    </div>
                    <div class="stock-badges">
                        <span class="badge">Máximo de 15 por página</span>
                    </div>
                </div>
                <div class="stock-summary-grid">
                    <div class="stock-summary-item"><strong>Produtos no controle automático</strong><span><?= (int) ($automaticStockPagination['total'] ?? count($soldWithoutAutoStock)) ?></span></div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/admin/stock')) ?>" style="margin-top:12px">
                    <input type="hidden" name="stock_search" value="<?= htmlspecialchars($stockSearch) ?>">
                    <input type="hidden" name="stock_status" value="<?= htmlspecialchars($stockStatus) ?>">
                    <input type="hidden" name="stock_alert" value="<?= htmlspecialchars($stockAlert) ?>">
                    <input type="hidden" name="stock_page" value="<?= htmlspecialchars((string) ($itemPagination['page'] ?? 1)) ?>">
                    <input type="hidden" name="stock_movement_search" value="<?= htmlspecialchars($stockMovementSearch) ?>">
                    <input type="hidden" name="stock_movement_type" value="<?= htmlspecialchars($stockMovementType) ?>">
                    <input type="hidden" name="stock_movement_page" value="<?= htmlspecialchars((string) ($movementPagination['page'] ?? 1)) ?>">

                    <div class="stock-form-grid">
                        <div class="field">
                            <label for="stock_auto_search">Buscar produto</label>
                            <input id="stock_auto_search" name="stock_auto_search" type="text" value="<?= htmlspecialchars($stockAutoSearch) ?>" placeholder="Produto, SKU ou ID" list="stock_product_suggestions" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="stock_auto_issue">Situação</label>
                            <select id="stock_auto_issue" name="stock_auto_issue">
                                <?php foreach ($automaticStockIssueOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $stockAutoIssue === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="stock-actions stock-actions--offset">
                        <input type="hidden" name="stock_auto_page" value="1">
                        <button class="btn" type="submit">Filtrar baixa automática</button>
                        <a class="btn secondary" href="<?= htmlspecialchars($buildStockUrl(['stock_auto_search' => '', 'stock_auto_issue' => '', 'stock_auto_page' => 1])) ?>">Limpar baixa automática</a>
                    </div>
                </form>

                <div class="stock-movement-list" style="margin-top:12px">
                    <?php if ($soldWithoutAutoStock === []): ?>
                        <div class="stock-empty">Nenhum produto com ficha técnica ou venda finalizada encontrado para baixa automática.</div>
                    <?php else: ?>
                        <?php foreach ($soldWithoutAutoStock as $row): ?>
                            <?php
                            $productId = (int) ($row['product_id'] ?? 0);
                            $currentRecipeRows = $recipesByProductId[$productId] ?? [];
                            $hasRecipe = $currentRecipeRows !== [];
                            $statusMeta = $automaticStockStatusMeta($row);
                            $editorRows = $hasRecipe ? $currentRecipeRows : [[]];
                            ?>
                            <article class="stock-movement stock-auto-card">
                                <div class="stock-movement-top">
                                    <div class="stock-movement-meta">
                                        <strong><?= htmlspecialchars((string) ($row['product_name'] ?? 'Produto')) ?></strong>
                                        <small>
                                            Vendido: <?= htmlspecialchars($formatQty($row['sold_quantity'] ?? 0)) ?> un
                                            | Última venda: <?= htmlspecialchars($formatDate($row['last_sold_at'] ?? null)) ?>
                                            | Pendentes de baixa: <?= (int) ($row['pending_consumption_lines'] ?? 0) ?>
                                        </small>
                                    </div>
                                    <span class="<?= htmlspecialchars($statusMeta['class']) ?>"><?= htmlspecialchars($statusMeta['label']) ?></span>
                                </div>

                                <div class="stock-auto-recipe-summary">
                                    <strong>Ficha técnica</strong>
                                    <div class="stock-recipe-current">
                                        <?php if (!$hasRecipe): ?>
                                            <span class="stock-form-note">Produto sem ficha técnica cadastrada.</span>
                                        <?php else: ?>
                                            <div class="stock-auto-ingredient-list">
                                                <?php foreach ($currentRecipeRows as $recipeRow): ?>
                                                    <?php $ingredientMeta = $stockIngredientStatusMeta($recipeRow); ?>
                                                    <div class="stock-auto-ingredient">
                                                        <span>
                                                            <strong><?= htmlspecialchars((string) ($recipeRow['stock_item_name'] ?? 'Insumo')) ?></strong>:
                                                            <?= htmlspecialchars((string) ($recipeRow['quantity_per_unit'] ?? '0')) ?>
                                                            <?= htmlspecialchars((string) ($recipeRow['consumption_unit'] ?? $recipeRow['unit_of_measure'] ?? 'un')) ?>
                                                            | saldo <?= htmlspecialchars((string) ($recipeRow['current_quantity'] ?? '0')) ?> <?= htmlspecialchars((string) ($recipeRow['unit_of_measure'] ?? 'un')) ?>
                                                        </span>
                                                        <span class="<?= htmlspecialchars($ingredientMeta['class']) ?>"><?= htmlspecialchars($ingredientMeta['label']) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($canManageStock && $stockAutomationReady && $recipeStockItems !== []): ?>
                                    <button class="btn secondary stock-auto-edit-toggle" type="button" data-stock-inline-recipe-edit><?= $hasRecipe ? 'Editar ficha' : 'Criar ficha' ?></button>
                                    <form class="stock-recipe-builder stock-auto-recipe-editor" method="POST" action="<?= htmlspecialchars(base_url('/admin/stock/recipes/update')) ?>" data-stock-inline-recipe-form hidden>
                                        <?= form_security_fields('stock.recipes.update') ?>
                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                        <input type="hidden" name="recipe_product_id" value="<?= $productId ?>">

                                        <div class="stock-recipe-grid" data-stock-inline-recipe-grid>
                                            <?php foreach ($editorRows as $recipeRow): ?>
                                                <div class="stock-recipe-row">
                                                    <div class="field">
                                                        <label>Insumo</label>
                                                        <input type="hidden" name="recipe_stock_item_id[]" value="<?= (int) ($recipeRow['stock_item_id'] ?? 0) ?>" data-stock-ingredient-id>
                                                        <input type="text" value="<?= htmlspecialchars((string) ($recipeRow['stock_item_name'] ?? '')) ?>" placeholder="Buscar insumo" list="stock_ingredient_suggestions" autocomplete="off" data-stock-ingredient-input>
                                                    </div>
                                                    <div class="field">
                                                        <label>Consumo por unidade</label>
                                                        <input name="recipe_quantity_per_unit[]" type="number" min="0.001" step="0.001" value="<?= htmlspecialchars((string) ($recipeRow['quantity_per_unit'] ?? '')) ?>" placeholder="0.000">
                                                    </div>
                                                    <div class="field">
                                                        <label>Unidade</label>
                                                        <select name="recipe_consumption_unit[]" data-stock-consumption-unit data-auto-unit="<?= (int) ($recipeRow['stock_item_id'] ?? 0) > 0 ? '0' : '1' ?>">
                                                            <?php foreach ($unitOptions as $unitOption): ?>
                                                                <?php $selectedUnit = (string) ($recipeRow['consumption_unit'] ?? $recipeRow['unit_of_measure'] ?? 'un'); ?>
                                                                <option value="<?= htmlspecialchars((string) $unitOption) ?>" <?= $selectedUnit === (string) $unitOption ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper((string) $unitOption)) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label>Perda %</label>
                                                        <input name="recipe_waste_percent[]" type="number" min="0" max="100" step="0.01" value="<?= htmlspecialchars((string) ($recipeRow['waste_percent'] ?? '0')) ?>">
                                                    </div>
                                                    <button class="btn secondary stock-recipe-remove" type="button" data-stock-inline-recipe-remove>Remover</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="stock-actions">
                                            <button class="btn secondary" type="button" data-stock-inline-recipe-add>Adicionar insumo</button>
                                            <button class="btn" type="submit">Salvar alteração</button>
                                            <button class="btn secondary" type="button" data-stock-inline-recipe-cancel>Cancelar</button>
                                            <?php if ($hasRecipe): ?>
                                                <button class="btn secondary" type="submit" name="delete_recipe" value="1" data-stock-recipe-delete>Excluir ficha</button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ((int) ($automaticStockPagination['total'] ?? 0) > 0): ?>
                    <div class="stock-pagination" style="margin-top:14px">
                        <div class="stock-note">
                            Exibindo <?= htmlspecialchars((string) ($automaticStockPagination['from'] ?? 0)) ?> a <?= htmlspecialchars((string) ($automaticStockPagination['to'] ?? 0)) ?> de <?= htmlspecialchars((string) ($automaticStockPagination['total'] ?? 0)) ?> registros.
                        </div>
                        <?php if ((int) ($automaticStockPagination['last_page'] ?? 1) > 1): ?>
                            <div class="stock-pagination-controls">
                                <?php if ((int) ($automaticStockPagination['page'] ?? 1) > 1): ?>
                                    <a class="stock-page-btn" href="<?= htmlspecialchars($buildStockUrl(['stock_auto_page' => ((int) $automaticStockPagination['page']) - 1])) ?>">Anterior</a>
                                <?php endif; ?>
                                <?php
                                $lastRenderedAutoStockPage = 0;
                                foreach (is_array($automaticStockPagination['pages'] ?? null) ? $automaticStockPagination['pages'] : [] as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($lastRenderedAutoStockPage > 0 && $pageNumber - $lastRenderedAutoStockPage > 1): ?>
                                        <span class="stock-page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a class="stock-page-btn<?= $pageNumber === (int) ($automaticStockPagination['page'] ?? 1) ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildStockUrl(['stock_auto_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                    <?php $lastRenderedAutoStockPage = $pageNumber; ?>
                                <?php endforeach; ?>
                                <?php if ((int) ($automaticStockPagination['page'] ?? 1) < (int) ($automaticStockPagination['last_page'] ?? 1)): ?>
                                    <a class="stock-page-btn" href="<?= htmlspecialchars($buildStockUrl(['stock_auto_page' => ((int) $automaticStockPagination['page']) + 1])) ?>">Próxima</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <div class="stock-head">
                    <div>
                        <h3>Produção por estoque</h3>
                        <p class="stock-note">Produtos com ficha técnica ficam indisponíveis no cardápio público quando os insumos não sustentam ao menos uma unidade.</p>
                    </div>
                </div>
                <div class="stock-summary-grid">
                    <div class="stock-summary-item"><strong>Produtos controlados por ficha técnica</strong><span><?= (int) ($productionAlerts['controlled_products_count'] ?? 0) ?></span></div>
                    <div class="stock-summary-item"><strong>Indisponíveis por insumo</strong><span><?= (int) ($productionAlerts['insufficient_count'] ?? 0) ?></span></div>
                </div>
                <div class="stock-movement-list" style="margin-top:12px">
                    <?php if ($insufficientProducts === []): ?>
                        <div class="stock-empty">Nenhum produto controlado está bloqueado por falta de insumo.</div>
                    <?php else: ?>
                        <?php foreach ($insufficientProducts as $row): ?>
                            <article class="stock-movement">
                                <div class="stock-movement-top">
                                    <div class="stock-movement-meta">
                                        <strong><?= htmlspecialchars((string) ($row['product_name'] ?? 'Produto')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($row['note'] ?? 'Insumos insuficientes para produção.')) ?></small>
                                    </div>
                                    <span class="badge status-overdue">Indisponível</span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                                <input id="new_stock_initial" name="initial_quantity" type="number" min="0" step="0.001" value="0">
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
                                <?php $renderProductPicker('new_stock', $products, [], 'Use este vínculo quando o item abastece um ou mais produtos. O relacionamento organiza o controle, enquanto a baixa automática por venda depende da ficha técnica configurada no módulo de estoque.'); ?>
                            </div>
                        </div>

                        <div class="stock-actions stock-actions--offset">
                            <button class="btn" type="submit">Cadastrar item</button>
                            <span class="stock-form-note">Se houver saldo inicial, o sistema registra a entrada automaticamente para o item não nascer sem histórico.</span>
                        </div>
                    </form>
                </section>

                <section class="card">
                    <div class="stock-head">
                        <div>
                            <h3>Ficha técnica</h3>
                            <p class="stock-note">Defina quanto cada produto consome de estoque. A baixa automática acontece quando o pedido fica pago e finalizado.</p>
                        </div>
                    </div>

                    <?php if (!$stockAutomationReady): ?>
                        <div class="stock-empty" style="margin-top:12px">A estrutura de ficha técnica ainda não está instalada no banco. Execute o patch SQL de evolução do estoque antes de usar este módulo.</div>
                    <?php elseif ($products === [] || $recipeStockItems === []): ?>
                        <div class="stock-empty" style="margin-top:12px">Cadastre produtos e itens ativos de estoque antes de montar a ficha técnica.</div>
                    <?php else: ?>
                        <form class="stock-recipe-builder" method="POST" action="<?= htmlspecialchars(base_url('/admin/stock/recipes/update')) ?>" data-stock-recipe-form>
                            <?= form_security_fields('stock.recipes.update') ?>
                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                            <div class="field">
                                <label for="recipe_product_search">Produto</label>
                                <input id="recipe_product_id" type="hidden" name="recipe_product_id" data-stock-recipe-product>
                                <input id="recipe_product_search" type="text" placeholder="Buscar produto" list="stock_product_suggestions" autocomplete="off" data-stock-product-input required>
                            </div>

                            <div class="stock-recipe-current" data-stock-recipe-current>
                                <span class="stock-form-note">Selecione um produto para carregar a composição atual.</span>
                            </div>

                            <div class="stock-recipe-grid" data-stock-recipe-grid></div>

                            <div class="stock-actions">
                                <button class="btn secondary" type="button" data-stock-recipe-add>Adicionar insumo</button>
                                <button class="btn" type="submit">Salvar ficha técnica</button>
                            </div>
                            <span class="stock-form-note">Consumo por unidade deve usar a unidade do item de estoque. Ex.: produto vende 1 pizza e consome 0,250 kg de queijo.</span>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="card">
                <div class="stock-head">
                    <div>
                        <h3>Movimentações</h3>
                        <p class="stock-note">Histórico de entradas, saídas e ajustes. O filtro da fila agora é independente do painel principal.</p>
                    </div>
                    <div class="stock-badges">
                        <span class="badge">Máximo de 15 por página</span>
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
                    <input type="hidden" name="stock_auto_search" value="<?= htmlspecialchars($stockAutoSearch) ?>">
                    <input type="hidden" name="stock_auto_issue" value="<?= htmlspecialchars($stockAutoIssue) ?>">
                    <input type="hidden" name="stock_auto_page" value="<?= htmlspecialchars((string) ($automaticStockPagination['page'] ?? 1)) ?>">

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
                            <input id="stock_movement_search_side" name="stock_movement_search" type="text" value="<?= htmlspecialchars($stockMovementSearch) ?>" placeholder="Item, SKU, motivo ou ID" list="stock_movement_suggestions" autocomplete="off">
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
                                    <a class="stock-page-btn" href="<?= htmlspecialchars($buildStockUrl(['stock_movement_page' => ((int) $movementPagination['page']) + 1])) ?>">Próxima</a>
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
        var normalizeText = function (value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim();
        };

        window.stockSmartText = normalizeText;
        window.stockRecipeUnits = <?= json_encode(array_values($unitOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];
        window.stockRecipeStockItems = <?= json_encode(array_map(static fn (array $item): array => [
            'id' => (int) ($item['id'] ?? 0),
            'name' => (string) ($item['name'] ?? 'Item'),
            'sku' => (string) ($item['sku'] ?? ''),
            'unit' => (string) ($item['unit_of_measure'] ?? 'un'),
            'label' => (string) ($item['name'] ?? 'Item') . (trim((string) ($item['sku'] ?? '')) !== '' ? ' | SKU ' . (string) ($item['sku'] ?? '') : '') . ' | ' . strtoupper((string) ($item['unit_of_measure'] ?? 'un')),
        ], $recipeStockItems), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];
        window.stockRecipeProducts = <?= json_encode(array_map(static fn (array $product): array => [
            'id' => (int) ($product['id'] ?? 0),
            'name' => (string) ($product['name'] ?? 'Produto'),
            'sku' => (string) ($product['sku'] ?? ''),
            'category' => (string) ($product['category_name'] ?? ''),
            'label' => (string) ($product['name'] ?? 'Produto') . (trim((string) ($product['sku'] ?? '')) !== '' ? ' | SKU ' . (string) ($product['sku'] ?? '') : '') . (trim((string) ($product['category_name'] ?? '')) !== '' ? ' | ' . (string) ($product['category_name'] ?? '') : ''),
        ], $products), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];

        window.stockUnitOptionsHtml = function (selectedUnit) {
            selectedUnit = String(selectedUnit || 'un').toLowerCase();
            return window.stockRecipeUnits.map(function (unit) {
                unit = String(unit || 'un').toLowerCase();
                return '<option value="' + unit.replace(/[&<>"]/g, '') + '"' + (unit === selectedUnit ? ' selected' : '') + '>' + unit.toUpperCase() + '</option>';
            }).join('');
        };

        window.stockFindIngredient = function (value) {
            var query = normalizeText(value);
            if (!query) {
                return null;
            }

            var exact = window.stockRecipeStockItems.find(function (item) {
                return normalizeText(item.label) === query
                    || normalizeText(item.name) === query
                    || normalizeText(item.sku) === query
                    || String(item.id || '') === String(value || '').trim();
            });
            if (exact) {
                return exact;
            }

            var matches = window.stockRecipeStockItems.filter(function (item) {
                return normalizeText(item.label).indexOf(query) !== -1
                    || normalizeText(item.name).indexOf(query) !== -1
                    || normalizeText(item.sku).indexOf(query) !== -1;
            });

            return matches.length === 1 ? matches[0] : null;
        };

        window.stockIngredientLabel = function (item) {
            if (!item) {
                return '';
            }

            return item.label || (item.name + (item.sku ? ' | SKU ' + item.sku : '') + ' | ' + String(item.unit || 'un').toUpperCase());
        };

        window.stockBindIngredientSearches = function (root) {
            var scope = root || document;
            Array.prototype.slice.call(scope.querySelectorAll('[data-stock-ingredient-input]')).forEach(function (input) {
                if (input.dataset.ingredientReady === '1') {
                    return;
                }

                input.dataset.ingredientReady = '1';
                var row = input.closest('.stock-recipe-row');
                var hidden = row ? row.querySelector('[data-stock-ingredient-id]') : null;
                var unitSelect = row ? row.querySelector('[data-stock-consumption-unit]') : null;
                var sync = function () {
                    var item = window.stockFindIngredient(input.value);
                    if (!item) {
                        if (hidden) {
                            hidden.value = '';
                        }
                        return;
                    }

                    input.value = window.stockIngredientLabel(item);
                    if (hidden) {
                        hidden.value = String(item.id || '');
                    }
                    if (unitSelect && (!unitSelect.value || unitSelect.dataset.autoUnit !== '0')) {
                        unitSelect.value = String(item.unit || 'un').toLowerCase();
                        unitSelect.dataset.autoUnit = '1';
                    }
                };

                input.addEventListener('change', sync);
                input.addEventListener('blur', sync);
                if (unitSelect) {
                    unitSelect.addEventListener('change', function () {
                        unitSelect.dataset.autoUnit = '0';
                    });
                }
            });
        };

        window.stockSyncIngredientInputs = function (root) {
            var scope = root || document;
            Array.prototype.slice.call(scope.querySelectorAll('[data-stock-ingredient-input]')).forEach(function (input) {
                var row = input.closest('.stock-recipe-row');
                var hidden = row ? row.querySelector('[data-stock-ingredient-id]') : null;
                var unitSelect = row ? row.querySelector('[data-stock-consumption-unit]') : null;
                var item = window.stockFindIngredient(input.value);
                if (!item) {
                    return;
                }

                input.value = window.stockIngredientLabel(item);
                if (hidden) {
                    hidden.value = String(item.id || '');
                }
                if (unitSelect && !unitSelect.value) {
                    unitSelect.value = String(item.unit || 'un').toLowerCase();
                }
            });
        };

        window.stockEnhanceSmartSelects = function (root) {
            var scope = root || document;
            var selects = Array.prototype.slice.call(scope.querySelectorAll('select[data-smart-select]'));

            selects.forEach(function (select) {
                if (select.dataset.smartSelectReady === '1') {
                    return;
                }

                select.dataset.smartSelectReady = '1';
                var type = select.getAttribute('data-smart-select') || 'opcao';
                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'stock-smart-select-search';
                input.placeholder = type === 'insumo' ? 'Buscar insumo' : 'Buscar produto';
                input.setAttribute('autocomplete', 'off');
                input.setAttribute('list', type === 'insumo' ? 'stock_ingredient_suggestions' : 'stock_product_suggestions');
                select.parentNode.insertBefore(input, select);

                input.addEventListener('input', function () {
                    var query = normalizeText(input.value);
                    Array.prototype.slice.call(select.options).forEach(function (option) {
                        if (!option.value) {
                            option.hidden = false;
                            return;
                        }

                        option.hidden = query !== '' && normalizeText(option.textContent).indexOf(query) === -1;
                    });
                });
            });
        };

        window.stockEnhanceSmartSelects(document);
        window.stockBindIngredientSearches(document);
    }());

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
                var term = searchInput && window.stockSmartText ? window.stockSmartText(searchInput.value) : (searchInput ? searchInput.value.trim().toLowerCase() : '');
                var visibleCount = 0;

                options.forEach(function (option) {
                    var haystack = window.stockSmartText ? window.stockSmartText(option.getAttribute('data-stock-filter-text') || '') : (option.getAttribute('data-stock-filter-text') || '');
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

    (function () {
        var form = document.querySelector('[data-stock-recipe-form]');
        if (!form) {
            return;
        }

        var recipes = <?= json_encode($recipesByProductId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
        var productSelect = form.querySelector('[data-stock-recipe-product]');
        var productInput = form.querySelector('[data-stock-product-input]');
        var grid = form.querySelector('[data-stock-recipe-grid]');
        var current = form.querySelector('[data-stock-recipe-current]');
        var addButton = form.querySelector('[data-stock-recipe-add]');

        var escapeHtml = function (value) {
            return String(value || '').replace(/[&<>"]/g, function (char) {
                return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[char] || char;
            });
        };

        var findProduct = function (value) {
            var query = window.stockSmartText ? window.stockSmartText(value) : String(value || '').toLowerCase().trim();
            if (!query) {
                return null;
            }

            var exact = (window.stockRecipeProducts || []).find(function (product) {
                return (window.stockSmartText ? window.stockSmartText(product.label) : String(product.label || '').toLowerCase()) === query
                    || (window.stockSmartText ? window.stockSmartText(product.name) : String(product.name || '').toLowerCase()) === query
                    || (window.stockSmartText ? window.stockSmartText(product.sku) : String(product.sku || '').toLowerCase()) === query
                    || String(product.id || '') === String(value || '').trim();
            });
            if (exact) {
                return exact;
            }

            var matches = (window.stockRecipeProducts || []).filter(function (product) {
                var label = window.stockSmartText ? window.stockSmartText(product.label) : String(product.label || '').toLowerCase();
                var name = window.stockSmartText ? window.stockSmartText(product.name) : String(product.name || '').toLowerCase();
                var sku = window.stockSmartText ? window.stockSmartText(product.sku) : String(product.sku || '').toLowerCase();
                return label.indexOf(query) !== -1 || name.indexOf(query) !== -1 || sku.indexOf(query) !== -1;
            });

            return matches.length === 1 ? matches[0] : null;
        };

        var addRow = function (row) {
            if (!grid) {
                return;
            }

            row = row || {};
            var wrapper = document.createElement('div');
            wrapper.className = 'stock-recipe-row';
            var item = window.stockFindIngredient ? window.stockFindIngredient(row.stock_item_id || row.stock_item_name || '') : null;
            var ingredientLabel = row.stock_item_name || (window.stockIngredientLabel ? window.stockIngredientLabel(item) : '');
            var unit = row.consumption_unit || (item ? item.unit : row.unit_of_measure) || 'un';
            wrapper.innerHTML = ''
                + '<div class="field"><label>Insumo</label><input type="hidden" name="recipe_stock_item_id[]" value="' + Number(row.stock_item_id || 0) + '" data-stock-ingredient-id><input type="text" value="' + escapeHtml(ingredientLabel) + '" placeholder="Buscar insumo" list="stock_ingredient_suggestions" autocomplete="off" data-stock-ingredient-input></div>'
                + '<div class="field"><label>Consumo por unidade</label><input name="recipe_quantity_per_unit[]" type="number" min="0.001" step="0.001" value="' + escapeHtml(row.quantity_per_unit || '') + '" placeholder="0.000"></div>'
                + '<div class="field"><label>Unidade</label><select name="recipe_consumption_unit[]" data-stock-consumption-unit data-auto-unit="' + (row.stock_item_id ? '0' : '1') + '">' + (window.stockUnitOptionsHtml ? window.stockUnitOptionsHtml(unit) : '') + '</select></div>'
                + '<div class="field"><label>Perda %</label><input name="recipe_waste_percent[]" type="number" min="0" max="100" step="0.01" value="' + escapeHtml(row.waste_percent || '0') + '"></div>'
                + '<button class="btn secondary stock-recipe-remove" type="button" data-stock-recipe-remove>Remover</button>';
            grid.appendChild(wrapper);
            if (window.stockBindIngredientSearches) {
                window.stockBindIngredientSearches(wrapper);
            }
        };

        var renderCurrent = function (rows) {
            if (!current) {
                return;
            }

            if (!rows.length) {
                current.innerHTML = '<span class="stock-form-note">Produto sem ficha técnica cadastrada.</span>';
                return;
            }

            current.innerHTML = rows.map(function (row) {
                return '<span class="stock-recipe-pill">' + escapeHtml(row.stock_item_name) + ': ' + escapeHtml(row.quantity_per_unit) + ' ' + escapeHtml(row.consumption_unit || row.unit_of_measure || 'un') + '</span>';
            }).join('');
        };

        var loadSelectedProduct = function () {
            if (!grid || !productSelect) {
                return;
            }

            var productId = String(productSelect.value || '');
            var rows = Array.isArray(recipes[productId]) ? recipes[productId] : [];
            grid.innerHTML = '';
            renderCurrent(rows);

            if (!productId) {
                current.innerHTML = '<span class="stock-form-note">Selecione um produto para carregar a composição atual.</span>';
                return;
            }

            if (rows.length) {
                rows.forEach(addRow);
            } else {
                addRow();
            }
        };

        if (productInput && productSelect) {
            var syncProduct = function () {
                var product = findProduct(productInput.value);
                if (!product) {
                    productSelect.value = '';
                    grid.innerHTML = '';
                    current.innerHTML = '<span class="stock-form-note">Informe um produto válido para carregar a composição atual.</span>';
                    return;
                }

                productInput.value = product.label || product.name;
                productSelect.value = String(product.id || '');
                loadSelectedProduct();
            };
            productInput.addEventListener('change', syncProduct);
            productInput.addEventListener('blur', syncProduct);
        }
        if (addButton) {
            addButton.addEventListener('click', function () {
                addRow();
            });
        }
        form.addEventListener('submit', function () {
            if (window.stockSyncIngredientInputs) {
                window.stockSyncIngredientInputs(form);
            }
            if (productInput && productSelect && !productSelect.value) {
                var product = findProduct(productInput.value);
                if (product) {
                    productSelect.value = String(product.id || '');
                }
            }
        });
        if (grid) {
            grid.addEventListener('click', function (event) {
                var target = event.target;
                if (target && target.matches('[data-stock-recipe-remove]')) {
                    var row = target.closest('.stock-recipe-row');
                    if (row) {
                        row.remove();
                    }
                }
            });
        }

        loadSelectedProduct();
    }());

    (function () {
        var forms = Array.prototype.slice.call(document.querySelectorAll('[data-stock-inline-recipe-form]'));
        if (!forms.length) {
            return;
        }

        var escapeHtml = function (value) {
            return String(value || '').replace(/[&<>"]/g, function (char) {
                return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'})[char] || char;
            });
        };

        var addRow = function (grid) {
            var wrapper = document.createElement('div');
            wrapper.className = 'stock-recipe-row';
            wrapper.innerHTML = ''
                + '<div class="field"><label>Insumo</label><input type="hidden" name="recipe_stock_item_id[]" value="" data-stock-ingredient-id><input type="text" value="" placeholder="Buscar insumo" list="stock_ingredient_suggestions" autocomplete="off" data-stock-ingredient-input></div>'
                + '<div class="field"><label>Consumo por unidade</label><input name="recipe_quantity_per_unit[]" type="number" min="0.001" step="0.001" placeholder="0.000"></div>'
                + '<div class="field"><label>Unidade</label><select name="recipe_consumption_unit[]" data-stock-consumption-unit data-auto-unit="1">' + (window.stockUnitOptionsHtml ? window.stockUnitOptionsHtml('un') : '') + '</select></div>'
                + '<div class="field"><label>Perda %</label><input name="recipe_waste_percent[]" type="number" min="0" max="100" step="0.01" value="0"></div>'
                + '<button class="btn secondary stock-recipe-remove" type="button" data-stock-inline-recipe-remove>Remover</button>';
            grid.appendChild(wrapper);
            if (window.stockBindIngredientSearches) {
                window.stockBindIngredientSearches(wrapper);
            }
        };

        forms.forEach(function (form) {
            var card = form.closest('.stock-auto-card');
            var editButton = card ? card.querySelector('[data-stock-inline-recipe-edit]') : null;
            var grid = form.querySelector('[data-stock-inline-recipe-grid]');
            var initialHtml = form.innerHTML;

            if (editButton) {
                editButton.addEventListener('click', function () {
                    form.hidden = false;
                    editButton.hidden = true;
                });
            }

            form.addEventListener('submit', function () {
                if (window.stockSyncIngredientInputs) {
                    window.stockSyncIngredientInputs(form);
                }
            });

            form.addEventListener('click', function (event) {
                var target = event.target;
                if (!target) {
                    return;
                }

                if (target.matches('[data-stock-inline-recipe-add]') && grid) {
                    addRow(grid);
                }

                if (target.matches('[data-stock-inline-recipe-remove]')) {
                    var row = target.closest('.stock-recipe-row');
                    if (row) {
                        row.remove();
                    }
                }

                if (target.matches('[data-stock-inline-recipe-cancel]')) {
                    form.innerHTML = initialHtml;
                    grid = form.querySelector('[data-stock-inline-recipe-grid]');
                    if (window.stockBindIngredientSearches) {
                        window.stockBindIngredientSearches(form);
                    }
                    form.hidden = true;
                    if (editButton) {
                        editButton.hidden = false;
                    }
                }

                if (target.matches('[data-stock-recipe-delete]') && !window.confirm('Excluir a ficha técnica deste produto?')) {
                    event.preventDefault();
                }
            });
        });
    }());
</script>
