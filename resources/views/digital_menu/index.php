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
$currentOrders = is_array($currentCommandPanel['orders'] ?? null) ? $currentCommandPanel['orders'] : [];
$tableCommands = is_array($tableCommands ?? null) ? $tableCommands : [];
$tableSummary = is_array($tableSummary ?? null) ? $tableSummary : [];
$openCommandsCount = (int) ($openCommandsCount ?? 0);
$refreshIntervalSeconds = max(20, (int) ($refreshIntervalSeconds ?? 20));
$fatalError = trim((string) ($fatalError ?? ''));
$companySlug = trim((string) ($company['slug'] ?? ''));
$tableNumber = (int) ($table['number'] ?? 0);
$token = trim((string) ($access['token'] ?? ''));
$menuBaseUrl = $companySlug !== '' && $tableNumber > 0 && $token !== ''
    ? base_url('/menu-digital?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token))
    : base_url('/menu-digital');
$openCommandAction = $menuBaseUrl !== '' ? str_replace('/menu-digital?', '/menu-digital/command/open?', $menuBaseUrl) : base_url('/menu-digital/command/open');
$storeOrderAction = $menuBaseUrl !== '' ? str_replace('/menu-digital?', '/menu-digital/order/store?', $menuBaseUrl) : base_url('/menu-digital/order/store');
$tableTicketUrl = base_url('/menu-digital/ticket?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token) . '&scope=table');
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
    'pending' => 'Aguardando produção',
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
    .dm-dashboard{display:grid;gap:18px}
    .dm-signal-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .dm-signal{padding:16px;border-radius:20px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);backdrop-filter:blur(12px)}
    .dm-signal strong{display:block;font-size:30px;line-height:1}
    .dm-signal span{display:block;margin-top:7px;font-size:12px;color:rgba(255,255,255,.82)}
    .dm-main-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(340px,.92fr);gap:18px;align-items:start}
    .dm-stack{display:grid;gap:18px}
    .dm-glass-card{
        background:linear-gradient(180deg,rgba(255,255,255,.94),rgba(255,255,255,.88));
        border:1px solid rgba(219,228,240,.95);
        border-radius:26px;
        box-shadow:var(--dm-shadow);
        padding:18px;
    }
    .dm-section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
    .dm-section-head h2{margin:0;font-size:24px;letter-spacing:-.02em}
    .dm-section-head p{margin:6px 0 0;color:var(--dm-muted);font-size:14px;max-width:720px;line-height:1.5}
    .dm-chip-row{display:flex;gap:8px;flex-wrap:wrap}
    .dm-chip{
        display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;
        border:1px solid var(--dm-border);background:#fff;color:var(--dm-secondary);font-size:12px;font-weight:700
    }
    .dm-chip.is-current{background:color-mix(in srgb, var(--dm-primary) 10%, white 90%);border-color:color-mix(in srgb, var(--dm-primary) 30%, white 70%)}
    .dm-quick-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .dm-quick-card{padding:14px;border-radius:20px;background:var(--dm-surface-soft);border:1px solid var(--dm-border)}
    .dm-quick-card strong{display:block;font-size:24px;line-height:1}
    .dm-quick-card span{display:block;margin-top:6px;font-size:12px;color:var(--dm-muted)}
    .dm-open-form,.dm-composer-form{display:grid;gap:12px}
    .dm-grid-two{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}
    .dm-note{font-size:13px;color:var(--dm-muted);line-height:1.55}
    .dm-command-board{display:grid;gap:14px}
    .dm-command-entry{padding:16px;border-radius:22px;border:1px solid var(--dm-border);background:#fff}
    .dm-command-entry.is-current{border-color:color-mix(in srgb, var(--dm-primary) 36%, white 64%);box-shadow:0 14px 32px rgba(29,78,216,.08)}
    .dm-command-header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .dm-command-title{display:grid;gap:4px}
    .dm-command-title strong{font-size:19px;line-height:1.1}
    .dm-command-title small{font-size:12px;color:var(--dm-muted);line-height:1.4}
    .dm-command-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:14px}
    .dm-command-metric{padding:12px;border-radius:16px;background:var(--dm-surface-soft);border:1px solid var(--dm-border)}
    .dm-command-metric strong{display:block;font-size:18px;line-height:1}
    .dm-command-metric span{display:block;margin-top:5px;font-size:11px;color:var(--dm-muted)}
    .dm-order-list{display:grid;gap:10px;margin-top:12px}
    .dm-order-card{padding:14px;border-radius:18px;background:var(--dm-surface-soft);border:1px solid var(--dm-border)}
    .dm-order-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .dm-order-head strong{font-size:14px}
    .dm-order-head small{display:block;margin-top:4px;color:var(--dm-muted);font-size:12px}
    .dm-order-items{display:grid;gap:8px;margin-top:10px}
    .dm-order-item{padding:10px 12px;border-radius:14px;background:#fff;border:1px solid var(--dm-border)}
    .dm-order-item strong{display:block;font-size:13px}
    .dm-order-item span,.dm-order-item small{display:block;margin-top:5px;color:var(--dm-muted);font-size:12px;line-height:1.45}
    .dm-action-bar{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .dm-action-bar .btn,.dm-action-bar .btn-secondary,.dm-action-bar .btn-soft{margin-top:4px}
    .dm-category-tabs{display:flex;gap:8px;overflow:auto;padding-bottom:4px;margin-bottom:16px}
    .dm-category-tab{
        border:1px solid var(--dm-border);background:#fff;border-radius:999px;padding:10px 14px;cursor:pointer;white-space:nowrap;
        font:inherit;font-weight:700;color:var(--dm-secondary)
    }
    .dm-category-tab.active{background:var(--dm-primary);border-color:var(--dm-primary);color:#fff}
    .dm-category-panel{display:none}
    .dm-category-panel.active{display:grid;gap:14px}
    .dm-category-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
    .dm-category-head h3{margin:0;font-size:22px}
    .dm-product-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .dm-product-card{
        position:relative;display:grid;grid-template-columns:96px minmax(0,1fr);gap:12px;padding:14px;border:1px solid var(--dm-border);
        border-radius:22px;background:linear-gradient(180deg,#fff,#f9fbfd)
    }
    .dm-product-image{
        width:96px;height:96px;border-radius:18px;overflow:hidden;background:linear-gradient(135deg,#e0ecff,#eef4fb);
        display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--dm-muted)
    }
    .dm-product-image img{width:100%;height:100%;object-fit:cover}
    .dm-product-body{display:grid;gap:8px;min-width:0}
    .dm-product-body strong{font-size:16px;line-height:1.15}
    .dm-product-body p{margin:0;color:var(--dm-muted);font-size:13px;line-height:1.5}
    .dm-tag-row{display:flex;gap:6px;flex-wrap:wrap}
    .dm-tag{display:inline-flex;padding:6px 9px;border-radius:999px;background:var(--dm-surface-soft);border:1px solid var(--dm-border);font-size:11px;font-weight:700;color:var(--dm-muted)}
    .dm-product-meta{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
    .dm-price{font-size:18px;font-weight:800;color:var(--dm-secondary)}
    .dm-side-stack{display:grid;gap:18px;position:sticky;top:88px}
    .dm-cart-list{display:grid;gap:10px}
    .dm-cart-item{padding:12px;border-radius:18px;border:1px solid var(--dm-border);background:var(--dm-surface-soft)}
    .dm-cart-item-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
    .dm-cart-item strong{font-size:14px}
    .dm-cart-item small,.dm-cart-item p{display:block;margin-top:5px;color:var(--dm-muted);font-size:12px;line-height:1.45}
    .dm-cart-footer{display:grid;gap:12px}
    .dm-total-line{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:14px;border-radius:18px;background:color-mix(in srgb, var(--dm-accent) 12%, white 88%);border:1px solid color-mix(in srgb, var(--dm-accent) 22%, white 78%)}
    .dm-total-line strong{font-size:20px}
    .dm-cart-hidden{display:none}
    .dm-refresh-status{font-size:12px;color:var(--dm-muted)}
    .dm-empty{padding:18px;border-radius:20px;border:1px dashed var(--dm-border);background:var(--dm-surface-soft);color:var(--dm-muted)}
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
    .dm-additional-card{position:relative;padding:12px;border-radius:16px;border:1px solid var(--dm-border);background:var(--dm-surface-soft);display:grid;gap:6px;cursor:pointer}
    .dm-additional-card input{position:absolute;opacity:0;pointer-events:none}
    .dm-additional-card.is-selected{border-color:var(--dm-primary);background:color-mix(in srgb, var(--dm-primary) 10%, white 90%)}
    .dm-additional-card strong{font-size:13px}
    .dm-additional-card span{font-size:12px;color:var(--dm-muted)}
    .dm-modal-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
    @media (max-width:1120px){
        .dm-main-grid{grid-template-columns:1fr}
        .dm-side-stack{position:static}
    }
    @media (max-width:900px){
        .dm-signal-grid,.dm-quick-grid,.dm-command-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
        .dm-grid-two,.dm-additionals-grid,.dm-product-grid{grid-template-columns:1fr}
    }
    @media (max-width:560px){
        .dm-signal-grid,.dm-quick-grid,.dm-command-metrics{grid-template-columns:1fr}
        .dm-product-card{grid-template-columns:78px minmax(0,1fr)}
        .dm-product-image{width:78px;height:78px}
        .dm-modal{padding:8px}
        .dm-modal-sheet{padding:14px}
    }
</style>

<?php if ($fatalError !== ''): ?>
    <section class="dm-card dm-hero">
        <div class="dm-hero-grid">
            <div class="dm-hero-copy">
                <span class="dm-eyebrow">Acesso da mesa</span>
                <h1>Menu digital indisponível</h1>
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
                    <p><?= htmlspecialchars(trim((string) ($menuTheme['description'] ?? '')) !== '' ? (string) $menuTheme['description'] : 'Abra sua comanda, acompanhe as comandas abertas da mesa e peça com rapidez direto do celular.') ?></p>
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
                        <span>Pedidos lançados na mesa</span>
                    </div>
                    <div class="dm-signal">
                        <strong><?= $formatMoney((float) ($tableSummary['total_amount'] ?? 0)) ?></strong>
                        <span>Valor acumulado da mesa</span>
                    </div>
                    <div class="dm-signal">
                        <strong><?= $currentCommand !== null ? '#' . (int) ($currentCommand['id'] ?? 0) : '---' ?></strong>
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
                                <p>Você enxerga todas as comandas abertas desta mesa, mas só consegue lançar pedidos na sua própria comanda atual.</p>
                            </div>
                            <div class="dm-chip-row">
                                <span class="dm-chip is-current">Comanda #<?= (int) ($currentCommand['id'] ?? 0) ?></span>
                                <span class="dm-chip"><?= htmlspecialchars((string) ($currentCommand['customer_name'] ?? 'Cliente')) ?></span>
                            </div>
                        </div>

                        <div class="dm-quick-grid">
                            <div class="dm-quick-card"><strong><?= (int) ($currentSummary['total_orders'] ?? 0) ?></strong><span>Pedidos da sua comanda</span></div>
                            <div class="dm-quick-card"><strong><?= (int) ($currentSummary['preparing'] ?? 0) + (int) ($currentSummary['received'] ?? 0) ?></strong><span>Em produção</span></div>
                            <div class="dm-quick-card"><strong><?= (int) ($currentSummary['ready'] ?? 0) ?></strong><span>Prontos</span></div>
                            <div class="dm-quick-card"><strong><?= $formatMoney((float) ($currentSummary['total_amount'] ?? 0)) ?></strong><span>Total da sua comanda</span></div>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="dm-glass-card">
                        <div class="dm-section-head">
                            <div>
                                <h2>Abrir sua comanda</h2>
                                <p>Cada pessoa da mesa pode abrir uma comanda própria pelo mesmo QR Code. A partir daí, os pedidos ficam vinculados apenas à sua comanda neste dispositivo.</p>
                            </div>
                        </div>
                        <form class="dm-open-form" method="POST" action="<?= htmlspecialchars($openCommandAction) ?>">
                            <?= form_security_fields('digital_menu.command.open') ?>
                            <div class="dm-grid-two">
                                <div class="field">
                                    <label for="customer_name">Nome na comanda</label>
                                    <input id="customer_name" name="customer_name" type="text" maxlength="120" placeholder="Ex.: Ana, João, Mesa da empresa">
                                </div>
                                <div class="field">
                                    <label for="command_notes">Observação da comanda</label>
                                    <input id="command_notes" name="notes" type="text" maxlength="255" placeholder="Opcional">
                                </div>
                            </div>
                            <div class="dm-note">A mesa pode ter várias comandas abertas ao mesmo tempo, mas este aparelho ficará vinculado apenas à comanda que você abrir aqui.</div>
                            <div class="dm-action-bar">
                                <button class="btn" type="submit">Abrir minha comanda</button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="dm-glass-card">
                    <div class="dm-section-head">
                        <div>
                            <h2>Comandas abertas da mesa</h2>
                            <p>Visão compartilhada da mesa: comandas abertas, pedidos, totais e tickets. Nenhuma comanda consegue ver pedidos de outras mesas.</p>
                        </div>
                        <div class="dm-action-bar" style="margin-top:0">
                            <?php if ((int) ($tableSummary['orders_count'] ?? 0) > 0): ?>
                                <a class="btn-soft" href="<?= htmlspecialchars($tableTicketUrl) ?>">Ticket geral da mesa</a>
                            <?php endif; ?>
                            <span class="dm-refresh-status">Atualização automática a cada <?= $refreshIntervalSeconds ?>s</span>
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
                                                • Aberta em <?= htmlspecialchars($formatDate((string) ($command['opened_at'] ?? ''))) ?>
                                                <?php if (!empty($command['notes'])): ?>
                                                    • <?= htmlspecialchars((string) $command['notes']) ?>
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
                                        <div class="dm-command-metric"><strong><?= (int) ($summary['preparing'] ?? 0) + (int) ($summary['received'] ?? 0) ?></strong><span>Em produção</span></div>
                                        <div class="dm-command-metric"><strong><?= $formatMoney((float) ($summary['total_amount'] ?? 0)) ?></strong><span>Total da comanda</span></div>
                                    </div>

                                    <?php if ($orders === []): ?>
                                        <div class="dm-empty" style="margin-top:12px">Esta comanda ainda não possui pedidos.</div>
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
                                                            <small><?= htmlspecialchars($statusLabel) ?> • <?= htmlspecialchars($formatDate((string) ($order['created_at'] ?? ''))) ?></small>
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
                                                                        echo htmlspecialchars(implode(' • ', $parts));
                                                                        ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                                <?php if (!empty($item['notes'])): ?>
                                                                    <small>Observação: <?= htmlspecialchars((string) $item['notes']) ?></small>
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

                <section class="dm-glass-card">
                    <div class="dm-section-head">
                        <div>
                            <h2>Cardápio por categoria</h2>
                            <p>As categorias ficam organizadas em menus rápidos, no mesmo racional do painel de produtos do sistema.</p>
                        </div>
                        <span class="dm-chip"><?= count($products) ?> item(ns) ativos</span>
                    </div>

                    <?php if ($categories === []): ?>
                        <div class="dm-empty">Nenhum produto ativo foi encontrado no cardápio desta empresa.</div>
                    <?php else: ?>
                        <div class="dm-category-tabs" id="categoryTabs">
                            <?php foreach ($categories as $index => $category): ?>
                                <button
                                    class="dm-category-tab<?= $index === 0 ? ' active' : '' ?>"
                                    type="button"
                                    data-category-tab="<?= htmlspecialchars((string) ($category['key'] ?? 'category-' . $index)) ?>"
                                >
                                    <?= htmlspecialchars((string) ($category['name'] ?? 'Categoria')) ?>
                                    (<?= (int) ($category['products_count'] ?? count(is_array($category['products'] ?? null) ? $category['products'] : [])) ?>)
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($categories as $index => $category): ?>
                            <?php
                            $categoryKey = (string) ($category['key'] ?? 'category-' . $index);
                            $categoryProducts = is_array($category['products'] ?? null) ? $category['products'] : [];
                            ?>
                            <section class="dm-category-panel<?= $index === 0 ? ' active' : '' ?>" data-category-panel="<?= htmlspecialchars($categoryKey) ?>">
                                <div class="dm-category-head">
                                    <h3><?= htmlspecialchars((string) ($category['name'] ?? 'Categoria')) ?></h3>
                                    <span class="dm-refresh-status"><?= count($categoryProducts) ?> produto(s)</span>
                                </div>
                                <div class="dm-product-grid">
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
                                                <p><?= htmlspecialchars((string) ($product['description'] ?? 'Sem descrição informada.')) ?></p>
                                                <div class="dm-tag-row">
                                                    <?php if ($additionals !== []): ?>
                                                        <span class="dm-tag"><?= count($additionals) ?> adicional(is)</span>
                                                    <?php endif; ?>
                                                    <?php if ((int) ($product['allows_notes'] ?? 0) === 1): ?>
                                                        <span class="dm-tag">Aceita observação</span>
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
                    <?php endif; ?>
                </section>
            </main>

            <aside class="dm-side-stack">
                <section class="dm-glass-card">
                    <div class="dm-section-head">
                        <div>
                            <h2>Pedido rápido</h2>
                            <p>O carrinho lança pedidos somente na sua comanda atual.</p>
                        </div>
                    </div>

                    <?php if ($currentCommand === null): ?>
                        <div class="dm-empty">Abra sua comanda para habilitar o carrinho e registrar seus pedidos.</div>
                    <?php else: ?>
                        <form method="POST" action="<?= htmlspecialchars($storeOrderAction) ?>" id="digitalMenuOrderForm" class="dm-composer-form">
                            <?= form_security_fields('digital_menu.order.store') ?>
                            <div class="dm-cart-list" id="digitalCartList">
                                <div class="dm-empty">Nenhum item no carrinho ainda.</div>
                            </div>

                            <div class="dm-cart-footer">
                                <div class="field">
                                    <label for="digital_order_notes">Observações gerais do pedido</label>
                                    <textarea id="digital_order_notes" name="notes" rows="3" placeholder="Opcional"></textarea>
                                </div>

                                <div class="dm-total-line">
                                    <span>Total previsto</span>
                                    <strong id="digitalCartTotal">R$ 0,00</strong>
                                </div>

                                <div id="digitalCartHiddenFields" class="dm-cart-hidden"></div>

                                <button class="btn" id="digitalCartSubmit" type="submit" disabled>Enviar para minha comanda</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="dm-glass-card">
                    <div class="dm-section-head">
                        <div>
                            <h2>Leitura operacional</h2>
                            <p>Resumo rápido da mesa para consulta do cliente.</p>
                        </div>
                    </div>
                    <div class="dm-quick-grid">
                        <div class="dm-quick-card"><strong><?= (int) ($tableSummary['pending'] ?? 0) ?></strong><span>Aguardando produção</span></div>
                        <div class="dm-quick-card"><strong><?= (int) ($tableSummary['preparing'] ?? 0) + (int) ($tableSummary['received'] ?? 0) ?></strong><span>Em preparo</span></div>
                        <div class="dm-quick-card"><strong><?= (int) ($tableSummary['ready'] ?? 0) ?></strong><span>Prontos</span></div>
                        <div class="dm-quick-card"><strong><?= (int) ($tableSummary['delivered'] ?? 0) ?></strong><span>Entregues</span></div>
                    </div>
                    <div class="dm-action-bar">
                        <?php if ((int) ($tableSummary['orders_count'] ?? 0) > 0): ?>
                            <a class="btn-secondary" href="<?= htmlspecialchars($tableTicketUrl) ?>">Imprimir ticket geral da mesa</a>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <div class="dm-modal" id="productModal" hidden>
        <div class="dm-modal-sheet">
            <div class="dm-modal-head">
                <div>
                    <h3 id="productModalTitle">Produto</h3>
                    <p id="productModalDescription">Configure quantidade, adicionais e observações.</p>
                </div>
                <button class="btn-secondary" type="button" id="closeProductModal">Fechar</button>
            </div>

            <div class="dm-grid-two">
                <div class="field">
                    <label>Quantidade</label>
                    <div class="dm-qty-stepper">
                        <button class="dm-step-btn" type="button" id="decreaseProductQty">−</button>
                        <span class="dm-step-value" id="productQtyValue">1</span>
                        <button class="dm-step-btn" type="button" id="increaseProductQty">+</button>
                    </div>
                </div>
                <div class="field">
                    <label for="productItemNotes">Observação do item</label>
                    <textarea id="productItemNotes" rows="4" placeholder="Ex.: sem cebola, ponto da carne, enviar junto."></textarea>
                </div>
            </div>

            <div class="field">
                <label>Adicionais</label>
                <div id="productAdditionalsMeta" class="dm-note">Este item não possui adicionais ativos.</div>
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
        const products = <?= $productsJson ?>;
        const productsById = {};
        const cart = [];
        let activeProduct = null;
        let activeQuantity = 1;

        const modal = document.getElementById('productModal');
        const modalTitle = document.getElementById('productModalTitle');
        const modalDescription = document.getElementById('productModalDescription');
        const additionalsMeta = document.getElementById('productAdditionalsMeta');
        const additionalsGrid = document.getElementById('productAdditionalsGrid');
        const itemNotes = document.getElementById('productItemNotes');
        const qtyValue = document.getElementById('productQtyValue');
        const cartList = document.getElementById('digitalCartList');
        const cartTotal = document.getElementById('digitalCartTotal');
        const cartHiddenFields = document.getElementById('digitalCartHiddenFields');
        const cartSubmit = document.getElementById('digitalCartSubmit');

        const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char] || char));

        products.forEach((product) => {
            if (product && typeof product.id !== 'undefined') {
                productsById[String(product.id)] = product;
            }
        });

        document.querySelectorAll('[data-category-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-category-tab');
                document.querySelectorAll('[data-category-tab]').forEach((item) => item.classList.toggle('active', item === button));
                document.querySelectorAll('[data-category-panel]').forEach((panel) => {
                    panel.classList.toggle('active', panel.getAttribute('data-category-panel') === key);
                });
            });
        });

        const closeModal = () => {
            activeProduct = null;
            activeQuantity = 1;
            qtyValue.textContent = '1';
            itemNotes.value = '';
            additionalsGrid.innerHTML = '';
            additionalsMeta.textContent = 'Este item não possui adicionais ativos.';
            modal.hidden = true;
            document.body.style.overflow = '';
        };

        const renderCart = () => {
            if (!cartList || !cartTotal || !cartHiddenFields || !cartSubmit) {
                return;
            }

            if (cart.length === 0) {
                cartList.innerHTML = '<div class="dm-empty">Nenhum item no carrinho ainda.</div>';
                cartTotal.textContent = money(0);
                cartHiddenFields.innerHTML = '';
                cartSubmit.disabled = true;
                return;
            }

            let html = '';
            let hiddenFields = '';
            let total = 0;

            cart.forEach((item, index) => {
                total += Number(item.lineTotal || 0);
                const additionalsLabel = item.additionals.length > 0
                    ? item.additionals.map((additional) => additional.name).join(' • ')
                    : '';

                html += `
                    <article class="dm-cart-item">
                        <div class="dm-cart-item-top">
                            <div>
                                <strong>${item.quantity}x ${escapeHtml(item.name)}</strong>
                                <small>${additionalsLabel !== '' ? escapeHtml(additionalsLabel) : 'Sem adicionais'}</small>
                            </div>
                            <button class="btn-secondary" type="button" data-cart-remove="${index}">Remover</button>
                        </div>
                        ${item.notes !== '' ? `<p>Observação: ${escapeHtml(item.notes)}</p>` : ''}
                        <p>Total da linha: ${money(item.lineTotal)}</p>
                    </article>
                `;

                hiddenFields += `<input type="hidden" name="product_id[]" value="${item.productId}">`;
                hiddenFields += `<input type="hidden" name="quantity[]" value="${item.quantity}">`;
                hiddenFields += `<input type="hidden" name="item_notes[]" value="${escapeHtml(item.notes)}">`;
                hiddenFields += `<input type="hidden" name="additional_item_ids[]" value="${item.additionalIds.join(',')}">`;
            });

            cartList.innerHTML = html;
            cartHiddenFields.innerHTML = hiddenFields;
            cartTotal.textContent = money(total);
            cartSubmit.disabled = false;
        };

        const renderAdditionals = (product) => {
            const additionals = Array.isArray(product.additionals) ? product.additionals : [];
            const maxSelection = product.additionals_max_selection !== null ? Number(product.additionals_max_selection) : null;
            const minSelection = product.additionals_min_selection !== null ? Number(product.additionals_min_selection) : 0;
            const required = Boolean(product.additionals_is_required);

            if (additionals.length === 0) {
                additionalsGrid.innerHTML = '';
                additionalsMeta.textContent = 'Este item não possui adicionais ativos.';
                return;
            }

            const rules = [];
            if (required || minSelection > 0) {
                rules.push(`mínimo ${Math.max(required ? 1 : 0, minSelection)}`);
            }
            if (maxSelection !== null) {
                rules.push(`máximo ${maxSelection}`);
            }
            additionalsMeta.textContent = rules.length > 0
                ? `Seleção de adicionais: ${rules.join(' • ')}`
                : 'Seleção opcional de adicionais.';

            additionalsGrid.innerHTML = additionals.map((additional) => `
                <label class="dm-additional-card" data-additional-card>
                    <input type="checkbox" value="${additional.id}">
                    <strong>${escapeHtml(additional.name)}</strong>
                    <span>${money(additional.price)}</span>
                </label>
            `).join('');

            additionalsGrid.querySelectorAll('[data-additional-card]').forEach((card) => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                card.addEventListener('click', () => {
                    window.setTimeout(() => {
                        card.classList.toggle('is-selected', Boolean(checkbox && checkbox.checked));
                    }, 0);
                });
            });
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
            modalDescription.textContent = product.description || 'Configure quantidade, adicionais e observações.';
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
            const additionalIds = [];
            additionalsGrid.querySelectorAll('input[type="checkbox"]:checked').forEach((checkbox) => {
                const additionalId = String(checkbox.value || '');
                const additional = (activeProduct.additionals || []).find((item) => String(item.id) === additionalId);
                if (!additional) {
                    return;
                }
                additionalIds.push(Number(additional.id));
                additionals.push({
                    id: Number(additional.id),
                    name: String(additional.name || 'Adicional'),
                    price: Number(additional.price || 0),
                });
            });

            const maxSelection = activeProduct.additionals_max_selection !== null ? Number(activeProduct.additionals_max_selection) : null;
            const minSelection = activeProduct.additionals_min_selection !== null ? Number(activeProduct.additionals_min_selection) : 0;
            const requiredMin = Math.max(Boolean(activeProduct.additionals_is_required) ? 1 : 0, minSelection);

            if (maxSelection !== null && additionals.length > maxSelection) {
                alert(`Este item aceita no máximo ${maxSelection} adicional(is).`);
                return;
            }

            if (requiredMin > 0 && additionals.length < requiredMin) {
                alert(`Este item exige pelo menos ${requiredMin} adicional(is).`);
                return;
            }

            const unitPrice = activeProduct.promotional_price !== null && activeProduct.promotional_price !== undefined
                ? Number(activeProduct.promotional_price)
                : Number(activeProduct.price || 0);
            const additionalsTotal = additionals.reduce((sum, additional) => sum + Number(additional.price || 0), 0) * activeQuantity;
            const baseTotal = unitPrice * activeQuantity;

            cart.push({
                productId: Number(activeProduct.id),
                name: String(activeProduct.name || 'Produto'),
                quantity: activeQuantity,
                notes: String(itemNotes.value || '').trim(),
                additionals,
                additionalIds,
                lineTotal: baseTotal + additionalsTotal,
            });

            closeModal();
            renderCart();
        });

        cartList?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const index = target.getAttribute('data-cart-remove');
            if (index === null) {
                return;
            }
            const numericIndex = Number(index);
            if (Number.isNaN(numericIndex) || numericIndex < 0 || numericIndex >= cart.length) {
                return;
            }
            cart.splice(numericIndex, 1);
            renderCart();
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

        renderCart();
        scheduleRefresh();
    })();
    </script>
<?php endif; ?>
