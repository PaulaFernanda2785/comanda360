<?php
$panel = is_array($panel ?? null) ? $panel : [];
$filters = is_array($panel['filters'] ?? null) ? $panel['filters'] : [];
$company = is_array($panel['company'] ?? null) ? $panel['company'] : [];
$reportViews = is_array($panel['report_views'] ?? null) ? $panel['report_views'] : ['ready' => false, 'missing' => []];
$analytics = is_array($panel['analytics'] ?? null) ? $panel['analytics'] : [];
$usersModule = is_array($panel['users_module'] ?? null) ? $panel['users_module'] : [];
$users = is_array($usersModule['users'] ?? null) ? $usersModule['users'] : (is_array($panel['users'] ?? null) ? $panel['users'] : []);
$roles = is_array($usersModule['roles'] ?? null) ? $usersModule['roles'] : (is_array($panel['roles'] ?? null) ? $panel['roles'] : []);
$permissionsCatalog = is_array($usersModule['permissions_catalog'] ?? null) ? $usersModule['permissions_catalog'] : (is_array($panel['permissions_catalog'] ?? null) ? $panel['permissions_catalog'] : []);
$permissionsGrouped = is_array($usersModule['permissions_grouped'] ?? null) ? $usersModule['permissions_grouped'] : [];
$usersFilters = is_array($usersModule['filters'] ?? null) ? $usersModule['filters'] : (is_array($panel['users_filters'] ?? null) ? $panel['users_filters'] : []);
$usersPagination = is_array($usersModule['pagination'] ?? null) ? $usersModule['pagination'] : (is_array($panel['users_pagination'] ?? null) ? $panel['users_pagination'] : []);
$supportModule = is_array($panel['support_module'] ?? null) ? $panel['support_module'] : [];
$supportTickets = is_array($supportModule['tickets'] ?? null) ? $supportModule['tickets'] : (is_array($panel['support_tickets'] ?? null) ? $panel['support_tickets'] : []);
$supportThreads = is_array($supportModule['threads'] ?? null) ? $supportModule['threads'] : [];
$supportFilters = is_array($supportModule['filters'] ?? null) ? $supportModule['filters'] : (is_array($panel['support_filters'] ?? null) ? $panel['support_filters'] : []);
$supportPagination = is_array($supportModule['pagination'] ?? null) ? $supportModule['pagination'] : (is_array($panel['support_pagination'] ?? null) ? $panel['support_pagination'] : []);
$supportSummary = is_array($supportModule['summary'] ?? null) ? $supportModule['summary'] : (is_array($panel['support_summary'] ?? null) ? $panel['support_summary'] : []);

$kpis = is_array($analytics['kpis'] ?? null) ? $analytics['kpis'] : [];
$salesByDay = is_array($analytics['sales_by_day'] ?? null) ? $analytics['sales_by_day'] : [];
$ordersByStatus = is_array($analytics['orders_by_status'] ?? null) ? $analytics['orders_by_status'] : [];
$salesByChannel = is_array($analytics['sales_by_channel'] ?? null) ? $analytics['sales_by_channel'] : [];
$paymentSummary = is_array($analytics['payment_summary'] ?? null) ? $analytics['payment_summary'] : [];
$cashKpis = is_array($analytics['cash_kpis'] ?? null) ? $analytics['cash_kpis'] : [];
$cashHistory = is_array($analytics['cash_history'] ?? null) ? $analytics['cash_history'] : [];
$topProducts = is_array($analytics['top_products'] ?? null) ? $analytics['top_products'] : [];

$activeSectionRaw = trim((string) ($activeSection ?? 'overview'));
$allowedSections = ['overview', 'branding', 'users', 'support'];
$activeSection = in_array($activeSectionRaw, $allowedSections, true) ? $activeSectionRaw : 'overview';

$startDate = trim((string) ($filters['start_date'] ?? date('Y-m-d', strtotime('-29 days'))));
$endDate = trim((string) ($filters['end_date'] ?? date('Y-m-d')));
$periodPresetFilter = trim((string) ($filters['period_preset'] ?? 'custom'));
$statusFilter = trim((string) ($filters['status'] ?? ''));
$channelFilter = trim((string) ($filters['channel'] ?? ''));
$paymentStatusFilter = trim((string) ($filters['payment_status'] ?? ''));
$minAmountFilter = trim((string) ($filters['min_amount'] ?? ''));
$maxAmountFilter = trim((string) ($filters['max_amount'] ?? ''));
$searchFilter = trim((string) ($filters['search'] ?? ''));

$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$formatInt = static fn (mixed $value): string => number_format((int) $value, 0, ',', '.');

