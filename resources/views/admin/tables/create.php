<div class="topbar">
    <div>
        <h1>Nova Mesa</h1>
        <p>Cadastro inicial de mesa operacional.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/tables')) ?>">Voltar</a>
</div>

<div class="card">
    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/tables/store')) ?>">
        <div class="grid two">
            <div class="field">
                <label for="number">Número da mesa</label>
                <input id="number" name="number" type="number" min="1" required>
            </div>

            <div class="field">
                <label for="capacity">Capacidade</label>
                <input id="capacity" name="capacity" type="number" min="1">
            </div>
        </div>

        <div class="field">
            <label for="name">Nome da mesa</label>
            <input id="name" name="name" type="text" placeholder="Ex.: Varanda 01">
        </div>

        <button class="btn" type="submit">Salvar mesa</button>
    </form>
</div>
