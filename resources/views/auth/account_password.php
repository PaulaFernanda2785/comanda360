<?php
$account = is_array($account ?? null) ? $account : [];
$accountName = trim((string) ($account['name'] ?? 'Usuário'));
$accountEmail = trim((string) ($account['email'] ?? ''));
$accountRole = trim((string) ($account['role_name'] ?? 'Perfil'));
$isSaasAccount = (int) ($account['is_saas_user'] ?? 0) === 1;
?>

<style>
    .account-password-page{display:grid;gap:16px}
    .account-password-layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:16px;align-items:start}
    .account-password-hero{border:1px solid #bfdbfe;background:linear-gradient(118deg,var(--theme-main-card,#0f172a) 0%,#1e3a8a 58%,#0ea5e9 100%);color:#fff;border-radius:14px;padding:16px;position:relative;overflow:hidden}
    .account-password-hero:before{content:"";position:absolute;top:-60px;right:-48px;width:210px;height:210px;border-radius:999px;background:rgba(255,255,255,.12)}
    .account-password-hero:after{content:"";position:absolute;bottom:-70px;left:-34px;width:180px;height:180px;border-radius:999px;background:rgba(255,255,255,.1)}
    .account-password-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .account-password-hero h1{margin:0 0 8px;font-size:24px}
    .account-password-hero p{margin:0;color:#dbeafe;max-width:760px;line-height:1.5}
    .account-password-pills{display:flex;gap:8px;flex-wrap:wrap}
    .account-password-pill{border:1px solid rgba(255,255,255,.3);background:rgba(15,23,42,.38);border-radius:999px;padding:6px 11px;font-size:12px;font-weight:600;white-space:nowrap}
    .account-password-card{display:grid;gap:12px}
    .account-password-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .account-password-grid .field{margin:0}
    .account-password-grid .field.full{grid-column:1 / -1}
    .account-password-footer{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px}
    .account-password-note{margin:0;color:#64748b;font-size:12px;line-height:1.45;max-width:680px}
    .account-password-summary{display:grid;gap:10px}
    .account-password-summary-item{border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:12px}
    .account-password-summary-item strong{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
    .account-password-summary-item span{display:block;color:#0f172a;font-size:14px;overflow-wrap:anywhere}
    .account-password-governance{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
    .account-password-governance h3{margin:0 0 8px;color:#1e1b4b;font-size:16px}
    .account-password-governance p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
    .account-password-governance ul{margin:10px 0 0;padding-left:18px;color:#312e81;font-size:13px;display:grid;gap:6px}
    @media (max-width:920px){
        .account-password-layout{grid-template-columns:1fr}
    }
    @media (max-width:640px){
        .account-password-grid{grid-template-columns:1fr}
    }
</style>

<div class="account-password-page">
    <div class="account-password-hero">
        <div class="account-password-hero-body">
            <div>
                <h1>Alterar senha</h1>
                <p>Atualize a senha da sua conta com segurança. A alteração vale imediatamente para o seu acesso no <?= $isSaasAccount ? 'ambiente SaaS' : 'ambiente da empresa' ?>.</p>
            </div>
            <div class="account-password-pills">
                <span class="account-password-pill">Conta logada</span>
                <span class="account-password-pill">Validação da senha atual</span>
                <span class="account-password-pill">Acesso imediato</span>
            </div>
        </div>
    </div>

    <div class="account-password-layout">
        <section class="card account-password-card">
            <div>
                <h2 style="margin:0 0 6px">Segurança da conta</h2>
                <p class="account-password-note">Informe a senha atual, defina uma nova senha e confirme antes de salvar. Essa ação não altera permissões nem perfil de acesso.</p>
            </div>

            <form method="POST" action="<?= htmlspecialchars(base_url('/account/password')) ?>">
                <?= form_security_fields('account.password.update') ?>

                <div class="account-password-grid">
                    <div class="field full">
                        <label for="current_password">Senha atual</label>
                        <input id="current_password" name="current_password" type="password" minlength="6" required autocomplete="current-password">
                    </div>
                    <div class="field">
                        <label for="password">Nova senha</label>
                        <input id="password" name="password" type="password" minlength="6" required autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="password_confirm">Confirmar nova senha</label>
                        <input id="password_confirm" name="password_confirm" type="password" minlength="6" required autocomplete="new-password">
                    </div>
                </div>

                <div class="account-password-footer">
                    <button class="btn" type="submit">Salvar nova senha</button>
                </div>
            </form>
        </section>

        <aside class="account-password-summary">
            <div class="account-password-summary-item">
                <strong>Usuário</strong>
                <span><?= htmlspecialchars($accountName) ?></span>
            </div>
            <div class="account-password-summary-item">
                <strong>E-mail</strong>
                <span><?= htmlspecialchars($accountEmail !== '' ? $accountEmail : '-') ?></span>
            </div>
            <div class="account-password-summary-item">
                <strong>Perfil</strong>
                <span><?= htmlspecialchars($accountRole !== '' ? $accountRole : '-') ?></span>
            </div>
            <div class="account-password-summary-item">
                <strong>Contexto</strong>
                <span><?= $isSaasAccount ? 'SaaS' : 'Empresa' ?></span>
            </div>

            <div class="account-password-governance">
                <h3>Boas práticas</h3>
                <p>Evite reutilizar senha de outro sistema. Se a conta for compartilhada operacionalmente, o problema não é só senha: é governança de acesso.</p>
                <ul>
                    <li>Use uma senha diferente da anterior.</li>
                    <li>Prefira combinação longa e difícil de adivinhar.</li>
                    <li>Não compartilhe a senha em grupos ou mensagens internas.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>
