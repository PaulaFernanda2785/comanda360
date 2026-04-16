<?php
$users = is_array($users ?? null) ? $users : [];
$roles = is_array($roles ?? null) ? $roles : [];
$permissionsCatalog = is_array($permissionsCatalog ?? null) ? $permissionsCatalog : [];
$permissionsGrouped = is_array($permissionsGrouped ?? null) ? $permissionsGrouped : [];
$usersFilters = is_array($usersFilters ?? null) ? $usersFilters : [];
$usersPagination = is_array($usersPagination ?? null) ? $usersPagination : [];

$usersSearch = trim((string) ($usersFilters['search'] ?? ''));
$usersStatus = trim((string) ($usersFilters['status'] ?? ''));
$usersRoleId = (int) ($usersFilters['role_id'] ?? 0);
$usersPerPage = (int) ($usersFilters['per_page'] ?? 10);
$usersPerPageOptions = is_array($usersFilters['per_page_options'] ?? null) ? $usersFilters['per_page_options'] : [10, 20, 50];

$paginationTotal = (int) ($usersPagination['total'] ?? count($users));
$paginationPage = (int) ($usersPagination['page'] ?? 1);
$paginationLastPage = (int) ($usersPagination['last_page'] ?? 1);
$paginationFrom = (int) ($usersPagination['from'] ?? 0);
$paginationTo = (int) ($usersPagination['to'] ?? 0);
$paginationPages = is_array($usersPagination['pages'] ?? null) ? $usersPagination['pages'] : [];

$currentQuery = [];
if (is_array($_GET ?? null)) {
    $currentQuery = $_GET;
}
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

$roleNameById = [];
foreach ($roles as $roleRow) {
    $roleNameById[(int) ($roleRow['id'] ?? 0)] = (string) ($roleRow['name'] ?? '-');
}
?>

