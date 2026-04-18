<?php
$access = is_array($access ?? null) ? $access : [];
$company = is_array($access['company'] ?? null) ? $access['company'] : [];
$table = is_array($access['table'] ?? null) ? $access['table'] : [];
$menuTheme = is_array($menuTheme ?? null) ? $menuTheme : [];
$categories = is_array($categories ?? null) ? $categories : [];
$products = is_array($products ?? null) ? $products : [];
$currentCommand = is_array($currentCommand ?? null) ? $currentCommand : null;
$currentCommandPanel = is_array($currentCommandPanel ?? null) ? $currentCommandPanel : ['summary' => [], 'orders' => []];
$currentSummary = is_array($currentCommandPanel['summary'] ?? null) ? $currentCommandPanel['summary'] : [];
$tableCommands = is_array($tableCommands ?? null) ? $tableCommands : [];
$tableSummary = is_array($tableSummary ?? null) ? $tableSummary : [];
$openCommandsCount = (int) ($openCommandsCount ?? 0);
$refreshIntervalSeconds = max(1200, (int) ($refreshIntervalSeconds ?? 1200));
$refreshIntervalLabel = $refreshIntervalSeconds % 60 === 0
    ? ((int) ($refreshIntervalSeconds / 60)) . ' min'
    : $refreshIntervalSeconds . ' s';
