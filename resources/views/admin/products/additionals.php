<?php
$product = is_array($product ?? null) ? $product : [];
$additionalGroup = is_array($additionalGroup ?? null) ? $additionalGroup : null;
$additionalItems = is_array($additionalItems ?? null) ? $additionalItems : [];
$isRequired = $additionalGroup !== null && (int) ($additionalGroup['is_required'] ?? 0) === 1;
$maxSelectionValue = $additionalGroup !== null && $additionalGroup['max_selection'] !== null ? (int) $additionalGroup['max_selection'] : 1;
$minSelectionValue = $additionalGroup !== null && $additionalGroup['min_selection'] !== null ? (int) $additionalGroup['min_selection'] : ($isRequired ? 1 : 0);
?>

<style>
    .additionals-layout{display:grid;grid-template-columns:1.3fr 1fr;gap:16px}
    .additionals-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
    .steps{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .step-pill{padding:4px 10px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:12px}
    .hero{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
    .hero-thumb{width:86px;height:86px;border-radius:12px;background:linear-gradient(135deg,#dbeafe,#e2e8f0);overflow:hidden;flex-shrink:0}
    .hero-thumb img{width:100%;height:100%;object-fit:cover}
    .next-actions{margin-top:12px;padding:10px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff}
    .item-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .additional-item{border:1px solid #e2e8f0;border-radius:12px;padding:12px;background:#f8fafc}
    .additional-item h4{margin:0 0 6px}
    @media (max-width:980px){
        .additionals-layout{grid-template-columns:1fr}
        .item-grid{grid-template-columns:1fr}
    }
</style>

<div class="topbar">
    <div>
        <h1>Adicionais do Produto</h1>
        <p>Mesmo fluxo visual do cadastro de produto para configurar regras e opcionais.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/products')) ?>">Voltar ao painel</a>
</div>

<div class="additionals-card" style="margin-bottom:16px">
    <div class="hero">
        <div class="hero-thumb">
            <?php if (!empty($product['image_path'])): ?>
                <img src="<?= htmlspecialchars(product_image_url((string) $product['image_path'])) ?>" alt="<?= htmlspecialchars((string) ($product['name'] ?? 'Produto')) ?>">
            <?php endif; ?>
        </div>
        <div>
            <strong><?= htmlspecialchars((string) ($product['name'] ?? 'Produto')) ?></strong><br>
            <small style="color:#64748b"><?= htmlspecialchars((string) ($product['slug'] ?? '-')) ?></small><br>
            <small style="color:#334155">Preco base: R$ <?= number_format((float) ($product['price'] ?? 0), 2, ',', '.') ?></small>
        </div>
    </div>
    <div class="next-actions">
        <strong style="display:block;margin-bottom:6px">Dica operacional</strong>
        <p style="margin:0;color:#334155">
            Defina primeiro o limite de selecao e depois cadastre os adicionais com nome e valor.
            Isso melhora a experiencia no pedido e evita selecoes fora da regra.
        </p>
    </div>
</div>

<div class="additionals-layout">
    <div class="additionals-card">
        <div class="steps">
            <span class="step-pill">1. Regras de selecao</span>
            <span class="step-pill">2. Cadastro de adicionais</span>
        </div>

        <h3 style="margin-top:0">Regras do produto</h3>
        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/products/additionals/rules')) ?>">
            <?= form_security_fields('products.additionals.rules') ?>
            <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">

            <div class="field">
                <label for="max_selection">Maximo de adicionais por item</label>
                <input
                    id="max_selection"
                    name="max_selection"
                    type="number"
                    min="1"
                    value="<?= $maxSelectionValue ?>"
                    placeholder="Ex.: 3"
                    required
                >
            </div>

            <div class="field">
                <label for="rule_required_mode">Modo de selecao</label>
                <select id="rule_required_mode" name="rule_required_mode">
                    <option value="opcional" <?= !$isRequired ? 'selected' : '' ?>>Opcional</option>
                    <option value="obrigatorio" <?= $isRequired ? 'selected' : '' ?>>Obrigatorio</option>
                </select>
                <small style="color:#64748b">Obrigatorio exige ao menos um adicional por item.</small>
            </div>

            <div class="field">
                <label for="min_selection">Minimo de adicionais por item</label>
                <input
                    id="min_selection"
                    name="min_selection"
                    type="number"
                    min="0"
                    value="<?= $minSelectionValue ?>"
                    placeholder="Ex.: 1"
                >
            </div>

            <button class="btn" type="submit">Salvar regras</button>
        </form>

        <hr style="margin:18px 0;border:none;border-top:1px solid #e2e8f0">

        <h3 style="margin-top:0">Novo adicional</h3>
        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/products/additionals/store')) ?>">
            <?= form_security_fields('products.additionals.store') ?>
            <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">

            <div class="field">
                <label for="additional_name">Nome do adicional</label>
                <input id="additional_name" name="name" type="text" required placeholder="Ex.: Bacon crocante">
            </div>

            <div class="grid two">
                <div class="field">
                    <label for="additional_price">Valor (R$)</label>
                    <input id="additional_price" name="price" type="number" min="0" step="0.01" required placeholder="Ex.: 4.50">
                </div>
                <div class="field">
                    <label for="display_order">Ordem de exibicao</label>
                    <input id="display_order" name="display_order" type="number" min="0" value="0" placeholder="Ex.: 1">
                </div>
            </div>

            <div class="field">
                <label for="additional_description">Descricao (opcional)</label>
                <input id="additional_description" name="description" type="text" placeholder="Ex.: Porcao extra no ponto do cliente">
            </div>

            <button class="btn" type="submit">Adicionar</button>
        </form>
    </div>

    <div class="additionals-card">
        <div class="steps">
            <span class="step-pill">3. Lista de adicionais</span>
        </div>
        <h3 style="margin-top:0">Itens cadastrados</h3>

        <?php if (empty($additionalItems)): ?>
            <div class="additional-item">
                Nenhum adicional cadastrado para este produto.
            </div>
        <?php else: ?>
            <div class="item-grid">
                <?php foreach ($additionalItems as $additional): ?>
                    <div class="additional-item">
                        <h4><?= htmlspecialchars((string) ($additional['name'] ?? '-')) ?></h4>
                        <div style="margin-bottom:8px">
                            <span class="badge <?= htmlspecialchars((string) (($additional['status'] ?? '') === 'ativo' ? 'status-active' : 'status-inactive')) ?>">
                                <?= htmlspecialchars((string) (($additional['status'] ?? '') === 'ativo' ? 'Ativo' : 'Inativo')) ?>
                            </span>
                        </div>
                        <div style="color:#334155">
                            <strong>R$ <?= number_format((float) ($additional['price'] ?? 0), 2, ',', '.') ?></strong>
                        </div>
                        <?php if (!empty($additional['description'])): ?>
                            <p style="margin:8px 0;color:#64748b"><?= htmlspecialchars((string) $additional['description']) ?></p>
                        <?php endif; ?>

                        <?php if (($additional['status'] ?? '') === 'ativo'): ?>
                            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/products/additionals/remove')) ?>" onsubmit="return confirm('Remover este adicional?');" style="margin-top:10px">
                                <?= form_security_fields('products.additionals.remove') ?>
                                <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">
                                <input type="hidden" name="additional_item_id" value="<?= (int) ($additional['id'] ?? 0) ?>">
                                <button class="btn secondary" type="submit">Remover</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(() => {
    const mode = document.getElementById('rule_required_mode');
    const min = document.getElementById('min_selection');
    if (!mode || !min) {
        return;
    }

    const syncMin = () => {
        const isRequired = mode.value === 'obrigatorio';
        const current = Number(min.value || 0);
        min.min = isRequired ? '1' : '0';
        if (isRequired && current < 1) {
            min.value = '1';
        }
    };

    mode.addEventListener('change', syncMin);
    syncMin();
})();
</script>
