<h1>Entrar no sistema</h1>
<p>Acesse com seu e-mail e senha.</p>

<?php if (!empty($flashSuccess)): ?>
    <div class="success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>

<?php if (!empty($flashError)): ?>
    <div class="error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars(base_url('/login')) ?>">
    <?= form_security_fields('auth.login') ?>

    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" required>

    <label for="password">Senha</label>
    <input id="password" name="password" type="password" required>

    <button type="submit">Entrar</button>
</form>
