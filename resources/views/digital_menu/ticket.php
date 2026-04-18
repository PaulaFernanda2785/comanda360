<?php
$ticket = is_array($ticket ?? null) ? $ticket : [];
$contextOrder = is_array($ticket['order'] ?? null) ? $ticket['order'] : [];
$items = is_array($ticket['items'] ?? null) ? $ticket['items'] : [];
$orders = is_array($ticket['orders'] ?? null) ? $ticket['orders'] : [];
$group = is_array($ticket['group'] ?? null) ? $ticket['group'] : [];
$isGrouped = !empty($ticket['is_grouped']);
$menuTheme = is_array($menuTheme ?? null) ? $menuTheme : [];
$access = is_array($access ?? null) ? $access : [];
$company = is_array($access['company'] ?? null) ? $access['company'] : [];
$table = is_array($access['table'] ?? null) ? $access['table'] : [];
$ticketScopeLabel = trim((string) ($ticketScopeLabel ?? 'Ticket'));
$ticketBackLabel = trim((string) ($ticketBackLabel ?? 'Voltar ao menu'));
$companySlug = trim((string) ($company['slug'] ?? ''));
$tableNumber = (int) ($table['number'] ?? 0);
$token = trim((string) ($access['token'] ?? ''));
$backUrl = base_url('/menu-digital?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token));
$logoPath = trim((string) ($menuTheme['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? company_image_url($logoPath) : '';
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
$statusValue = strtolower(trim((string) ($isGrouped ? ($group['status'] ?? '') : ($contextOrder['status'] ?? ''))));
$statusLabel = $statusLabels[$statusValue] ?? 'Em andamento';
$displayTotal = $isGrouped
    ? (float) ($group['total_amount'] ?? 0)
    : (float) ($contextOrder['total_amount'] ?? 0);
$displayCustomer = $isGrouped
    ? trim((string) ($group['customer_name'] ?? ''))
    : trim((string) ($contextOrder['customer_name'] ?? ''));
$displayCommandId = $isGrouped
    ? (int) ($group['command_id'] ?? 0)
    : (int) ($contextOrder['command_id'] ?? 0);
$displayCreatedAt = $formatDate((string) ($contextOrder['created_at'] ?? ''));
?>

<style>
    .dm-ticket-page{display:grid;gap:18px}
    .dm-ticket-toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .dm-ticket-sheet{padding:0;overflow:hidden}
    .dm-ticket-header{
        padding:22px;
        background:linear-gradient(135deg,var(--dm-main-card),color-mix(in srgb, var(--dm-primary) 62%, var(--dm-main-card) 38%));
        color:#fff;display:grid;gap:16px
    }
    .dm-ticket-brand{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .dm-ticket-brand-main{display:flex;gap:12px;align-items:center}
    .dm-ticket-logo{width:68px;height:68px;border-radius:18px;overflow:hidden;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;font-weight:800}
    .dm-ticket-logo img{width:100%;height:100%;object-fit:cover}
    .dm-ticket-brand strong{display:block;font-size:24px;line-height:1}
    .dm-ticket-brand span{display:block;margin-top:6px;color:rgba(255,255,255,.82)}
    .dm-ticket-grid{padding:22px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .dm-ticket-meta{padding:12px;border-radius:18px;background:var(--dm-surface-soft);border:1px solid var(--dm-border)}
    .dm-ticket-meta strong{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--dm-muted);margin-bottom:6px}
    .dm-ticket-meta span{display:block;font-size:15px;font-weight:700}
    .dm-ticket-body{padding:0 22px 22px;display:grid;gap:14px}
    .dm-ticket-bundle{padding:16px;border-radius:20px;border:1px solid var(--dm-border);background:var(--dm-surface-soft)}
    .dm-ticket-bundle-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:10px}
    .dm-ticket-bundle-head strong{font-size:16px}
    .dm-ticket-bundle-head span{display:block;margin-top:4px;color:var(--dm-muted);font-size:12px}
    .dm-ticket-item{padding:14px;border-radius:18px;border:1px solid var(--dm-border);background:#fff}
    .dm-ticket-item-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
    .dm-ticket-item strong{font-size:15px}
    .dm-ticket-item small{display:block;margin-top:4px;color:var(--dm-muted);font-size:12px}
    .dm-ticket-additionals{display:grid;gap:6px;margin-top:10px}
    .dm-ticket-additionals span{font-size:12px;color:#475569}
    .dm-ticket-total{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:16px 18px;border-radius:20px;background:color-mix(in srgb, var(--dm-accent) 12%, white 88%);border:1px solid color-mix(in srgb, var(--dm-accent) 24%, white 76%)}
    .dm-ticket-total strong{font-size:22px}
    @media (max-width:860px){
        .dm-ticket-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:560px){
        .dm-ticket-grid{grid-template-columns:1fr}
        .dm-ticket-header,.dm-ticket-grid,.dm-ticket-body{padding-left:16px;padding-right:16px}
    }
    @media print{
        body{background:#fff}
        .dm-topbar,.dm-footer,.dm-ticket-toolbar,.dm-flash{display:none !important}
        .dm-shell{width:100%;padding:0}
        .dm-ticket-sheet{box-shadow:none;border-color:#dbe4f0}
        .dm-card{box-shadow:none}
    }
</style>

<div class="dm-ticket-page">
    <div class="dm-ticket-toolbar">
        <a class="btn-secondary" href="<?= htmlspecialchars($backUrl) ?>"><?= htmlspecialchars($ticketBackLabel) ?></a>
        <button class="btn" type="button" onclick="window.print()">Imprimir ticket</button>
    </div>

    <section class="dm-card dm-ticket-sheet">
        <header class="dm-ticket-header">
            <div class="dm-ticket-brand">
                <div class="dm-ticket-brand-main">
                    <div class="dm-ticket-logo">
                        <?php if ($logoUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo da empresa">
                        <?php else: ?>
                            <?= htmlspecialchars(substr((string) ($company['name'] ?? 'ME'), 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars((string) ($company['name'] ?? 'Estabelecimento')) ?></strong>
                        <span><?= htmlspecialchars($ticketScopeLabel) ?> • mesa <?= $tableNumber > 0 ? $tableNumber : '-' ?></span>
                    </div>
                </div>
                <div>
                    <strong><?= $isGrouped ? htmlspecialchars((string) (($group['orders_count'] ?? 0) . ' pedido(s)')) : htmlspecialchars((string) ($contextOrder['order_number'] ?? '-')) ?></strong>
                    <span><?= htmlspecialchars($displayCreatedAt) ?></span>
                </div>
            </div>
        </header>

        <div class="dm-ticket-grid">
            <div class="dm-ticket-meta">
                <strong>Cliente / referência</strong>
                <span><?= htmlspecialchars($displayCustomer !== '' ? $displayCustomer : 'Mesa compartilhada') ?></span>
            </div>
            <div class="dm-ticket-meta">
                <strong>Canal</strong>
                <span>Mesa / QR Code</span>
            </div>
            <div class="dm-ticket-meta">
                <strong>Status</strong>
                <span><?= htmlspecialchars($statusLabel) ?></span>
            </div>
            <div class="dm-ticket-meta">
                <strong>Comanda</strong>
                <span><?= $displayCommandId > 0 ? '#' . $displayCommandId : 'Mesa completa' ?></span>
            </div>
        </div>

        <div class="dm-ticket-body">
            <?php if ($isGrouped): ?>
                <?php foreach ($orders as $bundle): ?>
                    <?php
                    $bundleOrder = is_array($bundle['order'] ?? null) ? $bundle['order'] : [];
                    $bundleItems = is_array($bundle['items'] ?? null) ? $bundle['items'] : [];
                    ?>
                    <section class="dm-ticket-bundle">
                        <div class="dm-ticket-bundle-head">
                            <div>
                                <strong><?= htmlspecialchars((string) ($bundleOrder['order_number'] ?? 'Pedido')) ?></strong>
                                <span>
                                    <?= htmlspecialchars((string) ($bundleOrder['customer_name'] ?? 'Mesa')) ?>
                                    • Comanda #<?= (int) ($bundleOrder['command_id'] ?? 0) ?>
                                    • <?= htmlspecialchars($formatDate((string) ($bundleOrder['created_at'] ?? ''))) ?>
                                </span>
                            </div>
                            <strong><?= $formatMoney((float) ($bundleOrder['total_amount'] ?? 0)) ?></strong>
                        </div>

                        <?php foreach ($bundleItems as $item): ?>
                            <article class="dm-ticket-item">
                                <div class="dm-ticket-item-head">
                                    <div>
                                        <strong><?= (int) ($item['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($item['name'] ?? 'Item')) ?></strong>
                                        <?php if (!empty($item['notes'])): ?>
                                            <small>Observação: <?= htmlspecialchars((string) $item['notes']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <strong><?= $formatMoney((float) ($item['line_subtotal'] ?? 0)) ?></strong>
                                </div>
                                <?php if (!empty($item['additionals'])): ?>
                                    <div class="dm-ticket-additionals">
                                        <?php foreach ((array) $item['additionals'] as $additional): ?>
                                            <span><?= (int) ($additional['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($additional['name'] ?? 'Adicional')) ?> • <?= $formatMoney((float) ($additional['line_subtotal'] ?? 0)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <article class="dm-ticket-item">
                        <div class="dm-ticket-item-head">
                            <div>
                                <strong><?= (int) ($item['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($item['name'] ?? 'Item')) ?></strong>
                                <?php if (!empty($item['notes'])): ?>
                                    <small>Observação: <?= htmlspecialchars((string) $item['notes']) ?></small>
                                <?php endif; ?>
                            </div>
                            <strong><?= $formatMoney((float) ($item['line_subtotal'] ?? 0)) ?></strong>
                        </div>
                        <?php if (!empty($item['additionals'])): ?>
                            <div class="dm-ticket-additionals">
                                <?php foreach ((array) $item['additionals'] as $additional): ?>
                                    <span><?= (int) ($additional['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($additional['name'] ?? 'Adicional')) ?> • <?= $formatMoney((float) ($additional['line_subtotal'] ?? 0)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="dm-ticket-total">
                <span>Total do ticket</span>
                <strong><?= $formatMoney($displayTotal) ?></strong>
            </div>
        </div>
    </section>
</div>
