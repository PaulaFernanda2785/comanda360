<h1>Entrar no sistema</h1>
<p>Acesse com seu e-mail e senha.</p>

<?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars(base_url('/login')) ?>">
    <label for="email">E-mail</label>
    <input id="email" name="email" type="email" required>

    <label for="password">Senha</label>
    <input id="password" name="password" type="password" required>

    <button type="submit">Entrar</button>
</form>
