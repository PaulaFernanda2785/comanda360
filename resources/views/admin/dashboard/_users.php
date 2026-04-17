<?php
$users = is_array($users ?? null) ? $users : [];
$roles = is_array($roles ?? null) ? $roles : [];
$permissionsGrouped = is_array($permissionsGrouped ?? null) ? $permissionsGrouped : [];
$usersFilters = is_array($usersFilters ?? null) ? $usersFilters : [];
$usersPagination = is_array($usersPagination ?? null) ? $usersPagination : [];

$usersSearch = trim((string) ($usersFilters['search'] ?? ''));
$usersStatus = trim((string) ($usersFilters['status'] ?? ''));
$usersRoleId = (int) ($usersFilters['role_id'] ?? 0);
$usersPerPage = (int) ($usersFilters['per_page'] ?? 10);
$usersPerPageOptions = is_array($usersFilters['per_page_options'] ?? null) ? $usersFilters['per_page_options'] : [10, 20, 50];

$páginationTotal = (int) ($usersPagination['total'] ?? count($users));
$páginationPage = max(1, (int) ($usersPagination['page'] ?? 1));
$páginationLastPage = max(1, (int) ($usersPagination['last_page'] ?? 1));
$páginationFrom = (int) ($usersPagination['from'] ?? 0);
$páginationTo = (int) ($usersPagination['to'] ?? 0);
$páginationPages = is_array($usersPagination['pages'] ?? null) ? $usersPagination['pages'] : [];

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$currentQuery['section'] = 'users';
$returnQuery = http_build_query($currentQuery);

$baseUserFilters = [
    'section' => 'users',
    'users_search' => $usersSearch,
    'users_status' => $usersStatus,
    'users_role_id' => $usersRoleId > 0 ? (string) $usersRoleId : '',
    'users_per_page' => (string) $usersPerPage,
];

