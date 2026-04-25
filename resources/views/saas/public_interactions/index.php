<?php
$interactionPanel = is_array($interactionPanel ?? null) ? $interactionPanel : [];
$items = is_array($interactionPanel['items'] ?? null) ? $interactionPanel['items'] : [];
$filters = is_array($interactionPanel['filters'] ?? null) ? $interactionPanel['filters'] : [];
$pagination = is_array($interactionPanel['pagination'] ?? null) ? $interactionPanel['pagination'] : [];
$summary = is_array($interactionPanel['summary'] ?? null) ? $interactionPanel['summary'] : [];

$interactionSearch = trim((string) ($filters['search'] ?? ''));
$interactionStatus = trim((string) ($filters['status'] ?? ''));
$interactionPage = max(1, (int) ($pagination['page'] ?? 1));
$interactionLastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$interactionFrom = (int) ($pagination['from'] ?? 0);
$interactionTo = (int) ($pagination['to'] ?? 0);
$interactionTotal = (int) ($pagination['total'] ?? ($summary['total'] ?? count($items)));
$interactionPages = is_array($pagination['pages'] ?? null) ? $pagination['pages'] : [];
$lastCreatedAt = trim((string) ($summary['last_created_at'] ?? ''));

$statusOptions = [
    '' => 'Todos os status',
    'pending' => 'Pendentes',
    'published' => 'Publicadas',
    'rejected' => 'Rejeitadas',
];

$statusLabels = [
    'pending' => 'Pendente',
    'published' => 'Publicada',
    'rejected' => 'Rejeitada',
];

$statusBadgeClasses = [
    'pending' => 'status-pending',
    'published' => 'status-paid',
    'rejected' => 'status-canceled',
];

