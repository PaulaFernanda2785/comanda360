<?php
$product = is_array($product ?? null) ? $product : [];
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$formAction = (string) ($formAction ?? base_url('/admin/products/store'));
$submitLabel = (string) ($submitLabel ?? 'Salvar produto');
$existingImagePath = product_image_url((string) ($product['image_path'] ?? ''));
$operationalStatus = 'ativo';
if (isset($product['is_paused']) && (int) $product['is_paused'] === 1) {
    $operationalStatus = 'pausado';
} elseif (isset($product['is_active']) && (int) $product['is_active'] === 0) {
    $operationalStatus = 'inativo';
}
$hasCategories = !empty($categories);
?>

<style>
    .product-form-layout{display:grid;grid-template-columns:1.3fr 1fr;gap:16px}
    .product-form-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
    .dropzone{border:2px dashed #93c5fd;border-radius:14px;padding:18px;text-align:center;background:linear-gradient(180deg,#eff6ff,#f8fafc);cursor:pointer}
    .dropzone.dragover{border-color:#1d4ed8;background:#dbeafe}
    .dropzone p{margin:6px 0;color:#475569}
    .dropzone small{color:#64748b}
    .image-preview{margin-top:12px;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;min-height:160px;background:#f8fafc;display:flex;align-items:center;justify-content:center}
    .image-preview img{width:100%;max-height:240px;object-fit:cover;display:block}
    .upload-actions{margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .upload-file-name{font-size:12px;color:#64748b}
    .steps{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .step-pill{padding:4px 10px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:12px}
    .status-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .status-option{position:relative;border:1px solid #cbd5e1;border-radius:10px;padding:10px;background:#f8fafc;cursor:pointer}
    .status-option input{position:absolute;opacity:0;pointer-events:none}
    .status-option strong{display:block;font-size:13px}
    .status-option small{display:block;color:#64748b;margin-top:3px;font-size:12px;line-height:1.3}
    .status-option.active{border-color:#1d4ed8;background:#dbeafe}
    .actions-stack{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .btn-modern-link{display:inline-flex;align-items:center;gap:6px;padding:10px 14px;border-radius:10px;text-decoration:none;border:1px solid #1d4ed8;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;font-weight:600}
    .btn-modern-link:hover{filter:brightness(1.03)}
    .btn-modern-danger{display:inline-flex;align-items:center;gap:6px;padding:10px 14px;border-radius:10px;border:1px solid #b91c1c;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;font-weight:600;cursor:pointer}
    .btn-modern-danger:hover{filter:brightness(1.03)}
    .image-help{color:#64748b;font-size:12px;margin-top:8px}
    .next-actions{margin-top:12px;padding:10px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff}
    @media (max-width:980px){
        .product-form-layout{grid-template-columns:1fr}
    }
</style>

<div class="topbar">
    <div>
        <h1><?= $isEdit ? 'Editar Produto' : 'Novo Produto' ?></h1>
        <p>Formulario dinamico para cadastro assertivo de produtos e imagem.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/products')) ?>">Voltar ao painel</a>
</div>

<form method="POST" action="<?= htmlspecialchars($formAction) ?>" enctype="multipart/form-data">
    <?= form_security_fields($isEdit ? 'products.update' : 'products.store') ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">
    <?php endif; ?>
    <input type="hidden" id="image_data_base64" name="image_data_base64" value="">
    <input type="hidden" id="image_data_name" name="image_data_name" value="">

    <div class="product-form-layout">
        <div class="product-form-card">
            <div class="steps">
                <span class="step-pill">1. Dados basicos</span>
                <span class="step-pill">2. Preco e disponibilidade</span>
            </div>

            <div class="grid two">
                <div class="field">
                    <label for="name">Nome do produto</label>
                    <input id="name" name="name" type="text" required value="<?= htmlspecialchars((string) ($product['name'] ?? '')) ?>" placeholder="Ex.: Burger Artesanal 180g">
                </div>
                <div class="field">
                    <label for="slug">Slug</label>
                    <input id="slug" name="slug" type="text" required value="<?= htmlspecialchars((string) ($product['slug'] ?? '')) ?>" placeholder="Ex.: burger-artesanal">
                </div>
            </div>

            <div class="grid two">
                <div class="field">
                    <label for="category_id">Categoria</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Selecione</option>
                        <?php foreach (($categories ?? []) as $category): ?>
                            <option
                                value="<?= (int) ($category['id'] ?? 0) ?>"
                                <?= (int) ($product['category_id'] ?? 0) === (int) ($category['id'] ?? 0) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars((string) ($category['name'] ?? 'Categoria')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$hasCategories): ?>
                        <small style="color:#b45309">Nenhuma categoria ativa disponivel. Cadastre categorias no painel de produtos antes de salvar.</small>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label for="sku">SKU</label>
                    <input id="sku" name="sku" type="text" value="<?= htmlspecialchars((string) ($product['sku'] ?? '')) ?>" placeholder="Ex.: BG-180-AZ">
                </div>
            </div>

            <div class="field">
                <label for="description">Descricao</label>
                <textarea id="description" name="description" rows="4" placeholder="Ex.: Pao brioche, burger angus, queijo cheddar e molho especial."><?= htmlspecialchars((string) ($product['description'] ?? '')) ?></textarea>
            </div>

            <div class="grid two">
                <div class="field">
                    <label for="price">Preco (R$)</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" required value="<?= htmlspecialchars((string) ($product['price'] ?? '0.00')) ?>" placeholder="Ex.: 29.90">
                </div>
                <div class="field">
                    <label for="promotional_price">Preco promocional (R$)</label>
                    <input id="promotional_price" name="promotional_price" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string) ($product['promotional_price'] ?? '')) ?>" placeholder="Ex.: 24.90">
                </div>
            </div>

            <div class="grid two">
                <div class="field">
                    <label for="display_order">Ordem de exibicao</label>
                    <input id="display_order" name="display_order" type="number" min="0" value="<?= (int) ($product['display_order'] ?? 0) ?>" placeholder="Ex.: 10">
                </div>
                <div class="field">
                    <label>Status operacional</label>
                    <div class="status-grid" style="margin-top:8px">
                        <label class="status-option">
                            <input type="radio" name="operational_status" value="ativo" <?= $operationalStatus === 'ativo' ? 'checked' : '' ?>>
                            <strong>Ativo</strong>
                            <small>Disponivel para venda imediatamente.</small>
                        </label>
                        <label class="status-option">
                            <input type="radio" name="operational_status" value="pausado" <?= $operationalStatus === 'pausado' ? 'checked' : '' ?>>
                            <strong>Pausado</strong>
                            <small>Oculta temporariamente sem excluir.</small>
                        </label>
                        <label class="status-option">
                            <input type="radio" name="operational_status" value="inativo" <?= $operationalStatus === 'inativo' ? 'checked' : '' ?>>
                            <strong>Inativo</strong>
                            <small>Desativa para operacao ate nova edicao.</small>
                        </label>
                        <label class="status-option">
                            <input type="checkbox" name="is_featured" <?= (int) ($product['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>>
                            <strong>Destaque</strong>
                            <small>Prioridade visual no cardapio.</small>
                        </label>
                        <label class="status-option">
                            <input type="checkbox" name="allows_notes" <?= !isset($product['allows_notes']) || (int) $product['allows_notes'] === 1 ? 'checked' : '' ?>>
                            <strong>Permite observacao</strong>
                            <small>Cliente pode descrever preferencias.</small>
                        </label>
                        <label class="status-option">
                            <input type="checkbox" name="has_additionals" <?= (int) ($product['has_additionals'] ?? 0) === 1 ? 'checked' : '' ?>>
                            <strong>Possui adicionais</strong>
                            <small>Habilita opcionais extras.</small>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="product-form-card">
            <div class="steps">
                <span class="step-pill">3. Imagem do produto</span>
            </div>

            <label for="image_file" style="display:block;margin-bottom:8px;font-weight:bold">Anexar imagem</label>
            <div id="dropzone" class="dropzone" tabindex="0">
                <p><strong>Selecione, arraste ou cole (Ctrl+V) a imagem aqui</strong></p>
                <small>Formatos: JPG, PNG, WEBP ou GIF. Tamanho maximo: 10MB.</small>
            </div>
            <div class="field" style="margin-top:10px">
                <input type="file" id="image_file" name="image_file" accept="image/*">
            </div>
            <div class="upload-actions">
                <button class="btn secondary" type="button" id="chooseImageButton">Selecionar imagem</button>
                <span class="upload-file-name" id="selectedFileName">Nenhum arquivo selecionado.</span>
            </div>
            <p class="image-help">Depois de selecionar, clique em "<?= htmlspecialchars($submitLabel) ?>" para aplicar a imagem no produto.</p>

            <div class="image-preview" id="imagePreview">
                <?php if ($existingImagePath !== ''): ?>
                    <img id="previewImage" src="<?= htmlspecialchars($existingImagePath) ?>" alt="Imagem do produto">
                <?php else: ?>
                    <span id="previewPlaceholder" style="color:#64748b">Nenhuma imagem selecionada.</span>
                <?php endif; ?>
            </div>

            <div style="margin-top:14px">
                <button class="btn" type="submit" <?= !$hasCategories ? 'disabled' : '' ?>><?= htmlspecialchars($submitLabel) ?></button>
            </div>

            <?php if ($isEdit): ?>
                <div class="next-actions">
                    <strong style="display:block;margin-bottom:6px">Ajustes avancados do produto</strong>
                    <p style="margin:0;color:#334155">
                        Defina limite de escolhas e cadastre nome/valor de opcionais na tela dedicada.
                    </p>
                    <div class="actions-stack">
                        <a class="btn-modern-link" href="<?= htmlspecialchars(base_url('/admin/products/additionals?product_id=' . (int) ($product['id'] ?? 0))) ?>">
                            Gerenciar adicionais
                        </a>
                        <?php if ($existingImagePath !== ''): ?>
                            <button type="submit" form="removeImageForm" class="btn-modern-danger" onclick="return confirm('Remover a imagem deste produto agora?');">
                                Remover imagem
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($isEdit && $existingImagePath !== ''): ?>
    <form id="removeImageForm" method="POST" action="<?= htmlspecialchars(base_url('/admin/products/remove-image')) ?>">
        <?= form_security_fields('products.image.remove') ?>
        <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">
    </form>
<?php endif; ?>

<script>
(() => {
    const input = document.getElementById('image_file');
    const dropzone = document.getElementById('dropzone');
    const preview = document.getElementById('imagePreview');
    const chooseImageButton = document.getElementById('chooseImageButton');
    const selectedFileName = document.getElementById('selectedFileName');
    const imageDataBase64 = document.getElementById('image_data_base64');
    const imageDataName = document.getElementById('image_data_name');
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');

    const normalizeText = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
    const slugify = (value) => normalizeText(value).replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    const setPreview = (file) => {
        if (!(file instanceof File)) {
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.id = 'previewImage';
            img.src = String(reader.result || '');
            img.alt = 'Preview da imagem';
            preview.appendChild(img);
            if (imageDataBase64) {
                imageDataBase64.value = String(reader.result || '');
            }
            if (imageDataName) {
                imageDataName.value = file.name || '';
            }
        };
        reader.readAsDataURL(file);
    };

    const assignFile = (file) => {
        if (!(file instanceof File) || !input) {
            return;
        }

        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        if (selectedFileName) {
            selectedFileName.textContent = file.name || 'Imagem selecionada.';
        }
        setPreview(file);
    };

    if (nameInput && slugInput) {
        nameInput.addEventListener('input', () => {
            if (slugInput.dataset.touched === '1') {
                return;
            }
            slugInput.value = slugify(nameInput.value);
        });
        slugInput.addEventListener('input', () => {
            slugInput.dataset.touched = '1';
        });
    }

    const refreshStatusOptions = () => {
        document.querySelectorAll('.status-option').forEach((option) => {
            const control = option.querySelector('input[type="checkbox"], input[type="radio"]');
            option.classList.toggle('active', !!control && control.checked);
        });
    };
    document.querySelectorAll('.status-option input[type="checkbox"], .status-option input[type="radio"]').forEach((control) => {
        control.addEventListener('change', refreshStatusOptions);
    });
    refreshStatusOptions();

    const firstImageFileFromList = (files) => {
        for (const file of files) {
            if (file && String(file.type || '').startsWith('image/')) {
                return file;
            }
        }
        return null;
    };

    if (input) {
        input.addEventListener('change', () => {
            const file = input.files && input.files.length > 0 ? input.files[0] : null;
            if (file) {
                if (selectedFileName) {
                    selectedFileName.textContent = file.name || 'Imagem selecionada.';
                }
                setPreview(file);
            } else if (selectedFileName) {
                selectedFileName.textContent = 'Nenhum arquivo selecionado.';
                if (imageDataBase64) {
                    imageDataBase64.value = '';
                }
                if (imageDataName) {
                    imageDataName.value = '';
                }
            }
        });
    }

    if (dropzone && input) {
        dropzone.addEventListener('click', () => input.click());
        dropzone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', (event) => {
            const file = firstImageFileFromList(event.dataTransfer ? event.dataTransfer.files : []);
            if (file) {
                assignFile(file);
            }
        });
    }

    if (chooseImageButton && input) {
        chooseImageButton.addEventListener('click', () => input.click());
    }

    document.addEventListener('paste', (event) => {
        const clipboard = event.clipboardData;
        if (!clipboard) {
            return;
        }

        const file = firstImageFileFromList(clipboard.files || []);
        if (file) {
            assignFile(file);
        }
    });
})();
</script>