$fatalError = trim((string) ($fatalError ?? ''));
$companySlug = trim((string) ($company['slug'] ?? ''));
$tableNumber = (int) ($table['number'] ?? 0);
$token = trim((string) ($access['token'] ?? ''));
$currentCommandId = (int) ($currentCommand['id'] ?? 0);
$menuBaseUrl = $companySlug !== '' && $tableNumber > 0 && $token !== ''
    ? base_url('/menu-digital?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token))
    : base_url('/menu-digital');
$openCommandAction = $menuBaseUrl !== '' ? str_replace('/menu-digital?', '/menu-digital/command/open?', $menuBaseUrl) : base_url('/menu-digital/command/open');
$cartUrl = base_url('/menu-digital/cart?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token));
$tableTicketUrl = base_url('/menu-digital/ticket?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token) . '&scope=table');
$cartStorageKey = 'digital-menu-cart:' . $companySlug . ':' . $tableNumber . ':' . $token . ':' . $currentCommandId;
$lastOrderId = isset($_GET['last_order_id']) ? (int) $_GET['last_order_id'] : 0;
$formatMoney = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$formatDate = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : $raw;
};
$statusLabels = [
    'pending' => 'Aguardando producao',
    'received' => 'Recebido na cozinha',
    'preparing' => 'Em preparo',
    'ready' => 'Pronto para entrega',
    'delivered' => 'Entregue na mesa',
    'finished' => 'Finalizado',
    'canceled' => 'Cancelado',
];
$productsJson = json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if (!is_string($productsJson)) {
    $productsJson = '[]';
}
?>

<style>
    .dm-dashboard{display:grid;gap:18px;padding-bottom:132px}
    .dm-dashboard,.dm-dashboard > *,.dm-main-grid,.dm-main-grid > *,.dm-stack,.dm-side-stack,.dm-section-head,.dm-section-head > *,.dm-category-nav,.dm-category-nav > *,.dm-product-card,.dm-product-card > *,.dm-command-entry,.dm-command-entry > *,.dm-order-card,.dm-order-card > *,.dm-modal-head,.dm-modal-head > *{min-width:0}
    .dm-signal-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .dm-signal{padding:16px;border-radius:20px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);backdrop-filter:blur(12px)}
    .dm-signal strong{display:block;font-size:30px;line-height:1}
    .dm-signal span{display:block;margin-top:7px;font-size:12px;color:rgba(255,255,255,.82)}
    .dm-main-grid{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(280px,.75fr);gap:18px;align-items:start}
    .dm-stack,.dm-side-stack{display:grid;gap:18px}
    .dm-side-stack{position:sticky;top:calc(var(--dm-topbar-offset, 76px) + 12px)}
    .dm-glass-card{
        background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
        border:1px solid rgba(219,228,240,.96);
        border-radius:26px;
        box-shadow:var(--dm-shadow);
        padding:18px;
    }
    .dm-section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
    .dm-section-head h2{margin:0;font-size:24px;letter-spacing:-.02em}
    .dm-section-head p{margin:6px 0 0;color:var(--dm-muted);font-size:14px;max-width:760px;line-height:1.5}
    .dm-chip-row{display:flex;gap:8px;flex-wrap:wrap}
    .dm-chip{
        display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;
        border:1px solid var(--dm-border);background:#fff;color:var(--dm-secondary);font-size:12px;font-weight:700
    }
    .dm-chip.is-current{background:color-mix(in srgb, var(--dm-primary) 10%, white 90%);border-color:color-mix(in srgb, var(--dm-primary) 28%, white 72%)}
    .dm-quick-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .dm-quick-card{padding:14px;border-radius:20px;background:var(--dm-surface-soft);border:1px solid var(--dm-border)}
    .dm-quick-card strong{display:block;font-size:24px;line-height:1}
    .dm-quick-card span{display:block;margin-top:6px;font-size:12px;color:var(--dm-muted)}
    .dm-open-form{display:grid;gap:12px}
    .dm-grid-two{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}
    .dm-note{font-size:13px;color:var(--dm-muted);line-height:1.55}
    .dm-command-board{display:grid;gap:14px}
    .dm-command-entry{padding:16px;border-radius:22px;border:1px solid var(--dm-border);background:#fff}
    .dm-command-entry.is-current{border-color:color-mix(in srgb, var(--dm-primary) 36%, white 64%);box-shadow:0 14px 32px rgba(29,78,216,.08)}
    .dm-command-header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .dm-command-title{display:grid;gap:4px}
    .dm-command-title strong{font-size:19px;line-height:1.1;overflow-wrap:anywhere}
    .dm-command-title small{font-size:12px;color:var(--dm-muted);line-height:1.4;overflow-wrap:anywhere}
    .dm-command-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:14px}
    .dm-command-metric{padding:12px;border-radius:16px;background:var(--dm-surface-soft);border:1px solid var(--dm-border)}
    .dm-command-metric strong{display:block;font-size:18px;line-height:1}
    .dm-command-metric span{display:block;margin-top:5px;font-size:11px;color:var(--dm-muted)}
    .dm-order-list{display:grid;gap:10px;margin-top:12px}
    .dm-order-card{padding:14px;border-radius:18px;background:var(--dm-surface-soft);border:1px solid var(--dm-border)}
    .dm-order-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .dm-order-head strong{font-size:14px;overflow-wrap:anywhere}
    .dm-order-head small{display:block;margin-top:4px;color:var(--dm-muted);font-size:12px;overflow-wrap:anywhere}
    .dm-order-items{display:grid;gap:8px;margin-top:10px}
    .dm-order-item{padding:10px 12px;border-radius:14px;background:#fff;border:1px solid var(--dm-border)}
    .dm-order-item strong{display:block;font-size:13px;overflow-wrap:anywhere}
    .dm-order-item span,.dm-order-item small{display:block;margin-top:5px;color:var(--dm-muted);font-size:12px;line-height:1.45;overflow-wrap:anywhere}
    .dm-action-bar{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .dm-menu-shell{display:grid;gap:16px;position:relative}
    .dm-menu-wrap{display:grid;gap:16px}
    .dm-category-nav-anchor{height:0}
    .dm-category-nav{
        position:relative;z-index:15;display:flex;gap:10px;overflow-x:auto;overflow-y:hidden;padding:6px;align-self:start;
        margin:0;border-radius:22px;background:rgba(248,250,252,.92);backdrop-filter:blur(16px);border:1px solid rgba(219,228,240,.9);
        transition:box-shadow .18s ease,border-color .18s ease,background .18s ease
    }
    .dm-menu-shell.is-floating .dm-category-nav{
        position:fixed;top:calc(var(--dm-topbar-offset, 76px) + 4px);left:var(--dm-menu-nav-left, 0px);width:var(--dm-menu-nav-width, auto);
        max-width:var(--dm-menu-nav-width, auto);z-index:19;box-shadow:0 14px 30px rgba(15,23,42,.12);border-color:rgba(203,213,225,.96)
    }
    .dm-menu-shell.is-bottom .dm-category-nav{
        position:absolute;left:0;top:var(--dm-menu-nav-stop, 0px);width:100%;box-shadow:0 14px 30px rgba(15,23,42,.12);border-color:rgba(203,213,225,.96)
    }
    .dm-menu-shell.is-floating .dm-category-nav-anchor,
    .dm-menu-shell.is-bottom .dm-category-nav-anchor{height:var(--dm-menu-nav-height, 0px)}
    .dm-category-nav::-webkit-scrollbar{display:none}
    .dm-category-tab{
        flex:0 0 auto;border:1px solid var(--dm-border);background:#fff;border-radius:999px;padding:10px 14px;cursor:pointer;
        font:inherit;font-weight:700;color:var(--dm-secondary);white-space:nowrap;transition:background .15s ease,border-color .15s ease,color .15s ease
    }
    .dm-category-tab.active{background:var(--dm-primary);border-color:var(--dm-primary);color:#fff}
    .dm-category-list{display:grid;gap:18px}
    .dm-category-section{display:grid;gap:12px;scroll-margin-top:calc(var(--dm-topbar-offset, 76px) + 84px)}
    .dm-category-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .dm-category-head h3{margin:0;font-size:22px}
    .dm-category-copy{max-width:780px}
    .dm-category-copy p{margin:6px 0 0;color:var(--dm-muted);font-size:13px;line-height:1.5}
    .dm-refresh-status{font-size:12px;color:var(--dm-muted)}
    .dm-product-list{display:grid;gap:12px}
    .dm-product-card{
        position:relative;display:grid;grid-template-columns:108px minmax(0,1fr);gap:14px;padding:14px;border:1px solid var(--dm-border);
        border-radius:24px;background:linear-gradient(180deg,#fff,#f9fbfd)
    }
    .dm-product-image{
        width:108px;height:108px;border-radius:18px;overflow:hidden;background:linear-gradient(135deg,#e0ecff,#eef4fb);
        display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--dm-muted)
    }
    .dm-product-image img{width:100%;height:100%;object-fit:cover}
    .dm-product-body{display:grid;gap:8px;min-width:0}
    .dm-product-body strong{font-size:17px;line-height:1.15;overflow-wrap:anywhere}
    .dm-product-body p{margin:0;color:var(--dm-muted);font-size:13px;line-height:1.5;overflow-wrap:anywhere}
    .dm-tag-row{display:flex;gap:6px;flex-wrap:wrap}
    .dm-tag{display:inline-flex;padding:6px 9px;border-radius:999px;background:var(--dm-surface-soft);border:1px solid var(--dm-border);font-size:11px;font-weight:700;color:var(--dm-muted)}
    .dm-product-meta{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
    .dm-price{font-size:20px;font-weight:800;color:var(--dm-secondary)}
    .dm-empty{padding:18px;border-radius:20px;border:1px dashed var(--dm-border);background:var(--dm-surface-soft);color:var(--dm-muted)}
    .dm-cart-dock{
        position:fixed;left:0;right:0;bottom:0;z-index:40;padding:12px 12px calc(12px + env(safe-area-inset-bottom,0px));
        pointer-events:none
    }
    .dm-cart-dock-inner{
        width:min(1180px,calc(100vw - 16px));max-width:calc(100vw - 16px);margin:0 auto;pointer-events:auto;
        display:flex;justify-content:space-between;gap:12px;align-items:center;padding:14px 16px;border-radius:24px;
        border:1px solid rgba(255,255,255,.22);
        background:linear-gradient(135deg,color-mix(in srgb, var(--dm-main-card) 92%, black 8%),color-mix(in srgb, var(--dm-primary) 78%, var(--dm-main-card) 22%) 100%);
        color:#fff;box-shadow:0 -12px 34px rgba(15,23,42,.22)
    }
    .dm-cart-dock-summary{display:grid;gap:4px;min-width:0}
    .dm-cart-dock-summary strong{font-size:15px;overflow-wrap:anywhere}
    .dm-cart-dock-summary span{font-size:12px;color:rgba(255,255,255,.78)}
    .dm-cart-dock-total{font-size:20px;font-weight:800}
    .dm-cart-dock .btn{background:#fff;color:var(--dm-secondary);min-width:170px}
    .dm-cart-dock[hidden]{display:none !important}
    .dm-modal[hidden]{display:none !important}
    .dm-modal{position:fixed;inset:0;z-index:60;background:rgba(15,23,42,.58);display:grid;place-items:end center;padding:14px}
    .dm-modal-sheet{width:min(720px,100%);max-height:min(86vh,920px);overflow:auto;background:#fff;border-radius:24px 24px 18px 18px;padding:18px;display:grid;gap:14px;box-shadow:0 30px 70px rgba(15,23,42,.24)}
    .dm-modal-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
    .dm-modal-head h3{margin:0;font-size:22px}
    .dm-modal-head p{margin:6px 0 0;color:var(--dm-muted);font-size:14px}
    .dm-qty-stepper{display:flex;align-items:center;gap:10px}
    .dm-step-btn{width:42px;height:42px;border-radius:14px;border:1px solid var(--dm-border);background:#fff;font-size:22px;cursor:pointer}
    .dm-step-value{min-width:24px;text-align:center;font-weight:800}
    .dm-additionals-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .dm-additional-card{position:relative;padding:12px;border-radius:16px;border:1px solid var(--dm-border);background:var(--dm-surface-soft);display:grid;gap:8px}
    .dm-additional-card input{position:absolute;opacity:0;pointer-events:none}
    .dm-additional-card.is-selected{border-color:var(--dm-primary);background:color-mix(in srgb, var(--dm-primary) 10%, white 90%)}
    .dm-additional-card strong{font-size:13px}
    .dm-additional-card span{font-size:12px;color:var(--dm-muted)}
    .dm-additional-controls{display:flex;align-items:center;justify-content:space-between;gap:8px}
    .dm-additional-toggle{border:1px solid color-mix(in srgb, var(--dm-primary) 28%, white 72%);background:#fff;color:var(--dm-primary);border-radius:999px;padding:7px 10px;font-size:11px;font-weight:800;cursor:pointer}
    .dm-additional-card.is-selected .dm-additional-toggle{background:var(--dm-primary);color:#fff;border-color:var(--dm-primary)}
    .dm-additional-qty{display:inline-flex;align-items:center;gap:6px}
    .dm-additional-qty button{width:28px;height:28px;border-radius:999px;border:1px solid color-mix(in srgb, var(--dm-primary) 28%, white 72%);background:#fff;color:var(--dm-primary);font-weight:800;cursor:pointer}
    .dm-additional-qty button:disabled{opacity:.45;cursor:not-allowed}
    .dm-additional-qty strong{min-width:18px;text-align:center;font-size:13px}
    .dm-modal-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
    @media (max-width:1120px){
        .dm-main-grid{grid-template-columns:1fr}
        .dm-side-stack{position:static}
    }
    @media (max-width:900px){
        .dm-signal-grid,.dm-command-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
        .dm-grid-two,.dm-additionals-grid,.dm-quick-grid{grid-template-columns:1fr}
        .dm-action-bar .btn,.dm-action-bar .btn-secondary,.dm-action-bar .btn-soft{width:100%}
    }
    @media (max-width:640px){
        .dm-dashboard{padding-bottom:144px}
        .dm-product-card{grid-template-columns:86px minmax(0,1fr)}
        .dm-product-image{width:86px;height:86px}
        .dm-command-header,.dm-order-head,.dm-product-meta,.dm-modal-head,.dm-cart-dock-inner{flex-direction:column;align-items:stretch}
        .dm-cart-dock .btn{width:100%;min-width:0}
        .dm-action-bar,.dm-modal-actions{flex-direction:column}
        .dm-modal{padding:8px}
        .dm-modal-sheet{padding:14px}
    }
</style>

<?php if ($fatalError !== ''): ?>
    <section class="dm-card dm-hero">
        <div class="dm-hero-grid">
            <div class="dm-hero-copy">
                <span class="dm-eyebrow">Acesso da mesa</span>
                <h1>Menu digital indisponivel</h1>
                <p><?= htmlspecialchars($fatalError) ?></p>
            </div>
            <div class="dm-empty" style="color:#fff;border-color:rgba(255,255,255,.18);background:rgba(255,255,255,.08)">
                Releia o QR Code oficial da mesa para tentar novamente.
            </div>
        </div>
    </section>
<?php else: ?>
    <div class="dm-dashboard">
        <section class="dm-card dm-hero">
            <div class="dm-hero-grid">
                <div class="dm-hero-copy">
                    <span class="dm-eyebrow">Mesa vinculada ao QR Code</span>
                    <h1><?= htmlspecialchars(trim((string) ($table['name'] ?? 'Mesa ' . $tableNumber))) ?></h1>
                    <p><?= htmlspecialchars(trim((string) ($menuTheme['description'] ?? '')) !== '' ? (string) $menuTheme['description'] : 'Abra sua comanda, acompanhe os pedidos da mesa e faca seus pedidos com rapidez direto do celular.') ?></p>
                    <div class="dm-chip-row">
                        <span class="dm-pill">Mesa <?= $tableNumber ?></span>
                        <span class="dm-pill"><?= htmlspecialchars((string) ($company['name'] ?? 'Estabelecimento')) ?></span>
                        <span class="dm-pill"><?= $openCommandsCount ?> comanda(s) aberta(s)</span>
                    </div>
                </div>
                <div class="dm-signal-grid">
                    <div class="dm-signal">
                        <strong><?= (int) ($tableSummary['commands_count'] ?? 0) ?></strong>
                        <span>Comandas abertas na mesa</span>
                    </div>
                    <div class="dm-signal">
                        <strong><?= (int) ($tableSummary['orders_count'] ?? 0) ?></strong>
                        <span>Pedidos lancados na mesa</span>
                    </div>
                    <div class="dm-signal">
                        <strong><?= $formatMoney((float) ($tableSummary['total_amount'] ?? 0)) ?></strong>
                        <span>Valor acumulado da mesa</span>
                    </div>
                    <div class="dm-signal">
                        <strong><?= $currentCommand !== null ? '#' . $currentCommandId : '---' ?></strong>
                        <span><?= $currentCommand !== null ? 'Sua comanda atual' : 'Abra sua comanda para pedir' ?></span>
                    </div>
                </div>
            </div>
        </section>

        <div class="dm-main-grid">
            <main class="dm-stack">
                <?php if ($currentCommand !== null): ?>
                    <section class="dm-glass-card">
                        <div class="dm-section-head">
                            <div>
                                <h2>Sua comanda ativa</h2>
                                <p>Voce enxerga todas as comandas abertas desta mesa, mas so consegue lancar pedidos na sua propria comanda neste aparelho.</p>
                            </div>
                            <div class="dm-chip-row">
                                <span class="dm-chip is-current">Comanda #<?= $currentCommandId ?></span>
                                <span class="dm-chip"><?= htmlspecialchars((string) ($currentCommand['customer_name'] ?? 'Cliente')) ?></span>
                            </div>
                        </div>

                        <div class="dm-quick-grid">
                            <div class="dm-quick-card"><strong><?= (int) ($currentSummary['total_orders'] ?? 0) ?></strong><span>Pedidos da sua comanda</span></div>
                            <div class="dm-quick-card"><strong><?= (int) ($currentSummary['preparing'] ?? 0) + (int) ($currentSummary['received'] ?? 0) ?></strong><span>Em producao</span></div>
                            <div class="dm-quick-card"><strong><?= (int) ($currentSummary['ready'] ?? 0) ?></strong><span>Prontos</span></div>
                            <div class="dm-quick-card"><strong><?= $formatMoney((float) ($currentSummary['total_amount'] ?? 0)) ?></strong><span>Total da sua comanda</span></div>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="dm-glass-card">
                        <div class="dm-section-head">
                            <div>
                                <h2>Abrir sua comanda</h2>
                                <p>Cada pessoa da mesa pode abrir uma comanda propria pelo mesmo QR Code. Depois disso, os pedidos ficam vinculados somente a sua comanda neste dispositivo.</p>
                            </div>
                        </div>
                        <form class="dm-open-form" method="POST" action="<?= htmlspecialchars($openCommandAction) ?>">
                            <?= form_security_fields('digital_menu.command.open') ?>
                            <div class="dm-grid-two">
                                <div class="field">
                                    <label for="customer_name">Nome na comanda</label>
                                    <input id="customer_name" name="customer_name" type="text" maxlength="120" placeholder="Ex.: Ana, Joao, Mesa da empresa">
                                </div>
                                <div class="field">
                                    <label for="command_notes">Observacao da comanda</label>
                                    <input id="command_notes" name="notes" type="text" maxlength="255" placeholder="Opcional">
                                </div>
                            </div>
                            <div class="dm-note">A mesa pode ter varias comandas abertas ao mesmo tempo, mas este aparelho ficara vinculado somente a comanda que voce abrir aqui.</div>
                            <div class="dm-action-bar">
                                <button class="btn" type="submit">Abrir minha comanda</button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="dm-glass-card">
                        <div class="dm-section-head">
                            <div>
                                <h2>Cardapio por categoria</h2>
                            </div>
                        </div>

                    <?php if ($categories === []): ?>
                        <div class="dm-empty">Nenhum produto ativo foi encontrado no cardapio desta empresa.</div>
                    <?php else: ?>
                        <div class="dm-menu-shell" id="digitalMenuShell">
                            <div class="dm-category-nav-anchor" id="digitalCategoryNavAnchor" aria-hidden="true"></div>
                            <nav class="dm-category-nav" id="categoryTabs" aria-label="Categorias do cardapio">
                                <?php foreach ($categories as $index => $category): ?>
                                    <?php $categoryKey = (string) ($category['key'] ?? 'category-' . $index); ?>
                                    <button
                                        class="dm-category-tab<?= $index === 0 ? ' active' : '' ?>"
                                        type="button"
                                        data-category-tab="<?= htmlspecialchars($categoryKey) ?>"
                                    >
                                        <?= htmlspecialchars((string) ($category['name'] ?? 'Categoria')) ?>
                                    </button>
                                <?php endforeach; ?>
                            </nav>

                            <div class="dm-menu-wrap">
                            <div class="dm-category-list">
                                <?php foreach ($categories as $index => $category): ?>
                                    <?php
                                    $categoryKey = (string) ($category['key'] ?? 'category-' . $index);
                                    $categoryProducts = is_array($category['products'] ?? null) ? $category['products'] : [];
                                    ?>
                                    <section class="dm-category-section" id="category-<?= htmlspecialchars($categoryKey) ?>" data-category-section="<?= htmlspecialchars($categoryKey) ?>">
                                        <div class="dm-category-head">
                                            <div class="dm-category-copy">
                                                <h3><?= htmlspecialchars((string) ($category['name'] ?? 'Categoria')) ?></h3>
                                                <p><?= count($categoryProducts) ?> produto(s) disponivel(is) nesta categoria.</p>
                                            </div>
                                            <span class="dm-refresh-status"><?= count($categoryProducts) ?> produto(s)</span>
                                        </div>

                                        <div class="dm-product-list">
                                            <?php foreach ($categoryProducts as $product): ?>
                                                <?php
                                                $price = $product['promotional_price'] !== null
                                                    ? (float) $product['promotional_price']
                                                    : (float) ($product['price'] ?? 0);
                                                $imageUrl = product_image_url((string) ($product['image_path'] ?? ''));
                                                $additionals = is_array($product['additionals'] ?? null) ? $product['additionals'] : [];
                                                ?>
                                                <article class="dm-product-card">
                                                    <div class="dm-product-image">
                                                        <?php if ($imageUrl !== ''): ?>
                                                            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars((string) ($product['name'] ?? 'Produto')) ?>">
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(substr((string) ($product['name'] ?? 'PD'), 0, 2)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="dm-product-body">
                                                        <strong><?= htmlspecialchars((string) ($product['name'] ?? 'Produto')) ?></strong>
                                                        <p><?= htmlspecialchars((string) ($product['description'] ?? 'Sem descricao informada.')) ?></p>
                                                        <div class="dm-tag-row">
                                                            <?php if ($additionals !== []): ?>
                                                                <span class="dm-tag"><?= count($additionals) ?> adicional(is)</span>
                                                            <?php endif; ?>
                                                            <?php if ((int) ($product['allows_notes'] ?? 0) === 1): ?>
                                                                <span class="dm-tag">Aceita observacao</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="dm-product-meta">
                                                            <span class="dm-price"><?= $formatMoney($price) ?></span>
                                                            <button
                                                                class="btn"
                                                                type="button"
                                                                data-product-open
                                                                data-product-id="<?= (int) ($product['id'] ?? 0) ?>"
                                                                <?= $currentCommand !== null ? '' : 'disabled title="Abra sua comanda primeiro"' ?>
                                                            >
                                                                Adicionar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                            </div>
                            <div id="digitalMenuEnd" aria-hidden="true"></div>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="dm-glass-card">
                    <div class="dm-section-head">
                        <div>
                            <h2>Comandas abertas da mesa</h2>
                            <p>Visao compartilhada da mesa: comandas abertas, pedidos, totais e tickets. Nenhuma comanda consegue ver pedidos de outras mesas.</p>
                        </div>
                        <div class="dm-action-bar" style="margin-top:0">
                            <?php if ((int) ($tableSummary['orders_count'] ?? 0) > 0): ?>
                                <a class="btn-soft" href="<?= htmlspecialchars($tableTicketUrl) ?>">Ticket geral da mesa</a>
                            <?php endif; ?>
                            <span class="dm-refresh-status">Atualizacao automatica a cada <?= htmlspecialchars($refreshIntervalLabel) ?></span>
                        </div>
                    </div>

                    <?php if ($tableCommands === []): ?>
                        <div class="dm-empty">Nenhuma comanda aberta nesta mesa no momento.</div>
                    <?php else: ?>
                        <div class="dm-command-board">
                            <?php foreach ($tableCommands as $panel): ?>
                                <?php
                                $command = is_array($panel['command'] ?? null) ? $panel['command'] : [];
                                $summary = is_array($panel['summary'] ?? null) ? $panel['summary'] : [];
                                $orders = is_array($panel['orders'] ?? null) ? $panel['orders'] : [];
                                $commandId = (int) ($command['id'] ?? 0);
                                $commandTicketUrl = base_url('/menu-digital/ticket?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token) . '&scope=command&command_id=' . $commandId);
                                ?>
                                <article class="dm-command-entry<?= !empty($panel['is_current']) ? ' is-current' : '' ?>">
                                    <div class="dm-command-header">
                                        <div class="dm-command-title">
                                            <strong>Comanda #<?= $commandId ?></strong>
                                            <small>
                                                Cliente: <?= htmlspecialchars((string) ($command['customer_name'] ?? 'Sem nome')) ?>
                                                - Aberta em <?= htmlspecialchars($formatDate((string) ($command['opened_at'] ?? ''))) ?>
                                                <?php if (!empty($command['notes'])): ?>
                                                    - <?= htmlspecialchars((string) $command['notes']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="dm-chip-row">
                                            <?php if (!empty($panel['is_current'])): ?>
                                                <span class="dm-chip is-current">Sua comanda atual</span>
                                            <?php endif; ?>
                                            <span class="dm-chip"><?= (int) ($summary['total_orders'] ?? 0) ?> pedido(s)</span>
                                        </div>
                                    </div>

                                    <div class="dm-command-metrics">
                                        <div class="dm-command-metric"><strong><?= (int) ($summary['active_orders'] ?? 0) ?></strong><span>Pedidos ativos</span></div>
                                        <div class="dm-command-metric"><strong><?= (int) ($summary['ready'] ?? 0) ?></strong><span>Prontos</span></div>
                                        <div class="dm-command-metric"><strong><?= (int) ($summary['preparing'] ?? 0) + (int) ($summary['received'] ?? 0) ?></strong><span>Em producao</span></div>
                                        <div class="dm-command-metric"><strong><?= $formatMoney((float) ($summary['total_amount'] ?? 0)) ?></strong><span>Total da comanda</span></div>
                                    </div>

                                    <?php if ($orders === []): ?>
                                        <div class="dm-empty" style="margin-top:12px">Esta comanda ainda nao possui pedidos.</div>
                                    <?php else: ?>
                                        <div class="dm-order-list">
                                            <?php foreach ($orders as $order): ?>
                                                <?php
                                                $status = strtolower(trim((string) ($order['status'] ?? 'pending')));
                                                $statusLabel = $statusLabels[$status] ?? 'Em andamento';
                                                $orderTicketUrl = base_url('/menu-digital/ticket?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token) . '&scope=order&order_id=' . (int) ($order['id'] ?? 0));
                                                $orderItems = is_array($order['items'] ?? null) ? $order['items'] : [];
                                                ?>
                                                <article class="dm-order-card">
                                                    <div class="dm-order-head">
                                                        <div>
                                                            <strong><?= htmlspecialchars((string) ($order['order_number'] ?? 'Pedido')) ?></strong>
                                                            <small><?= htmlspecialchars($statusLabel) ?> - <?= htmlspecialchars($formatDate((string) ($order['created_at'] ?? ''))) ?></small>
                                                        </div>
                                                        <strong><?= $formatMoney((float) ($order['total_amount'] ?? 0)) ?></strong>
                                                    </div>
                                                    <?php if (!empty($order['latest_status_note'])): ?>
                                                        <p class="dm-note" style="margin:8px 0 0"><?= htmlspecialchars((string) $order['latest_status_note']) ?></p>
                                                    <?php endif; ?>
                                                    <div class="dm-order-items">
                                                        <?php foreach ($orderItems as $item): ?>
                                                            <div class="dm-order-item">
                                                                <strong><?= (int) ($item['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($item['name'] ?? 'Item')) ?></strong>
                                                                <span><?= $formatMoney((float) ($item['line_subtotal'] ?? 0)) ?></span>
                                                                <?php if (!empty($item['additionals'])): ?>
                                                                    <small>
                                                                        <?php
                                                                        $parts = [];
                                                                        foreach ((array) $item['additionals'] as $additional) {
                                                                            $parts[] = (int) ($additional['quantity'] ?? 0) . 'x ' . (string) ($additional['name'] ?? 'Adicional');
                                                                        }
                                                                        echo htmlspecialchars(implode(' - ', $parts));
                                                                        ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($item['notes'])): ?>
                                                                    <small>Observacao: <?= htmlspecialchars((string) $item['notes']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="dm-action-bar">
                                                        <a class="btn-soft" href="<?= htmlspecialchars($orderTicketUrl) ?>">Ver ticket do pedido</a>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="dm-action-bar">
                                        <?php if (!empty($panel['has_orders'])): ?>
                                            <a class="btn-secondary" href="<?= htmlspecialchars($commandTicketUrl) ?>">Ticket individual da comanda</a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </main>

            <aside class="dm-side-stack">
                <section class="dm-glass-card">
                    <div class="dm-section-head">
                        <div>
                            <h2>Leitura operacional</h2>
                            <p>Resumo rapido da mesa para consulta do cliente.</p>
                        </div>
                    </div>
                    <div class="dm-quick-grid">
                        <div class="dm-quick-card"><strong><?= (int) ($tableSummary['pending'] ?? 0) ?></strong><span>Aguardando producao</span></div>
                        <div class="dm-quick-card"><strong><?= (int) ($tableSummary['preparing'] ?? 0) + (int) ($tableSummary['received'] ?? 0) ?></strong><span>Em preparo</span></div>
                        <div class="dm-quick-card"><strong><?= (int) ($tableSummary['ready'] ?? 0) ?></strong><span>Prontos</span></div>
                        <div class="dm-quick-card"><strong><?= (int) ($tableSummary['delivered'] ?? 0) ?></strong><span>Entregues</span></div>
                    </div>
                </section>

                <section class="dm-glass-card">
                    <div class="dm-section-head">
                        <div>
                            <h2>Tickets e consulta</h2>
                            <p>Acompanhe os pedidos da mesa e consulte os tickets ja emitidos.</p>
                        </div>
                    </div>
                    <div class="dm-action-bar" style="margin-top:0">
                        <?php if ((int) ($tableSummary['orders_count'] ?? 0) > 0): ?>
                            <a class="btn-secondary" href="<?= htmlspecialchars($tableTicketUrl) ?>">Imprimir ticket geral da mesa</a>
                        <?php endif; ?>
                        <a class="btn-soft" href="#categoryTabs">Ir para o cardapio</a>
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <?php if ($currentCommand !== null): ?>
        <div class="dm-cart-dock" id="digitalCartDock">
            <div class="dm-cart-dock-inner">
                <div class="dm-cart-dock-summary">
                    <strong id="digitalCartDockTitle">Seu carrinho</strong>
                    <span id="digitalCartDockMeta">Nenhum item adicionado ainda.</span>
                </div>
                <div class="dm-chip-row" style="justify-content:flex-end;align-items:center">
                    <span class="dm-cart-dock-total" id="digitalCartDockTotal">R$ 0,00</span>
                    <button class="btn" type="button" id="digitalCartDockButton" disabled>Ver carrinho</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="dm-modal" id="productModal" hidden>
        <div class="dm-modal-sheet">
            <div class="dm-modal-head">
                <div>
                    <h3 id="productModalTitle">Produto</h3>
                    <p id="productModalDescription">Configure quantidade, adicionais e observacoes.</p>
                </div>
                <button class="btn-secondary" type="button" id="closeProductModal">Fechar</button>
            </div>

            <div class="dm-grid-two">
                <div class="field">
                    <label>Quantidade</label>
                    <div class="dm-qty-stepper">
                        <button class="dm-step-btn" type="button" id="decreaseProductQty">-</button>
                        <span class="dm-step-value" id="productQtyValue">1</span>
                        <button class="dm-step-btn" type="button" id="increaseProductQty">+</button>
                    </div>
                </div>
                <div class="field">
                    <label for="productItemNotes">Observacao do item</label>
                    <textarea id="productItemNotes" rows="4" placeholder="Ex.: sem cebola, ponto da carne, enviar junto."></textarea>
                </div>
            </div>

            <div class="field">
                <label>Adicionais</label>
                <div id="productAdditionalsMeta" class="dm-note">Este item nao possui adicionais ativos.</div>
                <div class="dm-additionals-grid" id="productAdditionalsGrid"></div>
            </div>

            <div class="dm-modal-actions">
                <button class="btn-secondary" type="button" id="cancelProductModal">Cancelar</button>
                <button class="btn" type="button" id="confirmProductModal">Adicionar ao carrinho</button>
            </div>
        </div>
    </div>

    <script>
    (() => {
        const currentCommandEnabled = <?= $currentCommand !== null ? 'true' : 'false' ?>;
        const refreshIntervalMs = <?= $refreshIntervalSeconds * 1000 ?>;
        const checkoutUrl = <?= json_encode($cartUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const cartStorageKey = <?= json_encode($cartStorageKey, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const lastOrderId = <?= $lastOrderId ?>;
        const products = <?= $productsJson ?>;
        const productsById = {};
        let cart = [];
        let activeProduct = null;
        let activeQuantity = 1;

        const modal = document.getElementById('productModal');
        const menuShell = document.getElementById('digitalMenuShell');
        const categoryNav = document.getElementById('categoryTabs');
        const categoryNavAnchor = document.getElementById('digitalCategoryNavAnchor');
        const menuEnd = document.getElementById('digitalMenuEnd');
        const modalTitle = document.getElementById('productModalTitle');
        const modalDescription = document.getElementById('productModalDescription');
        const additionalsMeta = document.getElementById('productAdditionalsMeta');
        const additionalsGrid = document.getElementById('productAdditionalsGrid');
        const itemNotes = document.getElementById('productItemNotes');
        const qtyValue = document.getElementById('productQtyValue');
        const cartDock = document.getElementById('digitalCartDock');
        const cartDockMeta = document.getElementById('digitalCartDockMeta');
        const cartDockTotal = document.getElementById('digitalCartDockTotal');
        const cartDockButton = document.getElementById('digitalCartDockButton');

        const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
        const storageAvailable = (() => {
            try {
                const key = '__digital_menu_test__';
                window.localStorage.setItem(key, '1');
                window.localStorage.removeItem(key);
                return true;
            } catch (error) {
                return false;
            }
        })();

        const loadCart = () => {
            if (!storageAvailable) {
                return [];
            }

            try {
                const raw = window.localStorage.getItem(cartStorageKey);
                if (!raw) {
                    return [];
                }

                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        };

        const normalizeCartItems = (items) => {
            if (!Array.isArray(items)) {
                return [];
            }

            return items
                .slice(0, 60)
                .map((item) => {
                    const productId = Number(item?.productId || 0);
                    const product = productsById[String(productId)] || null;
                    return {
                        productId,
                        name: product ? String(product.name || 'Produto') : String(item?.name || 'Produto'),
                        quantity: Math.max(0, Number(item?.quantity || 0)),
                        notes: String(item?.notes || '').slice(0, 500),
                        additionals: Array.isArray(item?.additionals)
                            ? item.additionals.slice(0, 20).map((additional) => ({
                                id: Number(additional?.id || 0),
                                name: String(additional?.name || 'Adicional'),
                                price: Number(additional?.price || 0),
                                quantity: Math.max(0, Number(additional?.quantity ?? 1)),
                            }))
                            : [],
                        lineTotal: Number(item?.lineTotal || 0),
                    };
                })
                .map((item) => ({
                    ...item,
                    additionals: item.additionals.filter((additional) => additional.id > 0 && additional.quantity > 0),
                }))
                .filter((item) => item.productId > 0 && item.quantity > 0 && Number.isFinite(item.lineTotal) && item.lineTotal >= 0 && productsById[String(item.productId)]);
        };

        const saveCart = () => {
            if (!storageAvailable) {
                return;
            }

            try {
                window.localStorage.setItem(cartStorageKey, JSON.stringify(cart));
            } catch (error) {
            }
        };

        const clearCart = () => {
            cart = [];
            if (!storageAvailable) {
                return;
            }

            try {
                window.localStorage.removeItem(cartStorageKey);
            } catch (error) {
            }
        };

        const syncCartDock = () => {
            if (!cartDock || !cartDockMeta || !cartDockTotal || !cartDockButton) {
                return;
            }

            if (cart.length === 0) {
                cartDockMeta.textContent = 'Nenhum item adicionado ainda.';
                cartDockTotal.textContent = money(0);
                cartDockButton.disabled = true;
                return;
            }

            const total = cart.reduce((sum, item) => sum + Number(item.lineTotal || 0), 0);
            const quantity = cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
            cartDockMeta.textContent = `${quantity} item(ns) prontos para revisar na etapa final do pedido.`;
            cartDockTotal.textContent = money(total);
            cartDockButton.disabled = false;
        };

        products.forEach((product) => {
            if (product && typeof product.id !== 'undefined') {
                productsById[String(product.id)] = product;
            }
        });

        if (lastOrderId > 0) {
            clearCart();
        } else {
            cart = normalizeCartItems(loadCart());
            saveCart();
        }
        syncCartDock();

        const categoryTabs = Array.from(document.querySelectorAll('[data-category-tab]'));
        const categorySections = Array.from(document.querySelectorAll('[data-category-section]'));
        let activeCategoryKey = '';
        let categoryClickLockUntil = 0;

        const revealActiveCategoryTab = (tab, behavior = 'auto') => {
            if (!(tab instanceof HTMLElement) || !(categoryNav instanceof HTMLElement)) {
                return;
            }

            const navRect = categoryNav.getBoundingClientRect();
            const tabRect = tab.getBoundingClientRect();
            const leftOverflow = tabRect.left - navRect.left;
            const rightOverflow = tabRect.right - navRect.right;

            if (leftOverflow >= 12 && rightOverflow <= -12) {
                return;
            }

            const targetLeft = tab.offsetLeft - Math.max(12, (categoryNav.clientWidth - tab.offsetWidth) / 2);
            categoryNav.scrollTo({
                left: Math.max(0, targetLeft),
                behavior,
            });
        };

        const activateCategory = (key, options = {}) => {
            const shouldReveal = options.reveal !== false;
            const revealBehavior = options.revealBehavior === 'smooth' ? 'smooth' : 'auto';
            if (key === activeCategoryKey && shouldReveal === false) {
                return;
            }

            activeCategoryKey = key;
            categoryTabs.forEach((item) => {
                const isActive = item.getAttribute('data-category-tab') === key;
                item.classList.toggle('active', isActive);
                if (isActive && shouldReveal) {
                    revealActiveCategoryTab(item, revealBehavior);
                }
            });
        };

        categoryTabs.forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-category-tab');
                const target = key ? document.querySelector(`[data-category-section="${key}"]`) : null;
                categoryClickLockUntil = Date.now() + 700;
                activateCategory(key || '', { revealBehavior: 'smooth' });
                if (target instanceof HTMLElement) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        let menuFrame = 0;
        const syncCategoryFromScroll = () => {
            if (categorySections.length === 0 || Date.now() < categoryClickLockUntil) {
                return;
            }

            const topbarOffsetRaw = getComputedStyle(document.documentElement).getPropertyValue('--dm-topbar-offset');
            const topbarOffset = Number.parseFloat(topbarOffsetRaw) || 76;
            const navHeight = categoryNav instanceof HTMLElement ? (categoryNav.offsetHeight || 0) : 0;
            const guideLine = topbarOffset + navHeight + 26;
            let nextKey = categorySections[0].getAttribute('data-category-section') || '';
            let bestDistance = Number.POSITIVE_INFINITY;

            categorySections.forEach((section) => {
                if (!(section instanceof HTMLElement)) {
                    return;
                }

                const rect = section.getBoundingClientRect();
                const topDistance = Math.abs(rect.top - guideLine);
                const isPassedGuide = rect.top <= guideLine;

                if (isPassedGuide && topDistance <= bestDistance) {
                    bestDistance = topDistance;
                    nextKey = section.getAttribute('data-category-section') || nextKey;
                    return;
                }

                if (bestDistance === Number.POSITIVE_INFINITY && rect.top > guideLine && topDistance < bestDistance) {
                    bestDistance = topDistance;
                    nextKey = section.getAttribute('data-category-section') || nextKey;
                }
            });

            if (nextKey !== '') {
                activateCategory(nextKey, { revealBehavior: 'auto' });
            }
        };

        const syncFloatingCategoryMenu = () => {
            if (!(menuShell instanceof HTMLElement) || !(categoryNav instanceof HTMLElement) || !(categoryNavAnchor instanceof HTMLElement) || !(menuEnd instanceof HTMLElement)) {
                return;
            }

            const topbarOffsetRaw = getComputedStyle(document.documentElement).getPropertyValue('--dm-topbar-offset');
            const topbarOffset = Number.parseFloat(topbarOffsetRaw) || 76;
            const stickyTop = topbarOffset + 4;
            const anchorRect = categoryNavAnchor.getBoundingClientRect();
            const endRect = menuEnd.getBoundingClientRect();
            const navHeight = categoryNav.offsetHeight || 0;
            const anchorTop = anchorRect.top + window.scrollY;
            const endTop = endRect.top + window.scrollY;
            const currentTop = window.scrollY + stickyTop;
            const stopTop = Math.max(0, endTop - anchorTop - navHeight);

            menuShell.style.setProperty('--dm-menu-nav-height', `${navHeight}px`);
            menuShell.style.setProperty('--dm-menu-nav-left', `${Math.round(anchorRect.left)}px`);
            menuShell.style.setProperty('--dm-menu-nav-width', `${Math.round(anchorRect.width)}px`);
            menuShell.style.setProperty('--dm-menu-nav-stop', `${Math.round(stopTop)}px`);

            if (currentTop <= anchorTop) {
                menuShell.classList.remove('is-floating', 'is-bottom');
                return;
            }

            if (currentTop + navHeight >= endTop) {
                menuShell.classList.remove('is-floating');
                menuShell.classList.add('is-bottom');
                return;
            }

            menuShell.classList.remove('is-bottom');
            menuShell.classList.add('is-floating');
        };

        const scheduleFloatingCategoryMenu = () => {
            if (menuFrame !== 0) {
                return;
            }

            menuFrame = window.requestAnimationFrame(() => {
                menuFrame = 0;
                syncFloatingCategoryMenu();
                syncCategoryFromScroll();
            });
        };

        syncFloatingCategoryMenu();
        syncCategoryFromScroll();
        window.addEventListener('scroll', scheduleFloatingCategoryMenu, { passive: true });
        window.addEventListener('resize', scheduleFloatingCategoryMenu);
        window.addEventListener('load', scheduleFloatingCategoryMenu);

        const closeModal = () => {
            activeProduct = null;
            activeQuantity = 1;
            qtyValue.textContent = '1';
            itemNotes.value = '';
            additionalsGrid.innerHTML = '';
            additionalsMeta.textContent = 'Este item nao possui adicionais ativos.';
            modal.hidden = true;
            document.body.style.overflow = '';
        };

        const readModalAdditionalSelections = () => {
            const selections = [];
            additionalsGrid.querySelectorAll('.dm-additional-card[data-additional-id]').forEach((card) => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                const qtyLabel = card.querySelector('[data-additional-qty]');
                const additionalId = Number(card.getAttribute('data-additional-id') || 0);
                const quantity = Math.max(0, Number(qtyLabel ? qtyLabel.textContent || 0 : 0));
                if (additionalId > 0 && checkbox && checkbox.checked && quantity > 0) {
                    selections.push({ id: additionalId, quantity });
                }
            });

            return selections;
        };

        const syncModalAdditionalUi = () => {
            const selections = readModalAdditionalSelections();
            const selectedCount = selections.length;
            const totalUnits = selections.reduce((sum, selection) => sum + Number(selection.quantity || 0), 0);
            const baseText = additionalsMeta.getAttribute('data-base-text') || additionalsMeta.textContent;

            additionalsGrid.querySelectorAll('.dm-additional-card[data-additional-id]').forEach((card) => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                const qtyLabel = card.querySelector('[data-additional-qty]');
                const decreaseButton = card.querySelector('[data-additional-decrease]');
                const increaseButton = card.querySelector('[data-additional-increase]');
                const toggleButton = card.querySelector('[data-additional-toggle]');
                const isSelected = Boolean(checkbox && checkbox.checked);
                const quantity = Math.max(0, Number(qtyLabel ? qtyLabel.textContent || 0 : 0));

                card.classList.toggle('is-selected', isSelected);
                if (toggleButton) {
                    toggleButton.textContent = isSelected ? 'Remover' : 'Adicionar';
                }
                if (decreaseButton) {
                    decreaseButton.disabled = !isSelected || quantity <= 1;
                }
                if (increaseButton) {
                    increaseButton.disabled = !isSelected;
                }
            });

            if (!String(baseText).includes('Este item nao possui adicionais ativos.')) {
                const suffix = selectedCount > 0 ? ` · ${selectedCount} adicional(is) · ${totalUnits} unidade(s)` : '';
                additionalsMeta.textContent = `${baseText}${suffix}`;
            }
        };

        const setModalAdditionalQuantity = (card, quantity, maxSelection) => {
            const checkbox = card.querySelector('input[type="checkbox"]');
            const qtyLabel = card.querySelector('[data-additional-qty]');
            if (!checkbox || !qtyLabel) {
                return false;
            }

            const selections = readModalAdditionalSelections();
            const currentId = Number(card.getAttribute('data-additional-id') || 0);
            const currentSelection = selections.find((selection) => selection.id === currentId) || { quantity: 0 };
            const currentTotal = selections.reduce((sum, selection) => sum + Number(selection.quantity || 0), 0);
            const nextQuantity = Math.max(0, Number(quantity || 0));
            const nextTotal = currentTotal - Number(currentSelection.quantity || 0) + nextQuantity;

            if (maxSelection !== null && nextTotal > maxSelection) {
                alert(`Este item aceita no maximo ${maxSelection} unidade(s) de adicionais por produto.`);
                return false;
            }

            checkbox.checked = nextQuantity > 0;
            qtyLabel.textContent = String(nextQuantity > 0 ? nextQuantity : 0);
            syncModalAdditionalUi();
            return true;
        };

        const renderAdditionals = (product) => {
            const additionals = Array.isArray(product.additionals) ? product.additionals : [];
            const maxSelection = product.additionals_max_selection !== null ? Number(product.additionals_max_selection) : null;
            const minSelection = product.additionals_min_selection !== null ? Number(product.additionals_min_selection) : 0;
            const required = Boolean(product.additionals_is_required);

            if (additionals.length === 0) {
                additionalsGrid.innerHTML = '';
                additionalsMeta.textContent = 'Este item nao possui adicionais ativos.';
                return;
            }

            const rules = [];
            if (required || minSelection > 0) {
                rules.push(`minimo ${Math.max(required ? 1 : 0, minSelection)}`);
            }
            if (maxSelection !== null) {
                rules.push(`maximo ${maxSelection}`);
            }
            const baseMetaText = rules.length > 0
                ? `Selecao de adicionais: ${rules.join(' - ')}`
                : 'Selecao opcional de adicionais.';
            additionalsMeta.setAttribute('data-base-text', baseMetaText);
            additionalsMeta.textContent = baseMetaText;

            additionalsGrid.innerHTML = additionals.map((additional) => `
                <div class="dm-additional-card" data-additional-card data-additional-id="${additional.id}">
                    <input type="checkbox" value="${additional.id}">
                    <strong>${String(additional.name || 'Adicional').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char] || char))}</strong>
                    <span>${money(additional.price)}</span>
                    <div class="dm-additional-controls">
                        <button class="dm-additional-toggle" type="button" data-additional-toggle>Adicionar</button>
                        <div class="dm-additional-qty">
                            <button type="button" data-additional-decrease disabled>-</button>
                            <strong data-additional-qty>0</strong>
                            <button type="button" data-additional-increase disabled>+</button>
                        </div>
                    </div>
                </div>
            `).join('');

            additionalsGrid.querySelectorAll('[data-additional-card]').forEach((card) => {
                const toggleButton = card.querySelector('[data-additional-toggle]');
                const decreaseButton = card.querySelector('[data-additional-decrease]');
                const increaseButton = card.querySelector('[data-additional-increase]');

                toggleButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    const isSelected = Boolean(checkbox && checkbox.checked);
                    setModalAdditionalQuantity(card, isSelected ? 0 : 1, maxSelection);
                });

                decreaseButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const qtyLabel = card.querySelector('[data-additional-qty]');
                    const currentQty = Math.max(0, Number(qtyLabel ? qtyLabel.textContent || 0 : 0));
                    setModalAdditionalQuantity(card, currentQty - 1, maxSelection);
                });

                increaseButton?.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const qtyLabel = card.querySelector('[data-additional-qty]');
                    const currentQty = Math.max(0, Number(qtyLabel ? qtyLabel.textContent || 0 : 0));
                    setModalAdditionalQuantity(card, currentQty + 1, maxSelection);
                });
            });

            syncModalAdditionalUi();
        };

        const openModal = (productId) => {
            if (!currentCommandEnabled) {
                return;
            }

            const product = productsById[String(productId)];
            if (!product) {
                return;
            }

            activeProduct = product;
            activeQuantity = 1;
            qtyValue.textContent = '1';
            itemNotes.value = '';
            modalTitle.textContent = product.name || 'Produto';
            modalDescription.textContent = product.description || 'Configure quantidade, adicionais e observacoes.';
            renderAdditionals(product);
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        };

        document.querySelectorAll('[data-product-open]').forEach((button) => {
            button.addEventListener('click', () => {
                openModal(button.getAttribute('data-product-id'));
            });
        });

        document.getElementById('closeProductModal')?.addEventListener('click', closeModal);
        document.getElementById('cancelProductModal')?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.getElementById('decreaseProductQty')?.addEventListener('click', () => {
            activeQuantity = Math.max(1, activeQuantity - 1);
            qtyValue.textContent = String(activeQuantity);
        });

        document.getElementById('increaseProductQty')?.addEventListener('click', () => {
            activeQuantity += 1;
            qtyValue.textContent = String(activeQuantity);
        });

        document.getElementById('confirmProductModal')?.addEventListener('click', () => {
            if (!activeProduct) {
                return;
            }

            const additionals = [];
            readModalAdditionalSelections().forEach((selection) => {
                const additional = (activeProduct.additionals || []).find((item) => Number(item.id || 0) === Number(selection.id || 0));
                if (!additional) {
                    return;
                }

                additionals.push({
                    id: Number(additional.id),
                    name: String(additional.name || 'Adicional'),
                    price: Number(additional.price || 0),
                    quantity: Math.max(1, Number(selection.quantity || 1)),
                });
            });

            const maxSelection = activeProduct.additionals_max_selection !== null ? Number(activeProduct.additionals_max_selection) : null;
            const minSelection = activeProduct.additionals_min_selection !== null ? Number(activeProduct.additionals_min_selection) : 0;
            const requiredMin = Math.max(Boolean(activeProduct.additionals_is_required) ? 1 : 0, minSelection);
            const totalAdditionalUnits = additionals.reduce((sum, additional) => sum + Number(additional.quantity || 0), 0);

            if (maxSelection !== null && totalAdditionalUnits > maxSelection) {
                alert(`Este item aceita no maximo ${maxSelection} unidade(s) de adicionais.`);
                return;
            }

            if (requiredMin > 0 && totalAdditionalUnits < requiredMin) {
                alert(`Este item exige pelo menos ${requiredMin} unidade(s) de adicionais.`);
                return;
            }

            const unitPrice = activeProduct.promotional_price !== null && activeProduct.promotional_price !== undefined
                ? Number(activeProduct.promotional_price)
                : Number(activeProduct.price || 0);
            const additionalsTotal = additionals.reduce((sum, additional) => {
                return sum + (Number(additional.price || 0) * Number(additional.quantity || 0));
            }, 0);
            const quantity = activeQuantity;

            cart.push({
                productId: Number(activeProduct.id),
                name: String(activeProduct.name || 'Produto'),
                quantity,
                notes: String(itemNotes.value || '').trim(),
                additionals,
                lineTotal: (unitPrice + additionalsTotal) * quantity,
            });

            saveCart();
            syncCartDock();
            closeModal();
        });

        cartDockButton?.addEventListener('click', () => {
            if (cart.length === 0) {
                return;
            }

            window.location.href = checkoutUrl;
        });

        const scheduleRefresh = () => {
            window.setTimeout(() => {
                const activeElement = document.activeElement;
                const modalOpen = modal && !modal.hidden;
                const hasPendingCart = cart.length > 0;
                const formBusy = activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeElement.tagName);
                if (!document.hidden && !modalOpen && !hasPendingCart && !formBusy) {
                    window.location.reload();
                    return;
                }

                scheduleRefresh();
            }, refreshIntervalMs);
        };

        scheduleRefresh();
    })();
    </script>
<?php endif; ?>