$statusOptions = [
    '' => 'Todos os status',
    'pending' => 'Pendente',
    'received' => 'Recebido',
    'preparing' => 'Em preparo',
    'ready' => 'Pronto',
    'delivered' => 'Entregue',
    'finished' => 'Finalizado',
    'paid' => 'Pago',
    'canceled' => 'Cancelado',
];

$channelOptions = [
    '' => 'Todos os canais',
    'table' => 'Mesa',
    'delivery' => 'Entrega',
    'pickup' => 'Retirada',
    'counter' => 'Balcao',
];

$paymentStatusOptions = [
    '' => 'Todos os pagamentos',
    'pending' => 'Pendente',
    'partial' => 'Parcial',
    'paid' => 'Pago',
    'canceled' => 'Cancelado',
];

$periodPresetOptions = [
    'custom' => 'Personalizado',
    'today' => 'Hoje',
    'yesterday' => 'Ontem',
    'last7' => 'Ultimos 7 dias',
    'last30' => 'Ultimos 30 dias',
    'month_current' => 'Mes atual',
    'month_previous' => 'Mes anterior',
];

$supportPriorityLabels = [
    'low' => 'Baixa',
    'medium' => 'Media',
    'high' => 'Alta',
    'urgent' => 'Urgente',
];

$supportStatusLabels = [
    'open' => 'Aberto',
    'in_progress' => 'Em andamento',
    'resolved' => 'Resolvido',
    'closed' => 'Fechado',
];
?>