<section class="dash-section<?= $activeSection === 'users' ? ' active' : '' ?>" data-section="users">
    <div class="users-layout">
        <div class="users-panel">
            <div class="card">
                <h3>Novo perfil interno</h3>
                <p class="users-panel-note">Crie perfis personalizados da empresa e selecione exatamente quais permissoes cada perfil deve ter.</p>

                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/roles/store')) ?>">
                    <?= form_security_fields('dashboard.roles.store') ?>
                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                    <div class="field">
                        <label for="role_create_name">Nome do perfil</label>
                        <input id="role_create_name" name="name" type="text" maxlength="100" placeholder="Ex.: Supervisor de turno" required>
                    </div>

                    <div class="field">
                        <label for="role_create_description">Descricao</label>
                        <textarea id="role_create_description" name="description" rows="2" maxlength="500" placeholder="Resumo das responsabilidades desse perfil"></textarea>
                    </div>

                    <div class="permission-grid">
                        <?php foreach ($permissionsGrouped as $module => $modulePermissions): ?>
                            <div class="permission-group">
                                <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $module))) ?></strong>
                                <?php foreach ((array) $modulePermissions as $permission): ?>
                                    <?php
                                    $permissionId = (int) ($permission['id'] ?? 0);
                                    $permissionLabel = trim((string) ($permission['description'] ?? ''));
                                    if ($permissionLabel === '') {
                                        $permissionLabel = (string) ($permission['slug'] ?? ('Permissao #' . $permissionId));
                                    }
                                    ?>
                                    <label class="permission-check">
                                        <input type="checkbox" name="permission_ids[]" value="<?= $permissionId ?>">
                                        <span><?= htmlspecialchars($permissionLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button class="btn" type="submit">Criar perfil personalizado</button>
                </form>
            </div>

            <div class="card">
                <h3>Perfis cadastrados</h3>
                <p class="users-panel-note">Perfis padrao do sistema sao somente leitura. Use perfis personalizados para regras especificas da sua operacao.</p>

                <?php if ($roles === []): ?>
                    <div class="empty-state">Nenhum perfil disponivel no momento.</div>
                <?php else: ?>
                    <div class="profile-cards">
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
                            ?>
                            <details class="profile-card" <?= $roleIsCustom ? '' : 'open' ?> >
                                <summary>
                                    <div class="profile-title">
                                        <strong><?= htmlspecialchars($roleName) ?></strong>
                                        <small>
                                            <?= $roleDescription !== '' ? htmlspecialchars($roleDescription) : 'Sem descricao detalhada.' ?>
                                        </small>
                                    </div>
                                    <div class="profile-meta">
                                        <span class="badge status-default"><?= htmlspecialchars((string) $rolePermissionsCount) ?> permissoes</span>
                                        <span class="badge status-default"><?= htmlspecialchars((string) $roleUsersCount) ?> usuarios</span>
                                        <?php if (!$roleIsCustom): ?>
                                            <span class="profile-lock">Padrao do sistema</span>
                                        <?php endif; ?>
                                    </div>
                                </summary>

                                <?php if ($roleIsCustom): ?>
                                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/roles/update')) ?>" style="margin-top:10px">
                                        <?= form_security_fields('dashboard.roles.update.' . $roleId) ?>
                                        <input type="hidden" name="role_id" value="<?= $roleId ?>">
                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                        <div class="field">
                                            <label>Nome do perfil</label>
                                            <input name="name" type="text" maxlength="100" required value="<?= htmlspecialchars($roleName) ?>">
                                        </div>

                                        <div class="field">
                                            <label>Descricao</label>
                                            <textarea name="description" rows="2" maxlength="500"><?= htmlspecialchars($roleDescription) ?></textarea>
                                        </div>

                                        <div class="permission-grid">
                                            <?php foreach ($permissionsGrouped as $module => $modulePermissions): ?>
                                                <div class="permission-group">
                                                    <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $module))) ?></strong>
                                                    <?php foreach ((array) $modulePermissions as $permission): ?>
                                                        <?php
                                                        $permissionId = (int) ($permission['id'] ?? 0);
                                                        $permissionLabel = trim((string) ($permission['description'] ?? ''));
                                                        if ($permissionLabel === '') {
                                                            $permissionLabel = (string) ($permission['slug'] ?? ('Permissao #' . $permissionId));
                                                        }
                                                        $isChecked = isset($rolePermissionSet[$permissionId]);
                                                        ?>
                                                        <label class="permission-check">
                                                            <input type="checkbox" name="permission_ids[]" value="<?= $permissionId ?>" <?= $isChecked ? 'checked' : '' ?>>
                                                            <span><?= htmlspecialchars($permissionLabel) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <button class="btn secondary" type="submit">Salvar perfil</button>
                                    </form>
                                <?php endif; ?>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="users-panel">
            <div class="card">
                <h3>Novo usuario interno</h3>
                <p class="users-panel-note">Cadastre usuarios operacionais da empresa e associe o perfil correto de acesso.</p>

                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/users/store')) ?>">
                    <?= form_security_fields('dashboard.users.store') ?>
                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                    <div class="users-inline-fields">
                        <div class="field">
                            <label for="new_user_name">Nome</label>
                            <input id="new_user_name" name="name" type="text" required>
                        </div>
                        <div class="field">
                            <label for="new_user_email">E-mail</label>
                            <input id="new_user_email" name="email" type="email" required>
                        </div>
                    </div>

                    <div class="users-inline-fields">
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
                    </div>

                    <div class="users-inline-fields">
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

                    <button class="btn" type="submit">Cadastrar usuario</button>
                </form>
            </div>

            <div class="card">
                <h3>Gestao de usuarios internos</h3>

                <form method="GET" action="<?= htmlspecialchars(base_url('/admin/dashboard')) ?>" style="margin-bottom:10px">
                    <input type="hidden" name="section" value="users">
                    <div class="users-filter-row">
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
                            <label for="users_per_page">Por pagina</label>
                            <select id="users_per_page" name="users_per_page">
                                <?php foreach ($usersPerPageOptions as $option): ?>
                                    <?php $optionValue = (int) $option; ?>
                                    <option value="<?= $optionValue ?>" <?= $usersPerPage === $optionValue ? 'selected' : '' ?>><?= $optionValue ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field" style="display:flex;gap:8px">
                            <button class="btn" type="submit">Filtrar</button>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/dashboard?section=users')) ?>">Limpar</a>
                        </div>
                    </div>
                </form>

                <div class="users-query-badge">
                    <span class="badge status-default">Total: <?= htmlspecialchars((string) $paginationTotal) ?></span>
                    <?php if ($usersSearch !== ''): ?><span class="badge status-default">Busca: <?= htmlspecialchars($usersSearch) ?></span><?php endif; ?>
                    <?php if ($usersStatus !== ''): ?><span class="badge status-default">Status: <?= htmlspecialchars(ucfirst($usersStatus)) ?></span><?php endif; ?>
                    <?php if ($usersRoleId > 0): ?><span class="badge status-default">Perfil: <?= htmlspecialchars((string) ($roleNameById[$usersRoleId] ?? '')) ?></span><?php endif; ?>
                </div>

                <?php if ($users === []): ?>
                    <div class="empty-state" style="margin-top:10px">Nenhum usuario encontrado para os filtros aplicados.</div>
                <?php else: ?>
                    <div class="users-table-wrap" style="margin-top:10px">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                    <th>Cadastro</th>
                                    <th>Ultimo acesso</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                    $nextStatus = $uStatus === 'ativo' ? 'inativo' : 'ativo';
                                    $statusActionLabel = $uStatus === 'ativo' ? 'Inativar' : 'Ativar';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars((string) ($userRow['name'] ?? 'Usuario')) ?></strong><br>
                                            <span class="muted"><?= htmlspecialchars((string) ($userRow['email'] ?? '-')) ?></span><br>
                                            <span class="muted"><?= htmlspecialchars((string) ($userRow['phone'] ?? '-')) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($userRow['role_name'] ?? '-')) ?></td>
                                        <td><span class="badge <?= htmlspecialchars($uStatusBadge) ?>"><?= htmlspecialchars(ucfirst($uStatus)) ?></span></td>
                                        <td><?= htmlspecialchars((string) ($userRow['created_at'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string) ($userRow['last_login_at'] ?? '-')) ?></td>
                                        <td>
                                            <details>
                                                <summary class="btn secondary" style="display:inline-block;padding:6px 10px">Gerenciar</summary>
                                                <div class="users-actions" style="margin-top:8px">
                                                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/users/update')) ?>">
                                                        <?= form_security_fields('dashboard.users.update.' . $uId) ?>
                                                        <input type="hidden" name="user_id" value="<?= $uId ?>">
                                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                                        <div class="users-inline-fields one">
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
                                                        </div>

                                                        <button class="btn secondary" type="submit">Salvar dados</button>
                                                    </form>

                                                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/users/status')) ?>">
                                                        <?= form_security_fields('dashboard.users.status.' . $uId) ?>
                                                        <input type="hidden" name="user_id" value="<?= $uId ?>">
                                                        <input type="hidden" name="status" value="<?= htmlspecialchars($nextStatus) ?>">
                                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                        <button class="btn" type="submit"><?= htmlspecialchars($statusActionLabel) ?></button>
                                                    </form>

                                                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/users/password')) ?>">
                                                        <?= form_security_fields('dashboard.users.password.' . $uId) ?>
                                                        <input type="hidden" name="user_id" value="<?= $uId ?>">
                                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                        <div class="users-inline-fields one">
                                                            <div class="field">
                                                                <label>Nova senha</label>
                                                                <input name="password" type="password" minlength="6" required>
                                                            </div>
                                                            <div class="field">
                                                                <label>Confirmar senha</label>
                                                                <input name="password_confirm" type="password" minlength="6" required>
                                                            </div>
                                                        </div>
                                                        <button class="btn secondary" type="submit">Atualizar senha</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="dash-pagination">
                    <div class="dash-pagination-info">
                        <?php if ($paginationTotal > 0): ?>
                            Exibindo <?= htmlspecialchars((string) $paginationFrom) ?> a <?= htmlspecialchars((string) $paginationTo) ?> de <?= htmlspecialchars((string) $paginationTotal) ?> usuarios.
                        <?php else: ?>
                            Nenhum usuario para exibir.
                        <?php endif; ?>
                    </div>
                    <div class="dash-pagination-controls">
                        <?php if ($paginationPage > 1): ?>
                            <a class="dash-page-btn" href="<?= htmlspecialchars($buildUsersUrl(['users_page' => $paginationPage - 1])) ?>">Anterior</a>
                        <?php endif; ?>

                        <?php
                        $lastPrinted = 0;
                        foreach ($paginationPages as $pageNumber):
                            $pageValue = (int) $pageNumber;
                            if ($pageValue <= 0) {
                                continue;
                            }
                            if ($lastPrinted > 0 && ($pageValue - $lastPrinted) > 1):
                                ?>
                                <span class="pagination-ellipsis">...</span>
                                <?php
                            endif;
                            $lastPrinted = $pageValue;
                            ?>
                            <a class="dash-page-btn<?= $pageValue === $paginationPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildUsersUrl(['users_page' => $pageValue])) ?>"><?= htmlspecialchars((string) $pageValue) ?></a>
                        <?php endforeach; ?>

                        <?php if ($paginationPage < $paginationLastPage): ?>
                            <a class="dash-page-btn" href="<?= htmlspecialchars($buildUsersUrl(['users_page' => $paginationPage + 1])) ?>">Proxima</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
