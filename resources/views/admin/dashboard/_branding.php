<?php
$companyName = trim((string) ($company['name'] ?? ''));
$companyTitle = trim((string) ($company['title'] ?? ''));
$companyDescription = trim((string) ($company['description'] ?? ''));
$footerText = trim((string) ($company['footer_text'] ?? ''));
$logoPath = trim((string) ($company['logo_path'] ?? ''));
$bannerPath = trim((string) ($company['banner_path'] ?? ''));
$primaryColor = trim((string) ($company['primary_color'] ?? '#1d4ed8'));
$secondaryColor = trim((string) ($company['secondary_color'] ?? '#0f172a'));
$accentColor = trim((string) ($company['accent_color'] ?? '#0ea5e9'));

$logoUrl = $logoPath !== '' ? company_image_url($logoPath) : '';
$bannerUrl = $bannerPath !== '' ? company_image_url($bannerPath) : '';
?>

<section class="dash-section<?= $activeSection === 'branding' ? ' active' : '' ?>" data-section="branding">
    <style>
        .branding-layout{display:grid;gap:14px;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)}
        .branding-hero{border:1px solid #bfdbfe;background:linear-gradient(118deg,#0f172a 0%,#1e3a8a 58%,#0ea5e9 100%);border-radius:14px;padding:16px;position:relative;overflow:hidden;color:#fff}
        .branding-hero:before{content:"";position:absolute;top:-60px;right:-48px;width:210px;height:210px;border-radius:999px;background:rgba(255,255,255,.12)}
        .branding-hero:after{content:"";position:absolute;bottom:-70px;left:-34px;width:180px;height:180px;border-radius:999px;background:rgba(255,255,255,.1)}
        .branding-hero-body{position:relative;z-index:1;display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}
        .branding-hero h3{margin:0 0 6px;font-size:20px;color:#fff}
        .branding-hero p{margin:0;color:#dbeafe;line-height:1.5;max-width:760px}
        .branding-hero-actions{margin-top:10px;display:flex;gap:8px;flex-wrap:wrap}
        .branding-hero-pills{display:flex;gap:8px;flex-wrap:wrap}
        .branding-hero-pill{border:1px solid rgba(255,255,255,.3);background:rgba(15,23,42,.38);border-radius:999px;padding:6px 11px;font-size:12px;font-weight:600;white-space:nowrap}
        .btn-outline-danger{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid rgba(248,113,113,.75);background:rgba(127,29,29,.35);color:#fee2e2;text-decoration:none;font-weight:600;cursor:pointer}
        .btn-outline-danger:hover{background:rgba(220,38,38,.35)}
        .btn-modern-danger{display:inline-flex;align-items:center;gap:6px;padding:10px 14px;border-radius:10px;border:1px solid #b91c1c;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;font-weight:600;cursor:pointer}
        .btn-modern-danger:hover{filter:brightness(1.03)}
        .branding-main{display:grid;gap:14px}
        .branding-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .branding-form-grid .field.full{grid-column:1 / -1}
        .branding-color-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
        .branding-color-card{border:1px solid #dbeafe;border-radius:12px;background:#fff;padding:10px}
        .branding-color-card strong{display:block;font-size:13px;color:#0f172a}
        .branding-color-card small{display:block;font-size:12px;color:#64748b;margin-top:2px}
        .branding-color-inputs{display:grid;grid-template-columns:52px 1fr;gap:8px;margin-top:8px;align-items:center}
        .branding-color-inputs input[type="color"]{padding:0;height:42px;border-radius:10px;cursor:pointer}
        .branding-color-inputs input[type="text"]{font-family:Consolas,monospace;text-transform:uppercase}
        .branding-preview-shell{border:1px solid #dbeafe;border-radius:12px;overflow:hidden}
        .branding-preview-head{padding:10px;background:linear-gradient(135deg,var(--brand-preview-accent,#0ea5e9),#0f172a);color:#fff}
        .branding-preview-menu{padding:10px;background:linear-gradient(185deg,var(--brand-preview-secondary,#0f172a) 0%,#111827 100%);color:#e2e8f0}
        .branding-preview-btn{display:inline-flex;padding:8px 12px;border-radius:8px;background:var(--brand-preview-primary,#1d4ed8);color:#fff;font-weight:600;font-size:12px}
        .branding-upload-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .branding-upload-card{border:1px solid #dbeafe;border-radius:12px;padding:12px;background:#fff}
        .dropzone{border:2px dashed #93c5fd;border-radius:14px;padding:16px;text-align:center;background:linear-gradient(180deg,#eff6ff,#f8fafc);cursor:pointer}
        .dropzone.dragover{border-color:#1d4ed8;background:#dbeafe}
        .dropzone p{margin:6px 0;color:#475569}
        .dropzone small{color:#64748b}
        .upload-actions{margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .upload-file-name{font-size:12px;color:#64748b}
        .brand-media-preview{margin-top:12px;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;min-height:140px;background:#f8fafc;display:flex;align-items:center;justify-content:center}
        .brand-media-preview img{width:100%;max-height:220px;object-fit:cover;display:block}
        .brand-media-preview .empty-state{padding:14px;color:#64748b;text-align:center}
        .is-hidden{display:none !important}
        .branding-side{display:grid;gap:14px}
        .branding-side .card{padding:16px}
        .branding-legend{display:grid;gap:8px}
        .branding-legend-item{display:flex;justify-content:space-between;gap:10px;padding:8px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;font-size:13px}
        .branding-dot{width:12px;height:12px;border-radius:999px;display:inline-block;margin-right:6px;vertical-align:-1px}
        .branding-access-card{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
        .branding-access-card h4{margin:0 0 8px;color:#1e1b4b}
        .branding-access-card p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
        .branding-access-list{display:grid;gap:8px;margin-top:10px}
        .branding-access-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;background:#fff;border:1px solid #c7d2fe;font-size:13px}
        .branding-access-item strong{color:#312e81}
        .branding-access-badge{padding:4px 8px;border-radius:999px;background:#e0e7ff;color:#312e81;font-size:11px;font-weight:600}
        @media (max-width:1180px){
            .branding-layout{grid-template-columns:1fr}
            .branding-color-grid,.branding-upload-grid{grid-template-columns:1fr}
        }
        @media (max-width:760px){
            .branding-form-grid{grid-template-columns:1fr}
        }
    </style>

    <div class="branding-layout">
        <div class="branding-main">
            <div class="branding-hero">
                <div class="branding-hero-body">
                    <div>
                        <h3>Personalização visual por estabelecimento</h3>
                        <p>Defina nome comercial, cores do sistema, logo no menu/tickets e banner do cabecalho. O upload abaixo aceita selecionar, arrastar e colar imagem (Ctrl+V).</p>
                        <div class="branding-hero-actions">
                            <button class="btn-outline-danger" type="submit" form="restoreFactoryThemeForm" onclick="return confirm('Restaurar o estilo de fabrica e remover logo/banner atuais?');">
                                Restaurar estilo de fabrica
                            </button>
                        </div>
                    </div>
                    <div class="branding-hero-pills">
                        <span class="branding-hero-pill">Tema do painel</span>
                        <span class="branding-hero-pill">Logo e banner</span>
                        <span class="branding-hero-pill">Paleta personalizada</span>
                    </div>
                </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/theme')) ?>" enctype="multipart/form-data">
                <?= form_security_fields('dashboard.theme.update') ?>
                <input type="hidden" id="logo_data_base64" name="logo_data_base64" value="">
                <input type="hidden" id="logo_data_name" name="logo_data_name" value="">
                <input type="hidden" id="banner_data_base64" name="banner_data_base64" value="">
                <input type="hidden" id="banner_data_name" name="banner_data_name" value="">

                <div class="card">
                    <div class="branding-form-grid">
                        <div class="field">
                            <label for="company_name">Nome da empresa</label>
                            <input id="company_name" name="company_name" type="text" required value="<?= htmlspecialchars($companyName) ?>">
                        </div>
                        <div class="field">
                            <label for="title">Título acima do Comanda360</label>
                            <input id="title" name="title" type="text" value="<?= htmlspecialchars($companyTitle !== '' ? $companyTitle : $companyName) ?>">
                        </div>
                        <div class="field full">
                            <label for="description">Descrição da empresa</label>
                            <textarea id="description" name="description" rows="3"><?= htmlspecialchars($companyDescription) ?></textarea>
                        </div>
                        <div class="field full">
                            <label for="footer_text">Texto de rodapé</label>
                            <input id="footer_text" name="footer_text" type="text" value="<?= htmlspecialchars($footerText) ?>">
                        </div>
                    </div>

                    <div class="branding-color-grid">
                        <article class="branding-color-card">
                            <strong>Cor dos botões e ação principal</strong>
                            <small>Afeta botões principais e destaques de ação.</small>
                            <div class="branding-color-inputs">
                                <input id="primary_color" name="primary_color" type="color" value="<?= htmlspecialchars($primaryColor) ?>" data-color-key="primary">
                                <input id="primary_color_text" type="text" value="<?= htmlspecialchars($primaryColor) ?>" data-color-text-for="primary_color" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </article>
                        <article class="branding-color-card">
                            <strong>Cor do menu lateral</strong>
                            <small>Define o fundo principal da navegação lateral.</small>
                            <div class="branding-color-inputs">
                                <input id="secondary_color" name="secondary_color" type="color" value="<?= htmlspecialchars($secondaryColor) ?>" data-color-key="secondary">
                                <input id="secondary_color_text" type="text" value="<?= htmlspecialchars($secondaryColor) ?>" data-color-text-for="secondary_color" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </article>
                        <article class="branding-color-card">
                            <strong>Cor de cabeçalho e rodapé</strong>
                            <small>Usada no gradiente do cabeçalho e rodapé.</small>
                            <div class="branding-color-inputs">
                                <input id="accent_color" name="accent_color" type="color" value="<?= htmlspecialchars($accentColor) ?>" data-color-key="accent">
                                <input id="accent_color_text" type="text" value="<?= htmlspecialchars($accentColor) ?>" data-color-text-for="accent_color" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                        </article>
                    </div>

                    <div class="branding-preview-shell" id="brandingSystemPreview" style="--brand-preview-primary:<?= htmlspecialchars($primaryColor) ?>;--brand-preview-secondary:<?= htmlspecialchars($secondaryColor) ?>;--brand-preview-accent:<?= htmlspecialchars($accentColor) ?>;margin-top:12px">
                        <div class="branding-preview-head">
                            <strong><?= htmlspecialchars($companyName !== '' ? $companyName : 'Estabelecimento') ?></strong>
                            <div style="font-size:12px;opacity:.9">Cabeçalho com usuário e perfil</div>
                        </div>
                        <div class="branding-preview-menu">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center">
                                <span>Menu lateral</span>
                                <span class="branding-preview-btn">Botão principal</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="branding-upload-grid">
                        <article class="branding-upload-card" data-upload-kind="logo">
                            <h4 style="margin:0 0 4px">Logo do estabelecimento</h4>
                            <p class="ticket-note">Aparece no menu lateral e nos tickets gerados.</p>

                            <div id="logo_dropzone" class="dropzone" tabindex="0">
                                <p><strong>Selecione, arraste ou cole (Ctrl+V) a logo aqui</strong></p>
                                <small>Formatos: JPG, PNG, WEBP ou GIF. Máximo: 10MB.</small>
                            </div>
                            <p class="ticket-note" style="margin-top:8px">Tamanho recomendado da logo: <strong>512 x 512 px</strong> (minimo 256 x 256 px) para manter boa leitura no menu e tickets.</p>
                            <div class="field" style="margin-top:10px">
                                <input id="logo_file" name="logo_file" type="file" accept="image/*">
                            </div>
                            <div class="upload-actions">
                                <button class="btn secondary" type="button" id="logo_choose_btn">Selecionar logo</button>
                                <button class="btn-modern-danger" type="button" id="remove_logo_button">Remover logo</button>
                                <span class="upload-file-name" id="logo_selected_file">Nenhum arquivo selecionado.</span>
                            </div>

                            <div class="brand-media-preview">
                                <img id="logo_preview_image" src="<?= htmlspecialchars($logoUrl) ?>" alt="Preview da logo"<?= $logoUrl === '' ? ' class="is-hidden"' : '' ?>>
                                <div id="logo_preview_empty" class="empty-state<?= $logoUrl !== '' ? ' is-hidden' : '' ?>">Nenhuma logo cadastrada.</div>
                            </div>

                            <input type="checkbox" id="remove_logo" name="remove_logo" value="1" class="is-hidden">
                            <small class="ticket-note">A remoção será aplicada ao salvar a personalização.</small>
                        </article>

                        <article class="branding-upload-card" data-upload-kind="banner">
                            <h4 style="margin:0 0 4px">Banner do cabeçalho</h4>
                            <p class="ticket-note">Fica no topo junto do usuário logado e perfil.</p>

                            <div id="banner_dropzone" class="dropzone" tabindex="0">
                                <p><strong>Selecione, arraste ou cole (Ctrl+V) o banner aqui</strong></p>
                                <small>Formatos: JPG, PNG, WEBP ou GIF. Máximo: 10MB.</small>
                            </div>
                            <p class="ticket-note" style="margin-top:8px">Tamanho recomendado do banner: <strong>1920 x 420 px</strong> (minimo 1366 x 300 px) para melhor visibilidade no cabecalho.</p>
                            <div class="field" style="margin-top:10px">
                                <input id="banner_file" name="banner_file" type="file" accept="image/*">
                            </div>
                            <div class="upload-actions">
                                <button class="btn secondary" type="button" id="banner_choose_btn">Selecionar banner</button>
                                <button class="btn-modern-danger" type="button" id="remove_banner_button">Remover banner</button>
                                <span class="upload-file-name" id="banner_selected_file">Nenhum arquivo selecionado.</span>
                            </div>

                            <div class="brand-media-preview">
                                <img id="banner_preview_image" src="<?= htmlspecialchars($bannerUrl) ?>" alt="Preview do banner"<?= $bannerUrl === '' ? ' class="is-hidden"' : '' ?>>
                                <div id="banner_preview_empty" class="empty-state<?= $bannerUrl !== '' ? ' is-hidden' : '' ?>">Nenhum banner cadastrado.</div>
                            </div>

                            <input type="checkbox" id="remove_banner" name="remove_banner" value="1" class="is-hidden">
                            <small class="ticket-note">A remoção será aplicada ao salvar a personalização.</small>
                        </article>
                    </div>

                    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
                        <button class="btn" type="submit">Salvar personalização</button>
                    </div>
                </div>
            </form>
        </div>

        <aside class="branding-side">
            <div class="card">
                <h4 style="margin:0 0 8px">Resumo da identidade atual</h4>
                <div class="branding-legend">
                    <div class="branding-legend-item">
                        <span><span class="branding-dot" style="background:<?= htmlspecialchars($primaryColor) ?>"></span>Cor dos botões</span>
                        <strong><?= htmlspecialchars(strtoupper($primaryColor)) ?></strong>
                    </div>
                    <div class="branding-legend-item">
                        <span><span class="branding-dot" style="background:<?= htmlspecialchars($secondaryColor) ?>"></span>Cor do menu</span>
                        <strong><?= htmlspecialchars(strtoupper($secondaryColor)) ?></strong>
                    </div>
                    <div class="branding-legend-item">
                        <span><span class="branding-dot" style="background:<?= htmlspecialchars($accentColor) ?>"></span>Cor do cabeçalho</span>
                        <strong><?= htmlspecialchars(strtoupper($accentColor)) ?></strong>
                    </div>
                </div>
            </div>
            <div class="branding-access-card">
                <h4>Controle e governança</h4>
                <p>Configurações de identidade impactam toda a operação visual do estabelecimento e os documentos emitidos.</p>
                <div class="branding-access-list">
                    <div class="branding-access-item">
                        <strong>Permissão de edição</strong>
                        <span class="branding-access-badge">Administrador / Gerente</span>
                    </div>
                    <div class="branding-access-item">
                        <strong>Aplicacao</strong>
                        <span class="branding-access-badge">Menu, cabeçalho, rodapé e tickets</span>
                    </div>
                    <div class="branding-access-item">
                        <strong>Risco operacional</strong>
                        <span class="branding-access-badge">Alto impacto visual</span>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <form id="restoreFactoryThemeForm" method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/theme/restore')) ?>">
        <?= form_security_fields('dashboard.theme.restore') ?>
    </form>

    <script>
    (() => {
        const toUpperHex = (value) => String(value || '').trim().toUpperCase();
        const normalizeHex = (value) => {
            const cleaned = String(value || '').trim().replace(/[^#0-9a-fA-F]/g, '');
            if (/^#[0-9a-fA-F]{6}$/.test(cleaned)) {
                return cleaned.toLowerCase();
            }
            return null;
        };

        const previewShell = document.getElementById('brandingSystemPreview');
        const syncColorPair = (colorInput, textInput) => {
            if (!(colorInput instanceof HTMLInputElement) || !(textInput instanceof HTMLInputElement)) {
                return;
            }

            const applyColor = (hex) => {
                colorInput.value = hex;
                textInput.value = toUpperHex(hex);
                if (previewShell instanceof HTMLElement) {
                    if (colorInput.id === 'primary_color') {
                        previewShell.style.setProperty('--brand-preview-primary', hex);
                    } else if (colorInput.id === 'secondary_color') {
                        previewShell.style.setProperty('--brand-preview-secondary', hex);
                    } else if (colorInput.id === 'accent_color') {
                        previewShell.style.setProperty('--brand-preview-accent', hex);
                    }
                }
            };

            colorInput.addEventListener('input', () => {
                applyColor(colorInput.value || '#000000');
            });

            textInput.addEventListener('input', () => {
                const normalized = normalizeHex(textInput.value);
                if (!normalized) {
                    return;
                }
                applyColor(normalized);
            });

            textInput.addEventListener('blur', () => {
                const normalized = normalizeHex(textInput.value);
                if (!normalized) {
                    textInput.value = toUpperHex(colorInput.value || '#000000');
                }
            });
        };

        syncColorPair(document.getElementById('primary_color'), document.getElementById('primary_color_text'));
        syncColorPair(document.getElementById('secondary_color'), document.getElementById('secondary_color_text'));
        syncColorPair(document.getElementById('accent_color'), document.getElementById('accent_color_text'));

        const firstImageFileFromList = (files) => {
            for (const file of files) {
                if (file && String(file.type || '').startsWith('image/')) {
                    return file;
                }
            }
            return null;
        };

        let activeUploadKind = 'logo';
        const assigners = {};

        const setupBrandUpload = (kind) => {
            const input = document.getElementById(kind + '_file');
            const dropzone = document.getElementById(kind + '_dropzone');
            const chooseButton = document.getElementById(kind + '_choose_btn');
            const selectedFileLabel = document.getElementById(kind + '_selected_file');
            const hiddenBase64 = document.getElementById(kind + '_data_base64');
            const hiddenName = document.getElementById(kind + '_data_name');
            const previewImage = document.getElementById(kind + '_preview_image');
            const previewEmpty = document.getElementById(kind + '_preview_empty');
            const removeCheckbox = document.getElementById('remove_' + kind);
            const removeButton = document.getElementById('remove_' + kind + '_button');

            const setPreview = (file) => {
                if (!(file instanceof File)) {
                    return;
                }

                const reader = new FileReader();
                reader.onload = () => {
                    if (previewImage instanceof HTMLImageElement) {
                        previewImage.src = String(reader.result || '');
                        previewImage.classList.remove('is-hidden');
                    }
                    if (previewEmpty instanceof HTMLElement) {
                        previewEmpty.classList.add('is-hidden');
                    }
                    if (hiddenBase64 instanceof HTMLInputElement) {
                        hiddenBase64.value = String(reader.result || '');
                    }
                    if (hiddenName instanceof HTMLInputElement) {
                        hiddenName.value = file.name || '';
                    }
                    if (removeCheckbox instanceof HTMLInputElement) {
                        removeCheckbox.checked = false;
                    }
                };
                reader.readAsDataURL(file);
            };

            const assignFile = (file) => {
                if (!(file instanceof File) || !(input instanceof HTMLInputElement)) {
                    return;
                }

                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;

                if (selectedFileLabel instanceof HTMLElement) {
                    selectedFileLabel.textContent = file.name || 'Imagem selecionada.';
                }
                setPreview(file);
            };

            assigners[kind] = assignFile;

            if (input instanceof HTMLInputElement) {
                input.addEventListener('focus', () => {
                    activeUploadKind = kind;
                });
                input.addEventListener('change', () => {
                    const file = input.files && input.files.length > 0 ? input.files[0] : null;
                    if (file) {
                        if (selectedFileLabel instanceof HTMLElement) {
                            selectedFileLabel.textContent = file.name || 'Imagem selecionada.';
                        }
                        setPreview(file);
                    }
                });
            }

            if (chooseButton instanceof HTMLElement && input instanceof HTMLInputElement) {
                chooseButton.addEventListener('click', () => {
                    activeUploadKind = kind;
                    input.click();
                });
            }

            if (dropzone instanceof HTMLElement && input instanceof HTMLInputElement) {
                dropzone.addEventListener('click', () => {
                    activeUploadKind = kind;
                    input.click();
                });
                dropzone.addEventListener('focus', () => {
                    activeUploadKind = kind;
                });
                dropzone.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        activeUploadKind = kind;
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
                    activeUploadKind = kind;
                    const file = firstImageFileFromList(event.dataTransfer ? event.dataTransfer.files : []);
                    if (file) {
                        assignFile(file);
                    }
                });
            }

            if (removeCheckbox instanceof HTMLInputElement) {
                const applyRemoval = () => {
                    removeCheckbox.checked = true;
                    if (hiddenBase64 instanceof HTMLInputElement) {
                        hiddenBase64.value = '';
                    }
                    if (hiddenName instanceof HTMLInputElement) {
                        hiddenName.value = '';
                    }
                    if (input instanceof HTMLInputElement) {
                        input.value = '';
                    }
                    if (selectedFileLabel instanceof HTMLElement) {
                        selectedFileLabel.textContent = 'Nenhum arquivo selecionado.';
                    }
                    if (previewImage instanceof HTMLImageElement) {
                        previewImage.src = '';
                        previewImage.classList.add('is-hidden');
                    }
                    if (previewEmpty instanceof HTMLElement) {
                        previewEmpty.classList.remove('is-hidden');
                    }
                };

                if (removeButton instanceof HTMLButtonElement) {
                    removeButton.addEventListener('click', () => {
                        const label = kind === 'logo' ? 'logo' : 'banner';
                        if (!window.confirm('Remover ' + label + ' atual?')) {
                            return;
                        }
                        applyRemoval();
                    });
                }

                removeCheckbox.addEventListener('change', () => {
                    if (!removeCheckbox.checked) {
                        return;
                    }
                    applyRemoval();
                });
            }
        };

        setupBrandUpload('logo');
        setupBrandUpload('banner');

        document.addEventListener('paste', (event) => {
            const clipboard = event.clipboardData;
            if (!clipboard) {
                return;
            }
            const file = firstImageFileFromList(clipboard.files || []);
            if (!file) {
                return;
            }

            const assign = assigners[activeUploadKind];
            if (typeof assign === 'function') {
                assign(file);
            }
        });
    })();
    </script>
</section>