<style>
    .dash-page{display:grid;gap:16px}
    .dash-nav{display:flex;gap:8px;flex-wrap:wrap}
    .dash-tab-btn{border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:8px 12px;cursor:pointer;color:#0f172a}
    .dash-tab-btn.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
    .dash-section{display:none;gap:16px}
    .dash-section.active{display:grid}
    .dash-filter-grid{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:10px;align-items:end}
    .dash-kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:12px}
    .dash-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
    .dash-kpi strong{display:block;font-size:22px;line-height:1.1;color:#0f172a}
    .dash-kpi span{display:block;margin-top:6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .dash-grid-2{display:grid;grid-template-columns:1.5fr 1fr;gap:14px}
    .dash-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
    .dash-bars{display:grid;gap:8px}
    .dash-bar-row{display:grid;grid-template-columns:78px 1fr 110px;gap:8px;align-items:center}
    .dash-bar-track{height:12px;background:#e2e8f0;border-radius:999px;overflow:hidden}
    .dash-bar-fill{height:100%;display:block;background:linear-gradient(90deg,#1d4ed8,#2563eb);border-radius:999px}
    .dash-label{font-size:12px;color:#475569}
    .dash-value{font-size:12px;color:#0f172a;text-align:right;font-weight:600}
    .dash-donut-wrap{display:grid;place-items:center;gap:10px}
    .dash-donut{width:180px;height:180px;border-radius:999px;display:grid;place-items:center}
    .dash-donut-hole{width:108px;height:108px;border-radius:999px;background:#fff;display:grid;place-items:center;text-align:center;padding:6px}
    .dash-donut-hole strong{font-size:22px;line-height:1}
    .dash-donut-hole span{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
    .dash-legend{display:grid;gap:7px}
    .dash-legend-item{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:13px}
    .dash-dot{width:10px;height:10px;border-radius:999px;display:inline-block;margin-right:6px}
    .dash-table{width:100%;border-collapse:collapse}
    .dash-table th,.dash-table td{padding:10px;border-bottom:1px solid #e2e8f0;font-size:13px;text-align:left;vertical-align:top}
    .dash-table th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .dash-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:10px}
    .dash-pagination-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .dash-page-btn{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:7px 10px;cursor:pointer;min-width:36px}
    .dash-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .dash-pagination-info{font-size:12px;color:#475569}
    .dash-overview-hero{background:linear-gradient(115deg,#0f172a 0%,#1e293b 55%,#334155 100%);color:#fff;overflow:hidden;position:relative}
    .dash-overview-hero::before{content:"";position:absolute;top:-38px;right:-60px;width:220px;height:220px;border-radius:999px;background:rgba(56,189,248,.18)}
    .dash-overview-hero::after{content:"";position:absolute;bottom:-50px;left:-42px;width:200px;height:200px;border-radius:999px;background:rgba(34,197,94,.15)}
    .dash-overview-hero-body{position:relative;z-index:1}
    .dash-overview-hero h2{margin:0 0 8px;color:#fff}
    .dash-overview-hero p{margin:0;color:#cbd5e1;max-width:840px}
    .dash-overview-filters{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:10px}
    .dash-overview-filters .field{margin:0}
    .dash-overview-actions{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:10px;align-items:end}
    .dash-overview-actions .field{margin:0}
    .brand-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:14px}
    .brand-preview{display:grid;gap:10px}
    .brand-media{border:1px solid #dbeafe;border-radius:12px;background:#f8fafc;padding:10px}
    .brand-media img{display:block;width:100%;max-height:210px;object-fit:cover;border-radius:10px;border:1px solid #cbd5e1}
    .brand-media small{display:block;margin-top:6px;color:#64748b}
    .users-shell{display:grid;gap:14px}
    .users-intro{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .users-grid-main{display:grid;grid-template-columns:minmax(340px,1fr) minmax(580px,1.45fr);gap:14px}
    .users-stack{display:grid;gap:14px}
    .users-card{display:grid;gap:12px}
    .users-card-header{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .users-card-header h3{margin:0}
    .users-card-note{margin:0;color:#475569;font-size:13px;line-height:1.45;max-width:720px}

    .users-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .users-form-grid .field{margin:0}
    .users-form-grid.full{grid-template-columns:1fr}

    .permission-toolbar{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
    .permission-toolbar p{margin:0;font-size:12px;color:#64748b}
    .permission-builder-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px}
    .permission-module{border:1px solid #dbeafe;border-radius:12px;background:#f8fafc;padding:10px;display:grid;gap:8px}
    .permission-module-head{display:flex;justify-content:space-between;align-items:center;gap:8px}
    .permission-module-head strong{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#0f172a}
    .permission-module-count{font-size:11px;color:#475569;background:#e2e8f0;border-radius:999px;padding:3px 8px}
    .permission-module-body{display:grid;gap:6px;max-height:168px;overflow:auto;padding-right:4px}
    .permission-check{display:flex;align-items:flex-start;gap:7px;font-size:12px;color:#334155}
    .permission-check input{margin-top:2px}
    .permission-check span{line-height:1.32}

    .profiles-list{display:grid;gap:10px}
    .profile-row{border:1px solid #dbeafe;border-radius:12px;background:#f8fafc;padding:10px;display:grid;gap:10px}
    .profile-row-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap}
    .profile-title{display:grid;gap:3px}
    .profile-title strong{font-size:15px}
    .profile-title small{font-size:12px;color:#64748b}
    .profile-meta{display:flex;gap:6px;flex-wrap:wrap}
    .profile-lock{font-size:11px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:999px;padding:3px 8px}
    .profile-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .profile-edit-panel{display:none;border-top:1px dashed #cbd5e1;padding-top:10px}
    .profile-edit-panel.is-open{display:grid;gap:10px}
    .profile-system-note{font-size:12px;color:#475569;margin:0}

    .users-filter-grid{display:grid;grid-template-columns:1.8fr 1fr 1fr 140px auto;gap:10px;align-items:end}
    .users-filter-grid .field{margin:0}
    .users-query-badges{display:flex;align-items:center;gap:6px;flex-wrap:wrap}

    .users-list{display:grid;gap:10px}
    .user-item{border:1px solid #dbeafe;border-radius:12px;background:#fff;padding:10px;display:grid;gap:10px}
    .user-item-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .user-identity strong{font-size:14px}
    .user-identity small{display:block;color:#64748b}
    .user-meta{display:flex;gap:6px;flex-wrap:wrap}
    .user-manage{border-top:1px dashed #cbd5e1;padding-top:10px}
    .user-manage summary{cursor:pointer;font-weight:600;color:#0f172a}
    .user-manage-grid{display:grid;grid-template-columns:1.2fr .8fr .8fr;gap:10px;margin-top:10px}
    .user-manage-card{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:10px;display:grid;gap:8px}
    .user-manage-card h4{margin:0;font-size:13px}
    .user-manage-card .field{margin:0}
    .user-manage-card .btn{width:100%}

    .btn.outline{background:#fff;border:1px solid #cbd5e1;color:#0f172a}
    .btn.outline:hover{background:#f8fafc}
    .btn.danger{background:#dc2626}
    .btn.danger:hover{background:#b91c1c}
    .btn.text{background:transparent;color:#0f172a;border:1px dashed #cbd5e1}
    .btn.text:hover{background:#f8fafc}
    .btn.small{padding:7px 10px;font-size:12px}
    .pagination-ellipsis{padding:0 2px;color:#64748b}
    .support-grid{display:grid;grid-template-columns:1fr 1.4fr;gap:14px}
    .ticket-note{margin:0;color:#475569;font-size:13px;line-height:1.4}
    .muted{color:#64748b}
    .empty-state{padding:12px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;color:#475569}
    @media (max-width:1220px){
        .dash-kpi-grid{grid-template-columns:repeat(3,minmax(120px,1fr))}
        .dash-filter-grid{grid-template-columns:repeat(3,minmax(140px,1fr))}
        .dash-grid-3{grid-template-columns:1fr 1fr}
        .brand-grid,.users-grid-main,.support-grid{grid-template-columns:1fr}
        .users-filter-grid{grid-template-columns:1fr 1fr 1fr}
    }
    @media (max-width:820px){
        .dash-grid-2,.dash-grid-3{grid-template-columns:1fr}
        .dash-filter-grid{grid-template-columns:1fr 1fr}
        .dash-kpi-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}
        .dash-bar-row{grid-template-columns:60px 1fr 90px}
        .dash-overview-filters{grid-template-columns:1fr 1fr}
        .dash-overview-actions{grid-template-columns:1fr}
        .dash-section[data-section="overview"] .dash-filter-grid[style*="repeat(4"]{grid-template-columns:1fr 1fr !important}
        .dash-section[data-section="overview"] div[style*="grid-template-columns:1fr auto auto"]{grid-template-columns:1fr !important}
        .permission-builder-grid,.users-form-grid,.user-manage-grid{grid-template-columns:1fr}
        .users-filter-grid{grid-template-columns:1fr 1fr}
    }
    @media (max-width:620px){
        .dash-filter-grid,.dash-kpi-grid{grid-template-columns:1fr}
        .dash-bar-row{grid-template-columns:52px 1fr 80px}
        .dash-overview-filters{grid-template-columns:1fr}
        .dash-section[data-section="overview"] .dash-filter-grid[style*="repeat(4"]{grid-template-columns:1fr !important}
        .users-filter-grid{grid-template-columns:1fr}
    }
</style>

<div class="dash-page">
    <div class="topbar">
        <div>
            <h1 style="margin:0">Dashboard estrategico do estabelecimento</h1>
            <p class="muted" style="margin:6px 0 0">Painel estatistico, personalizacao da marca, usuarios internos e chamados tecnicos no mesmo modulo administrativo.</p>
        </div>
        <span class="badge status-default">Acesso exclusivo: Administrador e Gerente</span>
    </div>

    <div class="dash-nav" id="dashboardSectionNav">
        <button type="button" class="dash-tab-btn<?= $activeSection === 'overview' ? ' active' : '' ?>" data-section-target="overview">Painel estatistico</button>
        <button type="button" class="dash-tab-btn<?= $activeSection === 'branding' ? ' active' : '' ?>" data-section-target="branding">Personalizacao</button>
        <button type="button" class="dash-tab-btn<?= $activeSection === 'users' ? ' active' : '' ?>" data-section-target="users">Usuarios internos</button>
        <button type="button" class="dash-tab-btn<?= $activeSection === 'support' ? ' active' : '' ?>" data-section-target="support">Fale com equipe tecnica</button>
    </div>

    <?php require __DIR__ . '/_overview.php'; ?>
    <?php require __DIR__ . '/_branding.php'; ?>
    <?php require __DIR__ . '/_users.php'; ?>
    <?php require __DIR__ . '/_support.php'; ?>
</div>

<script>
(() => {
    const sectionButtons = Array.from(document.querySelectorAll('[data-section-target]'));
    const sections = Array.from(document.querySelectorAll('[data-section]'));
    if (sectionButtons.length === 0 || sections.length === 0) {
        return;
    }

    const periodPreset = document.getElementById('dash_period_preset');
    const startDateInput = document.getElementById('dash_start_date');
    const endDateInput = document.getElementById('dash_end_date');

    const setSection = (section) => {
        sectionButtons.forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-section-target') === section);
        });
        sections.forEach((panel) => {
            panel.classList.toggle('active', panel.getAttribute('data-section') === section);
        });
        const url = new URL(window.location.href);
        url.searchParams.set('section', section);
        window.history.replaceState({}, '', `${url.pathname}?${url.searchParams.toString()}`);
    };

    sectionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.getAttribute('data-section-target') || 'overview';
            setSection(target);
        });
    });

    const refreshDateFieldState = () => {
        if (!(periodPreset instanceof HTMLSelectElement) || !(startDateInput instanceof HTMLInputElement) || !(endDateInput instanceof HTMLInputElement)) {
            return;
        }
        const isCustom = periodPreset.value === 'custom';
        startDateInput.readOnly = !isCustom;
        endDateInput.readOnly = !isCustom;
        startDateInput.style.opacity = isCustom ? '1' : '.75';
        endDateInput.style.opacity = isCustom ? '1' : '.75';
    };

    if (periodPreset instanceof HTMLSelectElement) {
        periodPreset.addEventListener('change', refreshDateFieldState);
        refreshDateFieldState();
    }

    const autoRefreshMs = 30000;
    window.setInterval(() => {
        if (document.hidden) {
            return;
        }

        const activePanel = document.querySelector('[data-section].active');
        if (!(activePanel instanceof HTMLElement) || activePanel.getAttribute('data-section') !== 'overview') {
            return;
        }

        const active = document.activeElement;
        if (active instanceof HTMLElement) {
            const tagName = active.tagName;
            if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
                return;
            }
        }

        window.location.reload();
    }, autoRefreshMs);
})();
</script>