$formatDate = static function (mixed $value, bool $withTime = true): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
};

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$buildInteractionsUrl = static function (array $overrides = []) use ($currentQuery): string {
    $params = array_merge($currentQuery, $overrides);

    foreach (['interaction_search', 'interaction_status', 'interaction_page'] as $key) {
        if (array_key_exists($key, $params) && trim((string) $params[$key]) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/saas/public-interactions' . ($query !== '' ? '?' . $query : ''));
};

$returnQuery = http_build_query([
    'interaction_search' => $interactionSearch,
    'interaction_status' => $interactionStatus,
    'interaction_page' => $interactionPage,
]);
?>

<style>
    .public-interactions-page{display:grid;gap:16px}
    .public-interactions-hero{border:1px solid #bfdbfe;background:linear-gradient(120deg,var(--theme-main-card,#0f172a) 0%,#1d4ed8 48%,#0f766e 100%);color:#fff;border-radius:18px;padding:20px;position:relative;overflow:hidden}
    .public-interactions-hero:before{content:"";position:absolute;top:-58px;right:-34px;width:220px;height:220px;border-radius:999px;background:rgba(255,255,255,.12)}
    .public-interactions-hero:after{content:"";position:absolute;bottom:-86px;left:-42px;width:190px;height:190px;border-radius:999px;background:rgba(255,255,255,.08)}
    .public-interactions-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
    .public-interactions-hero-copy{max-width:900px}
    .public-interactions-hero h1{margin:0 0 8px;font-size:30px}
    .public-interactions-hero p{margin:0;color:#dbeafe;line-height:1.55}
    .public-interactions-pills{display:flex;gap:8px;flex-wrap:wrap}
    .public-interactions-pill{border:1px solid rgba(255,255,255,.24);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .public-interactions-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,.72fr);gap:16px;align-items:start}
    .public-interactions-main,.public-interactions-side{display:grid;gap:16px}

    .public-interactions-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .public-interactions-head h2,.public-interactions-head h3{margin:0}
    .public-interactions-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.5}
    .public-interactions-badges{display:flex;gap:8px;flex-wrap:wrap}
    .public-interactions-filter{
        display:grid;
        grid-template-columns:1.8fr 1fr auto;
        gap:10px;
        align-items:end;
        margin-bottom:20px;
        padding-bottom:4px;
    }
    .public-interactions-filter .field{margin:0 0 8px}
    .public-interactions-filter-actions{display:flex;gap:8px;flex-wrap:wrap}

    .public-interactions-list{display:grid;gap:14px}
    .public-interaction-card{border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(180deg,#fff,#f8fafc);padding:16px;display:grid;gap:14px}
    .public-interaction-card-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .public-interaction-title{display:grid;gap:4px}
    .public-interaction-title strong{font-size:17px;color:#0f172a}
    .public-interaction-title small{font-size:12px;color:#64748b;line-height:1.4}
    .public-interaction-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .public-interaction-meta-box{border:1px solid #e2e8f0;background:#fff;border-radius:12px;padding:12px}
    .public-interaction-meta-box span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
    .public-interaction-meta-box strong{display:block;margin-top:4px;color:#0f172a;font-size:13px;line-height:1.45;word-break:break-word}
    .public-interaction-details{border-top:1px dashed #cbd5e1;padding-top:12px}
    .public-interaction-details summary{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;list-style:none;font-weight:700;color:#0f172a}
    .public-interaction-details summary::-webkit-details-marker{display:none}
    .public-interaction-details-toggle{font-size:11px;color:#1d4ed8;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:4px 9px;font-weight:700}
    .public-interaction-details[open] .public-interaction-details-toggle{background:#eff6ff}
    .public-interaction-details-body{display:grid;gap:12px;margin-top:12px}
    .public-interaction-message{
        margin-top:12px;
        padding:16px;
        border-radius:14px;
        background:#fff;
        border:1px solid #e2e8f0;
        color:#334155;
        line-height:1.7;
        white-space:pre-wrap;
    }
    .public-interaction-edit{display:grid;gap:12px;padding-top:4px}
    .public-interaction-edit-grid{display:grid;grid-template-columns:1fr 1fr 220px;gap:10px}
    .public-interaction-edit-grid .field{margin:0}
    .public-interaction-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:space-between;align-items:center}
    .public-interaction-danger{display:flex;justify-content:flex-end}
    .public-interactions-empty{padding:18px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#475569}
    .public-interactions-summary{display:grid;gap:12px}
    .public-interactions-summary-item{display:grid;gap:4px;padding:14px;border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc)}
    .public-interactions-summary-item strong{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
    .public-interactions-summary-item span{font-size:26px;color:#0f172a;font-weight:800}
    .public-interactions-rule{padding:18px;border-radius:18px;background:linear-gradient(150deg,#091b2c 0%, #143753 100%);color:#eff7fb}
    .public-interactions-rule h3{margin:0 0 8px}
    .public-interactions-rule p{margin:0;color:rgba(239,247,251,.76);line-height:1.6}
    .public-interactions-rule ul{margin:14px 0 0;padding-left:18px;color:#dbeafe;display:grid;gap:8px}
    .saas-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:4px;padding-top:4px}
    .saas-pagination-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .saas-page-btn{display:inline-block;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none}
    .saas-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .saas-page-ellipsis{color:#64748b;padding:0 2px}

    @media (max-width:1160px){
        .public-interactions-layout{grid-template-columns:1fr}
        .public-interaction-meta{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:820px){
        .public-interactions-filter,.public-interaction-edit-grid{grid-template-columns:1fr}
    }
    @media (max-width:620px){
        .public-interaction-meta{grid-template-columns:1fr}
        .public-interactions-hero h1{font-size:24px}
        .public-interaction-actions{align-items:stretch}
        .public-interaction-actions .btn{width:100%}
    }
</style>

<div class="public-interactions-page">
    <section class="public-interactions-hero">
        <div class="public-interactions-hero-body">
            <div class="public-interactions-hero-copy">
                <h1>Interações públicas do MesiMenu</h1>
                <p>Este módulo concentra feedbacks, sugestões e mensagens enviadas pela página pública. O trabalho aqui é revisar o texto, proteger a reputação da marca e publicar apenas o que fortalece a narrativa comercial.</p>
            </div>
            <div class="public-interactions-pills">
                <span class="public-interactions-pill">Pendentes: <?= htmlspecialchars((string) ($summary['pending_count'] ?? 0)) ?></span>
                <span class="public-interactions-pill">Publicadas: <?= htmlspecialchars((string) ($summary['published_count'] ?? 0)) ?></span>
                <span class="public-interactions-pill">Rejeitadas: <?= htmlspecialchars((string) ($summary['rejected_count'] ?? 0)) ?></span>
            </div>
        </div>
    </section>

    <div class="public-interactions-layout">
        <main class="public-interactions-main">
            <section class="card">
                <div class="public-interactions-head">
                    <div>
                        <h2>Fila de moderação</h2>
                        <p class="public-interactions-note">Use a busca, revise o texto, ajuste o status e publique somente o que realmente ajuda a vender melhor o MesiMenu.</p>
                    </div>
                    <div class="public-interactions-badges">
                        <span class="badge">10 por página</span>
                        <span class="badge">Total filtrado: <?= htmlspecialchars((string) $interactionTotal) ?></span>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/public-interactions')) ?>" class="public-interactions-filter">
                    <div class="field">
                        <label for="interaction_search">Busca</label>
                        <input id="interaction_search" name="interaction_search" type="text" value="<?= htmlspecialchars($interactionSearch) ?>" placeholder="ID, nome, e-mail ou trecho da mensagem">
                    </div>
                    <div class="field">
                        <label for="interaction_status">Status</label>
                        <select id="interaction_status" name="interaction_status">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $interactionStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="public-interactions-filter-actions">
                        <input type="hidden" name="interaction_page" value="1">
                        <button class="btn" type="submit">Aplicar</button>
                        <a class="btn secondary" href="<?= htmlspecialchars($buildInteractionsUrl([
                            'interaction_search' => '',
                            'interaction_status' => '',
                            'interaction_page' => '',
                        ])) ?>">Limpar</a>
                    </div>
                </form>

                <div class="public-interactions-list">
                    <?php if ($items === []): ?>
                        <div class="public-interactions-empty">Nenhuma interação encontrada para os filtros aplicados.</div>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <?php
                        if (!is_array($item)) {
                            continue;
                        }

                        $interactionId = (int) ($item['id'] ?? 0);
                        $status = strtolower(trim((string) ($item['status'] ?? 'pending')));
                        $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                        $statusBadgeClass = $statusBadgeClasses[$status] ?? 'status-default';
                        $sourcePage = trim((string) ($item['source_page'] ?? ''));
                        ?>
                        <article class="public-interaction-card">
                            <div class="public-interaction-card-top">
                                <div class="public-interaction-title">
                                    <strong>#<?= $interactionId ?> - <?= htmlspecialchars((string) ($item['visitor_name'] ?? 'Visitante')) ?></strong>
                                    <small><?= htmlspecialchars((string) ($item['visitor_email'] ?? '-')) ?></small>
                                </div>
                                <span class="badge <?= htmlspecialchars($statusBadgeClass) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                            </div>

                            <div class="public-interaction-meta">
                                <div class="public-interaction-meta-box">
                                    <span>Enviada em</span>
                                    <strong><?= htmlspecialchars($formatDate($item['created_at'] ?? null)) ?></strong>
                                </div>
                                <div class="public-interaction-meta-box">
                                    <span>Publicada em</span>
                                    <strong><?= htmlspecialchars($formatDate($item['published_at'] ?? null)) ?></strong>
                                </div>
                                <div class="public-interaction-meta-box">
                                    <span>Revisada por</span>
                                    <strong><?= htmlspecialchars((string) ($item['reviewed_by_user_name'] ?? '-')) ?></strong>
                                </div>
                                <div class="public-interaction-meta-box">
                                    <span>Origem</span>
                                    <strong title="<?= htmlspecialchars($sourcePage !== '' ? $sourcePage : 'Origem não informada') ?>"><?= htmlspecialchars($sourcePage !== '' ? $sourcePage : 'Não informada') ?></strong>
                                </div>
                            </div>

                            <details class="public-interaction-details">
                                <summary>
                                    <span>Ler mensagem completa</span>
                                    <span class="public-interaction-details-toggle">Expandir / recolher</span>
                                </summary>

                                <div class="public-interaction-details-body">
                                    <div class="public-interaction-message"><?= htmlspecialchars((string) ($item['message'] ?? '')) ?></div>

                                    <form method="POST" action="<?= htmlspecialchars(base_url('/saas/public-interactions/update')) ?>" class="public-interaction-edit">
                                        <?= form_security_fields('saas.public_interactions.update.' . $interactionId) ?>
                                        <input type="hidden" name="interaction_id" value="<?= $interactionId ?>">
                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                        <div class="public-interaction-edit-grid">
                                            <div class="field">
                                                <label for="visitor_name_<?= $interactionId ?>">Nome</label>
                                                <input id="visitor_name_<?= $interactionId ?>" name="visitor_name" type="text" value="<?= htmlspecialchars((string) ($item['visitor_name'] ?? '')) ?>" required>
                                            </div>
                                            <div class="field">
                                                <label for="visitor_email_<?= $interactionId ?>">E-mail</label>
                                                <input id="visitor_email_<?= $interactionId ?>" name="visitor_email" type="email" value="<?= htmlspecialchars((string) ($item['visitor_email'] ?? '')) ?>" required>
                                            </div>
                                            <div class="field">
                                                <label for="status_<?= $interactionId ?>">Status</label>
                                                <select id="status_<?= $interactionId ?>" name="status">
                                                    <?php foreach ($statusOptions as $value => $label): ?>
                                                        <?php if ($value === '') { continue; } ?>
                                                        <option value="<?= htmlspecialchars($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label for="message_<?= $interactionId ?>">Mensagem</label>
                                            <textarea id="message_<?= $interactionId ?>" name="message" rows="6" required><?= htmlspecialchars((string) ($item['message'] ?? '')) ?></textarea>
                                        </div>

                                        <div class="public-interaction-actions">
                                            <button class="btn" type="submit">Salvar moderação</button>
                                        </div>
                                    </form>

                                    <div class="public-interaction-danger">
                                        <form method="POST" action="<?= htmlspecialchars(base_url('/saas/public-interactions/delete')) ?>" onsubmit="return confirm('Excluir esta interação pública? Esta ação não pode ser desfeita.');">
                                            <?= form_security_fields('saas.public_interactions.delete.' . $interactionId) ?>
                                            <input type="hidden" name="interaction_id" value="<?= $interactionId ?>">
                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                            <button class="btn secondary" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($interactionTotal > 0): ?>
                    <div class="saas-pagination">
                        <div class="public-interactions-note">
                            Exibindo <?= htmlspecialchars((string) $interactionFrom) ?> a <?= htmlspecialchars((string) $interactionTo) ?> de <?= htmlspecialchars((string) $interactionTotal) ?> interações filtradas.
                        </div>
                        <?php if ($interactionLastPage > 1): ?>
                            <div class="saas-pagination-controls">
                                <?php if ($interactionPage > 1): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildInteractionsUrl(['interaction_page' => $interactionPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>

                                <?php
                                $lastRenderedPage = 0;
                                foreach ($interactionPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($lastRenderedPage > 0 && $pageNumber - $lastRenderedPage > 1): ?>
                                        <span class="saas-page-ellipsis">...</span>
                                    <?php endif; ?>

                                    <a class="saas-page-btn<?= $pageNumber === $interactionPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildInteractionsUrl(['interaction_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>

                                    <?php $lastRenderedPage = $pageNumber; ?>
                                <?php endforeach; ?>

                                <?php if ($interactionPage < $interactionLastPage): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildInteractionsUrl(['interaction_page' => $interactionPage + 1])) ?>">Próxima</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <aside class="public-interactions-side">
            <section class="card">
                <div class="public-interactions-head">
                    <div>
                        <h3>Resumo de fila</h3>
                        <p class="public-interactions-note">Esses números ajudam a perceber se a vitrine pública está evoluindo com prova social ou acumulando ruído não tratado.</p>
                    </div>
                </div>

                <div class="public-interactions-summary">
                    <div class="public-interactions-summary-item">
                        <strong>Total filtrado</strong>
                        <span><?= htmlspecialchars((string) ($summary['total'] ?? 0)) ?></span>
                    </div>
                    <div class="public-interactions-summary-item">
                        <strong>Pendentes</strong>
                        <span><?= htmlspecialchars((string) ($summary['pending_count'] ?? 0)) ?></span>
                    </div>
                    <div class="public-interactions-summary-item">
                        <strong>Publicadas</strong>
                        <span><?= htmlspecialchars((string) ($summary['published_count'] ?? 0)) ?></span>
                    </div>
                    <div class="public-interactions-summary-item">
                        <strong>Último envio</strong>
                        <span style="font-size:18px"><?= htmlspecialchars($formatDate($lastCreatedAt ?: null)) ?></span>
                    </div>
                </div>
            </section>

            <section class="public-interactions-rule">
                <h3>Diretriz de moderação</h3>
                <p>Publicar tudo sem curadoria enfraquece a marca. O filtro correto aqui é publicar o que traz credibilidade, leitura de valor e sinal real de uso ou interesse.</p>
                <ul>
                    <li>Mantenha pendente o que ainda precisa de contexto ou ajuste editorial.</li>
                    <li>Publique somente mensagens legíveis, coerentes e seguras para a página pública.</li>
                    <li>Rejeite o que compromete a imagem, expõe dados ou não agrega valor comercial.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