$buildUsersUrl = static function (array $overrides = []) use ($baseUserFilters): string {
    $params = array_merge($baseUserFilters, $overrides);
    foreach ($params as $key => $value) {
        if ($key !== 'section' && trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/admin/dashboard' . ($query !== '' ? '?' . $query : ''));
};

$statusOptions = [
    '' => 'Todos os status',
    'ativo' => 'Ativo',
    'inativo' => 'Inativo',
    'bloqueado' => 'Bloqueado',
];

$userStatusChoices = [
    'ativo' => 'Ativo',
    'inativo' => 'Inativo',
    'bloqueado' => 'Bloqueado',
];

$moduleLabelMap = [
    'products' => 'Produtos',
    'product' => 'Produtos',
    'categories' => 'Categorias',
    'category' => 'Categorias',
    'additionals' => 'Adicionais',
    'additional' => 'Adicionais',
    'tables' => 'Mesas',
    'table' => 'Mesas',
    'commands' => 'Comandas',
    'command' => 'Comandas',
    'orders' => 'Pedidos',
    'order' => 'Pedidos',
    'payments' => 'Pagamentos',
    'payment' => 'Pagamentos',
    'cash_registers' => 'Caixas',
    'cash_register' => 'Caixas',
    'cashregisters' => 'Caixas',
    'cashregister' => 'Caixas',
    'reports' => 'Relatórios',
    'report' => 'Relatórios',
    'users' => 'Usuários',
    'user' => 'Usuários',
    'settings' => 'Configurações',
    'setting' => 'Configurações',
    'themes' => 'Temas',
    'theme' => 'Temas',
];

$moduleLabel = static function (string $moduleName) use ($moduleLabelMap): string {
    $normalized = strtolower(trim(str_replace(['-', ' '], '_', $moduleName)));
    if (isset($moduleLabelMap[$normalized])) {
        return $moduleLabelMap[$normalized];
    }

    return ucfirst(str_replace('_', ' ', $moduleName));
};

$roleNameById = [];
$totalCustomRoles = 0;
$totalFactoryRoles = 0;
foreach ($roles as $roleRow) {
    $roleId = (int) ($roleRow['id'] ?? 0);
    $roleNameById[$roleId] = (string) ($roleRow['name'] ?? '-');

    if ((bool) ($roleRow['is_custom'] ?? false)) {
        $totalCustomRoles++;
    } else {
        $totalFactoryRoles++;
    }
}

$formatDateTime = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y H:i', $timestamp);
};
?>

<section class="dash-section<?= $activeSection === 'users' ? ' active' : '' ?>" data-section="users">
    <style>
        .iu-shell{display:grid;gap:14px}
        .iu-hero{border:1px solid #bfdbfe;background:linear-gradient(118deg,#0f172a 0%,#1e3a8a 58%,#0ea5e9 100%);color:#fff;border-radius:14px;padding:16px;position:relative;overflow:hidden}
        .iu-hero:before{content:"";position:absolute;top:-60px;right:-48px;width:210px;height:210px;border-radius:999px;background:rgba(255,255,255,.12)}
        .iu-hero:after{content:"";position:absolute;bottom:-70px;left:-34px;width:180px;height:180px;border-radius:999px;background:rgba(255,255,255,.1)}
        .iu-hero-body{position:relative;z-index:1;display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}
        .iu-hero h2{margin:0 0 8px;font-size:22px}
        .iu-hero p{margin:0;color:#dbeafe;max-width:780px;line-height:1.45}
        .iu-hero-metrics{display:flex;gap:8px;flex-wrap:wrap}
        .iu-hero-pill{border:1px solid rgba(255,255,255,.3);background:rgba(15,23,42,.38);border-radius:999px;padding:6px 11px;font-size:12px;font-weight:600;white-space:nowrap}

        .iu-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:14px;align-items:start}
        .iu-main,.iu-side{display:grid;gap:14px}

        .iu-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
        .iu-card-head h3{margin:0;color:#0f172a}
        .iu-card-note{margin:4px 0 0;color:#475569;font-size:13px;line-height:1.45;max-width:760px}
        .iu-badges{display:flex;gap:6px;flex-wrap:wrap;align-items:center}

        .iu-filter-grid{display:grid;grid-template-columns:1.8fr 1fr 1fr 130px auto;gap:10px;align-items:end}
        .iu-filter-grid .field{margin:0}
        .iu-filter-actions{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}

        .iu-users-list{display:grid;gap:10px}
        .iu-user-item{border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);padding:12px;display:grid;gap:10px}
        .iu-user-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
        .iu-user-id{display:grid;gap:4px}
        .iu-user-id strong{font-size:15px;color:#0f172a}
        .iu-user-id small{font-size:12px;color:#64748b;line-height:1.35}
        .iu-user-meta{display:flex;gap:6px;flex-wrap:wrap}

        .iu-manage{border-top:1px dashed #cbd5e1;padding-top:10px}
        .iu-manage summary{cursor:pointer;font-weight:700;color:#1e293b;list-style:none}
        .iu-manage summary::-webkit-details-marker{display:none}
        .iu-manage summary:after{content:'Expandir';font-size:11px;color:#64748b;margin-left:8px;font-weight:600}
        .iu-manage[open] summary:after{content:'Fechar'}
        .iu-manage-grid{display:grid;grid-template-columns:1.2fr .8fr .8fr;gap:10px;margin-top:10px}
        .iu-manage-card{border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:10px;display:grid;gap:8px}
        .iu-manage-card h4{margin:0;font-size:13px;color:#0f172a}
        .iu-manage-card p{margin:0;color:#64748b;font-size:12px}
        .iu-manage-card .field{margin:0}
        .iu-manage-card .btn{width:100%}

        .iu-role-grid{display:grid;gap:10px}
        .iu-role-item{border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(180deg,#fff,#f8fafc);padding:10px;display:grid;gap:10px}
        .iu-role-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
        .iu-role-title{display:grid;gap:4px}
        .iu-role-title strong{font-size:15px;color:#0f172a}
        .iu-role-title small{font-size:12px;color:#64748b;line-height:1.35}
        .iu-role-meta{display:flex;gap:6px;flex-wrap:wrap}
        .iu-role-lock{font-size:11px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:999px;padding:3px 8px}
        .iu-role-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .iu-role-edit{display:none;border-top:1px dashed #cbd5e1;padding-top:10px}
        .iu-role-edit.is-open{display:grid;gap:10px}
        .iu-role-system-note{font-size:12px;color:#475569;margin:0}

        .iu-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .iu-form-grid .field{margin:0}

        .iu-perm-toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}
        .iu-perm-toolbar p{margin:0;font-size:12px;color:#64748b}
        .iu-perm-toolbar-actions{display:flex;gap:8px;flex-wrap:wrap}

        .iu-perm-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px}
        .iu-perm-module{border:1px solid #bfdbfe;border-radius:12px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);padding:10px;display:grid;gap:8px;box-shadow:0 8px 18px rgba(15,23,42,.04);transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
        .iu-perm-module:hover{border-color:#93c5fd;box-shadow:0 12px 24px rgba(37,99,235,.12);transform:translateY(-1px)}
        .iu-perm-head{display:flex;justify-content:space-between;align-items:center;gap:8px}
        .iu-perm-head strong{font-size:12px;color:#0f172a;text-transform:uppercase;letter-spacing:.05em;font-weight:700}
        .iu-perm-count{font-size:11px;color:#1e3a8a;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:3px 8px}
        .iu-perm-actions{display:flex;gap:6px;flex-wrap:wrap}
        .iu-perm-body{display:grid;gap:7px;max-height:192px;overflow:auto;padding-right:3px}
        .iu-perm-check{display:flex;gap:8px;align-items:flex-start;padding:8px 9px;border:1px solid #dbeafe;border-radius:10px;background:#fff;transition:border-color .18s ease,box-shadow .18s ease,background-color .18s ease,transform .18s ease}
        .iu-perm-check:hover{border-color:#93c5fd;background:#f8fbff;box-shadow:0 8px 16px rgba(59,130,246,.1);transform:translateY(-1px)}
        .iu-perm-check:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.2)}
        .iu-perm-check input[type="checkbox"]{margin-top:1px;inline-size:18px;block-size:18px;flex:0 0 18px;accent-color:#1d4ed8;cursor:pointer}
        .iu-perm-check span{font-size:12px;color:#334155;line-height:1.35;transition:color .18s ease,font-weight .18s ease}
        .iu-perm-check input[type="checkbox"]:checked + span{color:#0f172a;font-weight:600}
        .iu-perm-grid + .btn{margin-top:12px}

        .iu-págination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:4px}
        .iu-págination .dash-págination-controls{display:flex;align-items:center;gap:8px;flex-wrap:wrap}

        .iu-shell .btn.ghost{background:#fff;border:1px solid #cbd5e1;color:#0f172a}
        .iu-shell .btn.ghost:hover{background:#f8fafc}
        .iu-shell .btn.danger{background:#dc2626}
        .iu-shell .btn.danger:hover{background:#b91c1c}
        .iu-shell .btn.text{background:transparent;color:#0f172a;border:1px dashed #cbd5e1}
        .iu-shell .btn.text:hover{background:#f8fafc}
        .iu-shell .btn.small{padding:7px 10px;font-size:12px}
        .iu-ellipsis{padding:0 3px;color:#64748b}

        @media (max-width:1260px){
            .iu-layout{grid-template-columns:1fr}
            .iu-side{order:2}
            .iu-main{order:1}
        }
        @media (max-width:900px){
            .iu-filter-grid{grid-template-columns:1fr 1fr}
            .iu-form-grid,.iu-perm-grid,.iu-manage-grid{grid-template-columns:1fr}
        }
        @media (max-width:640px){
            .iu-hero h2{font-size:20px}
            .iu-filter-grid{grid-template-columns:1fr}
        }
    </style>

    <div class="iu-shell">
        <div class="iu-hero">
            <div class="iu-hero-body">
                <div>
                    <h2>Gestão moderna de usuários internos</h2>
                    <p>Mesmo padrão visual do Painel Estatístico e Personalização, com foco em operação: filtros inteligentes, páginação, controle de perfis e ações de editar, status e senha em um fluxo único.</p>
                </div>
                <div class="iu-hero-metrics">
                    <span class="iu-hero-pill">Usuários: <?= htmlspecialchars((string) $páginationTotal) ?></span>
                    <span class="iu-hero-pill">Perfis de fábrica: <?= htmlspecialchars((string) $totalFactoryRoles) ?></span>
                    <span class="iu-hero-pill">Perfis customizados: <?= htmlspecialchars((string) $totalCustomRoles) ?></span>
                </div>
            </div>
        </div>

        <div class="iu-layout">
            <div class="iu-main">
                <div class="card">
                    <div class="iu-card-head">
                        <div>
                            <h3>Gestão de usuários internos</h3>
                            <p class="iu-card-note">Use os filtros para localizar rapidamente e abra o gerenciamento de cada usuário para editar cadastro, status de acesso e senha.</p>
                        </div>
                    </div>

                    <form method="GET" action="<?= htmlspecialchars(base_url('/admin/dashboard')) ?>">
                        <input type="hidden" name="section" value="users">
                        <div class="iu-filter-grid">
                            <div class="field">
                                <label for="users_search">Busca inteligente</label>
                                <input id="users_search" name="users_search" type="text" value="<?= htmlspecialchars($usersSearch) ?>" placeholder="Nome, e-mail, telefone ou perfil">
                            </div>
                            <div class="field">
                                <label for="users_status">Status</label>
                                <select id="users_status" name="users_status">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= $usersStatus === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="users_role_id">Perfil</label>
                                <select id="users_role_id" name="users_role_id">
                                    <option value="">Todos os perfis</option>
                                    <?php foreach ($roles as $role): ?>
                                        <?php $roleId = (int) ($role['id'] ?? 0); ?>
                                        <option value="<?= $roleId ?>" <?= $usersRoleId === $roleId ? 'selected' : '' ?>><?= htmlspecialchars((string) ($role['name'] ?? '-')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="users_per_page">Por página</label>
                                <select id="users_per_page" name="users_per_page">
                                    <?php foreach ($usersPerPageOptions as $option): ?>
                                        <?php $optionValue = (int) $option; ?>
                                        <option value="<?= $optionValue ?>" <?= $usersPerPage === $optionValue ? 'selected' : '' ?>><?= $optionValue ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="iu-filter-actions">
                                <button class="btn" type="submit">Aplicar</button>
                                <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/dashboard?section=users')) ?>">Limpar</a>
                            </div>
                        </div>
                    </form>

                    <div class="iu-badges" style="margin-top:10px">
                        <span class="badge status-default">Total: <?= htmlspecialchars((string) $páginationTotal) ?></span>
                        <?php if ($usersSearch !== ''): ?><span class="badge status-default">Busca: <?= htmlspecialchars($usersSearch) ?></span><?php endif; ?>
                        <?php if ($usersStatus !== ''): ?><span class="badge status-default">Status: <?= htmlspecialchars(ucfirst($usersStatus)) ?></span><?php endif; ?>
                        <?php if ($usersRoleId > 0): ?><span class="badge status-default">Perfil: <?= htmlspecialchars((string) ($roleNameById[$usersRoleId] ?? '-')) ?></span><?php endif; ?>
                    </div>

                    <?php if ($users === []): ?>
                        <div class="empty-state" style="margin-top:10px">Nenhum usuário encontrado para os filtros aplicados.</div>
                    <?php else: ?>
                        <div class="iu-users-list" style="margin-top:10px">
                            <?php foreach ($users as $userRow): ?>
                                <?php
                                $uId = (int) ($userRow['id'] ?? 0);
                                $uStatus = strtolower(trim((string) ($userRow['status'] ?? 'ativo')));
                                $uStatusBadge = match ($uStatus) {
                                    'ativo' => 'status-active',
                                    'inativo' => 'status-inactive',
                                    'bloqueado' => 'status-blocked',
                                    default => 'status-default',
                                };
                                ?>
                                <article class="iu-user-item">
                                    <div class="iu-user-top">
                                        <div class="iu-user-id">
                                            <strong><?= htmlspecialchars((string) ($userRow['name'] ?? 'Usuário')) ?></strong>
                                            <small><?= htmlspecialchars((string) ($userRow['email'] ?? '-')) ?></small>
                                            <small><?= htmlspecialchars((string) ($userRow['phone'] ?? '-')) ?></small>
                                        </div>
                                        <div class="iu-user-meta">
                                            <span class="badge status-default"><?= htmlspecialchars((string) ($userRow['role_name'] ?? '-')) ?></span>
                                            <span class="badge <?= htmlspecialchars($uStatusBadge) ?>"><?= htmlspecialchars(ucfirst($uStatus)) ?></span>
                                            <span class="badge status-default">Cadastro: <?= htmlspecialchars($formatDateTime($userRow['created_at'] ?? '')) ?></span>
                                            <span class="badge status-default">Último acesso: <?= htmlspecialchars($formatDateTime($userRow['last_login_at'] ?? '')) ?></span>
                                        </div>
                                    </div>

                                    <details class="iu-manage">
                                        <summary>Gerenciar usuário</summary>
                                        <div class="iu-manage-grid">
                                            <form class="iu-manage-card" method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/users/update')) ?>">
                                                <?= form_security_fields('dashboard.users.update.' . $uId) ?>
                                                <input type="hidden" name="user_id" value="<?= $uId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                                <h4>Dados cadastrais</h4>
                                                <p>Atualize dados pessoais e perfil de acesso.</p>
                                                <div class="field">
                                                    <label>Nome</label>
                                                    <input name="name" type="text" required value="<?= htmlspecialchars((string) ($userRow['name'] ?? '')) ?>">
                                                </div>
                                                <div class="field">
                                                    <label>E-mail</label>
                                                    <input name="email" type="email" required value="<?= htmlspecialchars((string) ($userRow['email'] ?? '')) ?>">
                                                </div>
                                                <div class="field">
                                                    <label>Telefone</label>
                                                    <input name="phone" type="text" value="<?= htmlspecialchars((string) ($userRow['phone'] ?? '')) ?>">
                                                </div>
                                                <div class="field">
                                                    <label>Perfil</label>
                                                    <select name="role_id" required>
                                                        <?php foreach ($roles as $role): ?>
                                                            <?php $roleId = (int) ($role['id'] ?? 0); ?>
                                                            <option value="<?= $roleId ?>" <?= $roleId === (int) ($userRow['role_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($role['name'] ?? '-')) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button class="btn secondary" type="submit">Salvar dados</button>
                                            </form>

                                            <form class="iu-manage-card" method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/users/status')) ?>">
                                                <?= form_security_fields('dashboard.users.status.' . $uId) ?>
                                                <input type="hidden" name="user_id" value="<?= $uId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                                <h4>Status de acesso</h4>
                                                <p>Ativar, inativar ou bloquear acesso ao painel.</p>
                                                <div class="field">
                                                    <label>Status atual</label>
                                                    <select name="status" required>
                                                        <?php foreach ($userStatusChoices as $statusValue => $statusLabel): ?>
                                                            <option value="<?= htmlspecialchars($statusValue) ?>" <?= $uStatus === $statusValue ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button class="btn" type="submit">Atualizar status</button>
                                            </form>

                                            <form class="iu-manage-card" method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/users/password')) ?>">
                                                <?= form_security_fields('dashboard.users.password.' . $uId) ?>
                                                <input type="hidden" name="user_id" value="<?= $uId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                                <h4>Segurança de senha</h4>
                                                <p>Defina nova senha e confirme antes de salvar.</p>
                                                <div class="field">
                                                    <label>Nova senha</label>
                                                    <input name="password" type="password" minlength="6" required>
                                                </div>
                                                <div class="field">
                                                    <label>Confirmar senha</label>
                                                    <input name="password_confirm" type="password" minlength="6" required>
                                                </div>
                                                <button class="btn secondary" type="submit">Atualizar senha</button>
                                            </form>
                                        </div>
                                    </details>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="iu-págination">
                        <div class="dash-págination-info">
                            <?php if ($páginationTotal > 0): ?>
                                Exibindo <?= htmlspecialchars((string) $páginationFrom) ?> a <?= htmlspecialchars((string) $páginationTo) ?> de <?= htmlspecialchars((string) $páginationTotal) ?> usuários.
                            <?php else: ?>
                                Nenhum usuário para exibir.
                            <?php endif; ?>
                        </div>
                        <div class="dash-págination-controls">
                            <?php if ($páginationPage > 1): ?>
                                <a class="dash-page-btn" href="<?= htmlspecialchars($buildUsersUrl(['users_page' => $páginationPage - 1])) ?>">Anterior</a>
                            <?php else: ?>
                                <span class="dash-page-btn" style="opacity:.55;cursor:default">Anterior</span>
                            <?php endif; ?>

                            <?php
                            $lastPrinted = 0;
                            foreach ($páginationPages as $pageNumber):
                                $pageValue = (int) $pageNumber;
                                if ($pageValue <= 0) {
                                    continue;
                                }
                                if ($lastPrinted > 0 && ($pageValue - $lastPrinted) > 1):
                                    ?>
                                    <span class="iu-ellipsis">...</span>
                                    <?php
                                endif;
                                $lastPrinted = $pageValue;
                                ?>
                                <a class="dash-page-btn<?= $pageValue === $páginationPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildUsersUrl(['users_page' => $pageValue])) ?>"><?= htmlspecialchars((string) $pageValue) ?></a>
                            <?php endforeach; ?>

                            <?php if ($páginationPage < $páginationLastPage): ?>
                                <a class="dash-page-btn" href="<?= htmlspecialchars($buildUsersUrl(['users_page' => $páginationPage + 1])) ?>">Próxima</a>
                            <?php else: ?>
                                <span class="dash-page-btn" style="opacity:.55;cursor:default">Próxima</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="iu-card-head">
                        <div>
                            <h3>Perfis cadastrados</h3>
                            <p class="iu-card-note">Perfis de fábrica são protegidos como baseline do sistema. Perfis personalizados podem ser editados e excluídos quando não houver usuários vinculados.</p>
                        </div>
                    </div>

                    <?php if ($roles === []): ?>
                        <div class="empty-state">Nenhum perfil disponivel no momento.</div>
                    <?php else: ?>
                        <div class="iu-role-grid">
                            <?php foreach ($roles as $role): ?>
                                <?php
                                $roleId = (int) ($role['id'] ?? 0);
                                $roleName = trim((string) ($role['name'] ?? 'Perfil'));
                                $roleDescription = trim((string) ($role['description'] ?? ''));
                                $roleIsCustom = (bool) ($role['is_custom'] ?? false);
                                $rolePermissionIds = is_array($role['permission_ids'] ?? null) ? $role['permission_ids'] : [];
                                $rolePermissionSet = [];
                                foreach ($rolePermissionIds as $permissionIdValue) {
                                    $rolePermissionSet[(int) $permissionIdValue] = true;
                                }
                                $roleUsersCount = (int) ($role['users_count'] ?? 0);
                                $rolePermissionsCount = (int) ($role['permissions_count'] ?? 0);
                                $editPanelId = 'profile-edit-' . $roleId;
                                $canDeleteRole = $roleIsCustom && $roleUsersCount === 0;
                                ?>
                                <article class="iu-role-item">
                                    <div class="iu-role-head">
                                        <div class="iu-role-title">
                                            <strong><?= htmlspecialchars($roleName) ?></strong>
                                            <small><?= $roleDescription !== '' ? htmlspecialchars($roleDescription) : 'Sem descricao detalhada.' ?></small>
                                        </div>
                                        <div class="iu-role-meta">
                                            <span class="badge status-default"><?= htmlspecialchars((string) $rolePermissionsCount) ?> permissoes</span>
                                            <span class="badge status-default"><?= htmlspecialchars((string) $roleUsersCount) ?> usuarios</span>
                                            <?php if (!$roleIsCustom): ?>
                                                <span class="iu-role-lock">Perfil de fabrica</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($roleIsCustom): ?>
                                        <div class="iu-role-actions">
                                            <button type="button" class="btn ghost small" data-profile-edit-toggle="<?= htmlspecialchars($editPanelId) ?>">Editar perfil</button>

                                            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/roles/delete')) ?>" onsubmit="return confirm('Excluir este perfil personalizado? Esta acao nao pode ser desfeita.');">
                                                <?= form_security_fields('dashboard.roles.delete.' . $roleId) ?>
                                                <input type="hidden" name="role_id" value="<?= $roleId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                <button class="btn danger small" type="submit" <?= $canDeleteRole ? '' : 'disabled title="Realoque os usuarios vinculados antes de excluir este perfil."' ?>>Excluir perfil</button>
                                            </form>
                                        </div>

                                        <div class="iu-role-edit" id="<?= htmlspecialchars($editPanelId) ?>" data-profile-edit-panel>
                                            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/roles/update')) ?>" data-permission-form>
                                                <?= form_security_fields('dashboard.roles.update.' . $roleId) ?>
                                                <input type="hidden" name="role_id" value="<?= $roleId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                                <div class="iu-form-grid">
                                                    <div class="field">
                                                        <label>Nome do perfil</label>
                                                        <input name="name" type="text" maxlength="100" required value="<?= htmlspecialchars($roleName) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label>Descrição</label>
                                                        <input name="description" type="text" maxlength="500" value="<?= htmlspecialchars($roleDescription) ?>">
                                                    </div>
                                                </div>

                                                <div class="iu-perm-toolbar">
                                                    <p>Ajuste as permissoes deste perfil customizado.</p>
                                                    <div class="iu-perm-toolbar-actions">
                                                        <button type="button" class="btn ghost small" data-toggle-all-permissions="on">Marcar tudo</button>
                                                        <button type="button" class="btn text small" data-toggle-all-permissions="off">Limpar selecao</button>
                                                    </div>
                                                </div>

                                                <div class="iu-perm-grid">
                                                    <?php foreach ($permissionsGrouped as $module => $modulePermissions): ?>
                                                        <div class="iu-perm-module" data-permission-module>
                                                            <div class="iu-perm-head">
                                                                <strong><?= htmlspecialchars($moduleLabel((string) $module)) ?></strong>
                                                                <span class="iu-perm-count" data-module-count>0</span>
                                                            </div>
                                                            <div class="iu-perm-actions">
                                                                <button type="button" class="btn ghost small" data-module-toggle="on">Marcar modulo</button>
                                                                <button type="button" class="btn text small" data-module-toggle="off">Limpar modulo</button>
                                                            </div>
                                                            <div class="iu-perm-body">
                                                                <?php foreach ((array) $modulePermissions as $permission): ?>
                                                                    <?php
                                                                    $permissionId = (int) ($permission['id'] ?? 0);
                                                                    $permissionLabel = trim((string) ($permission['description'] ?? ''));
                                                                    if ($permissionLabel === '') {
                                                                        $permissionLabel = (string) ($permission['slug'] ?? ('Permissão #' . $permissionId));
                                                                    }
                                                                    $isChecked = isset($rolePermissionSet[$permissionId]);
                                                                    ?>
                                                                    <label class="iu-perm-check">
                                                                        <input type="checkbox" name="permission_ids[]" value="<?= $permissionId ?>" data-permission-checkbox <?= $isChecked ? 'checked' : '' ?>>
                                                                        <span><?= htmlspecialchars($permissionLabel) ?></span>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <button class="btn secondary" type="submit">Salvar alteracoes do perfil</button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <p class="iu-role-system-note">Perfil padrão de fabrica. Mantido como referencia para governanca do sistema.</p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="iu-side">
                <div class="card">
                    <div class="iu-card-head">
                        <div>
                            <h3>Cadastrar usuario interno</h3>
                            <p class="iu-card-note">Cadastro direto com definicao de perfil, status inicial e senha temporaria.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/users/store')) ?>">
                        <?= form_security_fields('dashboard.users.store') ?>
                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                        <div class="iu-form-grid">
                            <div class="field">
                                <label for="new_user_name">Nome</label>
                                <input id="new_user_name" name="name" type="text" required>
                            </div>
                            <div class="field">
                                <label for="new_user_email">E-mail</label>
                                <input id="new_user_email" name="email" type="email" required>
                            </div>
                            <div class="field">
                                <label for="new_user_phone">Telefone</label>
                                <input id="new_user_phone" name="phone" type="text" placeholder="Opcional">
                            </div>
                            <div class="field">
                                <label for="new_user_role">Perfil</label>
                                <select id="new_user_role" name="role_id" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= (int) ($role['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($role['name'] ?? '-')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="new_user_status">Status inicial</label>
                                <select id="new_user_status" name="status">
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                    <option value="bloqueado">Bloqueado</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="new_user_password">Senha inicial</label>
                                <input id="new_user_password" name="password" type="password" minlength="6" required>
                            </div>
                        </div>

                        <button class="btn" type="submit" style="margin-top:12px">Cadastrar usuario</button>
                    </form>
                </div>

                <div class="card">
                    <div class="iu-card-head">
                        <div>
                            <h3>Construtor de perfil</h3>
                            <p class="iu-card-note">Crie perfis novos por modulo/permissao. Perfis de fabrica continuam como base padrão do sistema.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/roles/store')) ?>" data-permission-form>
                        <?= form_security_fields('dashboard.roles.store') ?>
                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                        <div class="iu-form-grid">
                            <div class="field">
                                <label for="role_create_name">Nome do perfil</label>
                                <input id="role_create_name" name="name" type="text" maxlength="100" placeholder="Ex.: Supervisor de turno" required>
                            </div>
                            <div class="field">
                                <label for="role_create_description">Descrição</label>
                                <input id="role_create_description" name="description" type="text" maxlength="500" placeholder="Escopo e responsabilidades do perfil">
                            </div>
                        </div>

                        <div class="iu-perm-toolbar">
                            <p>Selecione permissoes por modulo.</p>
                            <div class="iu-perm-toolbar-actions">
                                <button type="button" class="btn ghost small" data-toggle-all-permissions="on">Marcar tudo</button>
                                <button type="button" class="btn text small" data-toggle-all-permissions="off">Limpar selecao</button>
                            </div>
                        </div>

                        <div class="iu-perm-grid">
                            <?php foreach ($permissionsGrouped as $module => $modulePermissions): ?>
                                <div class="iu-perm-module" data-permission-module>
                                    <div class="iu-perm-head">
                                        <strong><?= htmlspecialchars($moduleLabel((string) $module)) ?></strong>
                                        <span class="iu-perm-count" data-module-count>0</span>
                                    </div>
                                    <div class="iu-perm-actions">
                                        <button type="button" class="btn ghost small" data-module-toggle="on">Marcar modulo</button>
                                        <button type="button" class="btn text small" data-module-toggle="off">Limpar modulo</button>
                                    </div>
                                    <div class="iu-perm-body">
                                        <?php foreach ((array) $modulePermissions as $permission): ?>
                                            <?php
                                            $permissionId = (int) ($permission['id'] ?? 0);
                                            $permissionLabel = trim((string) ($permission['description'] ?? ''));
                                            if ($permissionLabel === '') {
                                                $permissionLabel = (string) ($permission['slug'] ?? ('Permissão #' . $permissionId));
                                            }
                                            ?>
                                            <label class="iu-perm-check">
                                                <input type="checkbox" name="permission_ids[]" value="<?= $permissionId ?>" data-permission-checkbox>
                                                <span><?= htmlspecialchars($permissionLabel) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button class="btn" type="submit">Criar perfil personalizado</button>
                    </form>
                </div>

                <div class="card" style="border:1px solid #c7d2fe;background:linear-gradient(140deg,#eef2ff 0%,#f8fafc 100%)">
                    <h4 style="margin:0 0 8px;color:#1e1b4b">Governanca e regra de fabrica</h4>
                    <p class="ticket-note" style="margin-bottom:8px;color:#312e81">Perfis padrão continuam fixos como base de configuracao do sistema. Perfis customizados devem ser usados para adaptacoes operacionais por estabelecimento.</p>
                    <div class="iu-badges">
                        <span class="badge status-default">Baseline de fabrica ativo</span>
                        <span class="badge status-default">Edicao segura por perfil</span>
                        <span class="badge status-default">Controle por permissao</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<script>
(() => {
    const usersSection = document.querySelector('[data-section="users"]');
    if (!(usersSection instanceof HTMLElement)) {
        return;
    }

    const resolveSelector = (id) => {
        if (!id) {
            return '';
        }

        if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
            return '#' + CSS.escape(id);
        }

        return '#' + String(id).replace(/([ #;?%&,.+*~\':"!^$\[\]()=>|\/\\@])/g, '\\$1');
    };

    usersSection.querySelectorAll('[data-profile-edit-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const panelId = button.getAttribute('data-profile-edit-toggle');
            const selector = resolveSelector(panelId);
            if (!selector) {
                return;
            }

            const panel = usersSection.querySelector(selector);
            if (!(panel instanceof HTMLElement)) {
                return;
            }

            const isOpen = panel.classList.toggle('is-open');
            button.textContent = isOpen ? 'Fechar edicao' : 'Editar perfil';
        });
    });

    const updateModuleCounter = (moduleEl) => {
        const checkboxes = Array.from(moduleEl.querySelectorAll('input[data-permission-checkbox]'));
        const checked = checkboxes.filter((checkbox) => checkbox.checked).length;
        const total = checkboxes.length;
        const countEl = moduleEl.querySelector('[data-module-count]');
        if (countEl instanceof HTMLElement) {
            countEl.textContent = checked + '/' + total;
        }
    };

    usersSection.querySelectorAll('[data-permission-form]').forEach((formEl) => {
        const modules = Array.from(formEl.querySelectorAll('[data-permission-module]'));

        modules.forEach((moduleEl) => {
            const checkboxes = Array.from(moduleEl.querySelectorAll('input[data-permission-checkbox]'));
            const moduleToggles = Array.from(moduleEl.querySelectorAll('[data-module-toggle]'));

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => updateModuleCounter(moduleEl));
            });

            moduleToggles.forEach((toggleBtn) => {
                toggleBtn.addEventListener('click', () => {
                    const mode = toggleBtn.getAttribute('data-module-toggle');
                    const targetChecked = mode === 'on';
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = targetChecked;
                    });
                    updateModuleCounter(moduleEl);
                });
            });

            updateModuleCounter(moduleEl);
        });

        formEl.querySelectorAll('[data-toggle-all-permissions]').forEach((toggleAllBtn) => {
            toggleAllBtn.addEventListener('click', () => {
                const mode = toggleAllBtn.getAttribute('data-toggle-all-permissions');
                const targetChecked = mode === 'on';

                modules.forEach((moduleEl) => {
                    moduleEl.querySelectorAll('input[data-permission-checkbox]').forEach((checkbox) => {
                        checkbox.checked = targetChecked;
                    });
                    updateModuleCounter(moduleEl);
                });
            });
        });
    });
})();
</script>
