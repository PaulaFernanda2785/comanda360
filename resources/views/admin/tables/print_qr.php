<?php
$context = is_array($context ?? null) ? $context : [];
$tableData = is_array($context['table'] ?? null) ? $context['table'] : [];
$companyName = trim((string) ($context['company_name'] ?? 'Empresa'));
$tableNumber = (int) ($context['table_number'] ?? 0);
$tableName = trim((string) ($context['table_name'] ?? ''));
$tableCapacity = array_key_exists('table_capacity', $context) && $context['table_capacity'] !== null
    ? (int) $context['table_capacity']
    : null;
$qrPayload = trim((string) ($context['qr_payload'] ?? ''));
$token = trim((string) ($tableData['qr_code_token'] ?? ''));
$logoPath = trim((string) ($context['company_logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? company_image_url($logoPath) : '';
$qrImageUrl = $qrPayload !== ''
    ? base_url('/media/table-qr?size=760&data=' . rawurlencode($qrPayload))
    : '';
?>

<style>
    .print-page{display:grid;gap:16px}
    .print-header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .print-actions{display:flex;gap:8px;flex-wrap:wrap}
    .print-sheet{max-width:680px;margin:0 auto;background:#fff;border:2px solid #0f172a;border-radius:18px;padding:24px;box-shadow:0 14px 34px rgba(15,23,42,.14)}
    .print-brand{display:flex;align-items:center;gap:14px;margin-bottom:16px}
    .print-logo{width:74px;height:74px;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-weight:700;color:#334155}
    .print-logo img{width:100%;height:100%;object-fit:cover}
    .print-title h2{margin:0;font-size:28px;line-height:1.05}
    .print-title p{margin:4px 0 0;color:#475569}
    .print-grid{display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:10px;margin-bottom:16px}
    .print-meta{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .print-meta strong{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
    .print-meta span{font-size:17px;color:#0f172a;font-weight:700}
    .qr-wrapper{display:grid;justify-items:center;gap:10px}
    .qr-image-box{width:min(430px,100%);aspect-ratio:1;border:1px solid #cbd5e1;border-radius:14px;background:#fff;padding:14px;display:flex;align-items:center;justify-content:center}
    .qr-image-box img{width:100%;height:100%;object-fit:contain}
    .qr-tech{font-size:13px;color:#0f172a;font-weight:700;text-align:center}
    .qr-token{font-size:12px;color:#334155;word-break:break-word;text-align:center}
    .qr-caption{font-size:13px;color:#475569;text-align:center;max-width:430px}
    .screen-only{display:block}

    @media (max-width:760px){
        .print-sheet{padding:16px}
        .print-grid{grid-template-columns:1fr}
        .print-title h2{font-size:24px}
    }

    @media print {
        body{background:#fff}
        .shell{display:block !important}
        .shell > aside{display:none !important}
        main{padding:0 !important}
        .screen-only{display:none !important}
        .print-sheet{box-shadow:none;border-width:1px;max-width:100%;margin:0}
    }
</style>

<div class="print-page">
    <div class="print-header screen-only">
        <div>
            <h1 style="margin:0">Impressao de QR da Mesa</h1>
            <p style="margin:6px 0 0;color:#64748b">Folha pronta para imprimir e afixar na mesa.</p>
        </div>
        <div class="print-actions">
            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/tables')) ?>">Voltar</a>
            <button class="btn" type="button" onclick="window.print()">Imprimir</button>
            <?php if ($qrImageUrl !== ''): ?>
                <a class="btn secondary" href="<?= htmlspecialchars($qrImageUrl) ?>">Abrir imagem do QR</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="print-sheet">
        <div class="print-brand">
            <div class="print-logo">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo da empresa">
                <?php else: ?>
                    <?= htmlspecialchars(substr($companyName, 0, 2)) ?>
                <?php endif; ?>
            </div>
            <div class="print-title">
                <h2><?= htmlspecialchars($companyName) ?></h2>
                <p><?= htmlspecialchars($tableName !== '' ? $tableName : 'Mesa de atendimento') ?></p>
            </div>
        </div>

        <div class="print-grid">
            <div class="print-meta">
                <strong>Numero da mesa</strong>
                <span><?= $tableNumber > 0 ? $tableNumber : '-' ?></span>
            </div>
            <div class="print-meta">
                <strong>Capacidade</strong>
                <span><?= $tableCapacity !== null ? $tableCapacity . ' pessoa(s)' : 'Não informada' ?></span>
            </div>
        </div>

        <div class="qr-wrapper">
            <div class="qr-image-box">
                <?php if ($qrImageUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($qrImageUrl) ?>" alt="QR Code da mesa">
                <?php else: ?>
                    <span>Não foi possível gerar a imagem do QR.</span>
                <?php endif; ?>
            </div>
            <div class="qr-tech">Comanda360.com.br</div>
            <div class="qr-token">Token QR: <?= htmlspecialchars($token !== '' ? $token : '-') ?></div>
            <div class="qr-caption">Este QR identifica a mesa para o fluxo de atendimento.</div>
        </div>
    </section>
</div>
