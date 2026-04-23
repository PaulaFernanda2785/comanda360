<?php
$contactPanel = is_array($contactPanel ?? null) ? $contactPanel : [];
$items = is_array($contactPanel['items'] ?? null) ? $contactPanel['items'] : [];
$filters = is_array($contactPanel['filters'] ?? null) ? $contactPanel['filters'] : [];
$pagination = is_array($contactPanel['pagination'] ?? null) ? $contactPanel['pagination'] : [];
$summary = is_array($contactPanel['summary'] ?? null) ? $contactPanel['summary'] : [];

$contactSearch = trim((string) ($filters['search'] ?? ''));
$contactStatus = trim((string) ($filters['status'] ?? ''));
$contactChannel = trim((string) ($filters['response_channel'] ?? ''));
$contactPage = max(1, (int) ($pagination['page'] ?? 1));
$contactLastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$contactFrom = (int) ($pagination['from'] ?? 0);
$contactTo = (int) ($pagination['to'] ?? 0);
$contactTotal = (int) ($pagination['total'] ?? ($summary['total'] ?? count($items)));
$contactPages = is_array($pagination['pages'] ?? null) ? $pagination['pages'] : [];
$lastCreatedAt = trim((string) ($summary['last_created_at'] ?? ''));

$statusOptions = [
    '' => 'Todos os status',
    'new' => 'Novos',
    'contacted' => 'Contatados',
    'qualified' => 'Qualificados',
    'converted' => 'Convertidos',
    'archived' => 'Arquivados',
];

$statusLabels = [
    'new' => 'Novo',
    'contacted' => 'Contatado',
    'qualified' => 'Qualificado',
    'converted' => 'Convertido',
    'archived' => 'Arquivado',
];

$statusBadgeClasses = [
    'new' => 'status-pending',
    'contacted' => 'status-trial',
    'qualified' => 'status-active',
    'converted' => 'status-paid',
    'archived' => 'status-canceled',
];

$channelOptions = [
    '' => 'Todos os canais',
    'email' => 'E-mail',
    'phone' => 'Telefone',
    'whatsapp' => 'WhatsApp',
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

$normalizePhoneDigits = static function (mixed $value): string {
    $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));
    return is_string($digits) ? $digits : '';
};

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$buildContactsUrl = static function (array $overrides = []) use ($currentQuery): string {
    $params = array_merge($currentQuery, $overrides);

    foreach (['contact_search', 'contact_status', 'contact_channel', 'contact_page'] as $key) {
        if (array_key_exists($key, $params) && trim((string) $params[$key]) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/saas/public-contacts' . ($query !== '' ? '?' . $query : ''));
};

$returnQuery = http_build_query([
    'contact_search' => $contactSearch,
    'contact_status' => $contactStatus,
    'contact_channel' => $contactChannel,
    'contact_page' => $contactPage,
]);
?>

<style>
    .public-contacts-page{display:grid;gap:16px}
    .public-contacts-hero{border:1px solid #bfdbfe;background:linear-gradient(120deg,var(--theme-main-card,#0f172a) 0%,#14532d 44%,#0f766e 100%);color:#fff;border-radius:18px;padding:20px;position:relative;overflow:hidden}
    .public-contacts-hero:before{content:"";position:absolute;top:-58px;right:-34px;width:220px;height:220px;border-radius:999px;background:rgba(255,255,255,.12)}
    .public-contacts-hero:after{content:"";position:absolute;bottom:-86px;left:-42px;width:190px;height:190px;border-radius:999px;background:rgba(255,255,255,.08)}
    .public-contacts-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
    .public-contacts-hero-copy{max-width:900px}
    .public-contacts-hero h1{margin:0 0 8px;font-size:30px}
    .public-contacts-hero p{margin:0;color:#dcfce7;line-height:1.55}
    .public-contacts-pills{display:flex;gap:8px;flex-wrap:wrap}
    .public-contacts-pill{border:1px solid rgba(255,255,255,.24);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .public-contacts-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(290px,.72fr);gap:16px;align-items:start}
    .public-contacts-main,.public-contacts-side{display:grid;gap:16px}
    .public-contacts-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .public-contacts-head h2,.public-contacts-head h3{margin:0}
    .public-contacts-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.5}
    .public-contacts-badges{display:flex;gap:8px;flex-wrap:wrap}
    .public-contacts-filter{display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:10px;align-items:end;margin-bottom:20px;padding-bottom:4px}
    .public-contacts-filter .field{margin:0 0 8px}
    .public-contacts-filter-actions{display:flex;gap:8px;flex-wrap:wrap}

    .public-contacts-list{display:grid;gap:14px}
    .public-contact-card{border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(180deg,#fff,#f8fafc);padding:16px;display:grid;gap:14px}
    .public-contact-card-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .public-contact-title{display:grid;gap:4px}
    .public-contact-title strong{font-size:17px;color:#0f172a}
    .public-contact-title small{font-size:12px;color:#64748b;line-height:1.4}
    .public-contact-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .public-contact-meta-box{border:1px solid #e2e8f0;background:#fff;border-radius:12px;padding:12px}
    .public-contact-meta-box span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
    .public-contact-meta-box strong{display:block;margin-top:4px;color:#0f172a;font-size:13px;line-height:1.45;word-break:break-word}
    .public-contact-quick-actions{display:flex;gap:8px;flex-wrap:wrap}
    .public-contact-link{display:inline-flex;align-items:center;justify-content:center;padding:9px 12px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;text-decoration:none;font-weight:700}
    .public-contact-link:hover{text-decoration:none;border-color:#93c5fd;background:#eff6ff}
    .public-contact-details{border-top:1px dashed #cbd5e1;padding-top:12px}
    .public-contact-details summary{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;list-style:none;font-weight:700;color:#0f172a}
    .public-contact-details summary::-webkit-details-marker{display:none}
    .public-contact-details-toggle{font-size:11px;color:#1d4ed8;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:4px 9px;font-weight:700}
    .public-contact-details[open] .public-contact-details-toggle{background:#eff6ff}
    .public-contact-details-body{display:grid;gap:12px;margin-top:12px}
    .public-contact-message{margin-top:12px;padding:16px;border-radius:14px;background:#fff;border:1px solid #e2e8f0;color:#334155;line-height:1.7;white-space:pre-wrap}
    .public-contact-utm-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .public-contact-edit{display:grid;gap:12px;padding-top:4px}
    .public-contact-edit-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
    .public-contact-edit-grid .field{margin:0}
    .public-contact-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:space-between;align-items:center}
    .public-contact-danger{display:flex;justify-content:flex-end}
    .public-contacts-empty{padding:18px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#475569}
    .public-contacts-summary{display:grid;gap:12px}
    .public-contacts-summary-item{display:grid;gap:4px;padding:14px;border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc)}
    .public-contacts-summary-item strong{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
    .public-contacts-summary-item span{font-size:26px;color:#0f172a;font-weight:800}
    .public-contacts-rule{padding:18px;border-radius:18px;background:linear-gradient(150deg,#052e2b 0%, #164e63 100%);color:#eff7fb}
    .public-contacts-rule h3{margin:0 0 8px}
    .public-contacts-rule p{margin:0;color:rgba(239,247,251,.76);line-height:1.6}
    .public-contacts-rule ul{margin:14px 0 0;padding-left:18px;color:#d1fae5;display:grid;gap:8px}

    @media (max-width:1180px){
        .public-contacts-layout{grid-template-columns:1fr}
        .public-contact-meta{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:860px){
        .public-contacts-filter,.public-contact-edit-grid,.public-contact-utm-grid{grid-template-columns:1fr}
    }
    @media (max-width:620px){
        .public-contact-meta{grid-template-columns:1fr}
        .public-contacts-hero h1{font-size:24px}
        .public-contact-actions{align-items:stretch}
        .public-contact-actions .btn{width:100%}
    }
</style>

<div class="public-contacts-page">
    <section class="public-contacts-hero">
        <div class="public-contacts-hero-body">
            <div class="public-contacts-hero-copy">
                <h1>Contatos comerciais da Comanda360</h1>
                <p>Esta fila concentra mensagens de visitantes e futuros clientes que querem falar com o comercial. O objetivo aqui nao e apenas armazenar lead, e sim responder com velocidade, contexto e leitura de conversao.</p>
            </div>
            <div class="public-contacts-pills">
                <span class="public-contacts-pill">Novos: <?= htmlspecialchars((string) ($summary['new_count'] ?? 0)) ?></span>
                <span class="public-contacts-pill">Qualificados: <?= htmlspecialchars((string) ($summary['qualified_count'] ?? 0)) ?></span>
                <span class="public-contacts-pill">Convertidos: <?= htmlspecialchars((string) ($summary['converted_count'] ?? 0)) ?></span>
            </div>
        </div>
    </section>

    <div class="public-contacts-layout">
        <main class="public-contacts-main">
            <section class="card">
                <div class="public-contacts-head">
                    <div>
                        <h2>Fila comercial</h2>
                        <p class="public-contacts-note">Use a busca para localizar o lead, registre o canal de retorno e avance o status conforme o contato comercial evolui.</p>
                    </div>
                    <div class="public-contacts-badges">
                        <span class="badge">10 por pagina</span>
                        <span class="badge">Total filtrado: <?= htmlspecialchars((string) $contactTotal) ?></span>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/public-contacts')) ?>" class="public-contacts-filter">
                    <div class="field">
                        <label for="contact_search">Busca</label>
                        <input id="contact_search" name="contact_search" type="text" value="<?= htmlspecialchars($contactSearch) ?>" placeholder="ID, nome, empresa, email, telefone ou mensagem">
                    </div>
                    <div class="field">
                        <label for="contact_status">Status</label>
                        <select id="contact_status" name="contact_status">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $contactStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="contact_channel">Canal</label>
                        <select id="contact_channel" name="contact_channel">
                            <?php foreach ($channelOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $contactChannel === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="public-contacts-filter-actions">
                        <input type="hidden" name="contact_page" value="1">
                        <button class="btn" type="submit">Aplicar</button>
                        <a class="btn secondary" href="<?= htmlspecialchars($buildContactsUrl([
                            'contact_search' => '',
                            'contact_status' => '',
                            'contact_channel' => '',
                            'contact_page' => '',
                        ])) ?>">Limpar</a>
                    </div>
                </form>

                <div class="public-contacts-list">
                    <?php if ($items === []): ?>
                        <div class="public-contacts-empty">Nenhum contato comercial encontrado para os filtros aplicados.</div>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <?php
                        if (!is_array($item)) {
                            continue;
                        }

                        $contactId = (int) ($item['id'] ?? 0);
                        $status = strtolower(trim((string) ($item['status'] ?? 'new')));
                        $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                        $statusBadgeClass = $statusBadgeClasses[$status] ?? 'status-default';
                        $phoneDigits = $normalizePhoneDigits($item['phone'] ?? '');
                        $email = trim((string) ($item['contact_email'] ?? ''));
                        $phone = trim((string) ($item['phone'] ?? ''));
                        $companyName = trim((string) ($item['company_name'] ?? ''));
                        $sourcePage = trim((string) ($item['source_page'] ?? ''));
                        $planInterest = trim((string) ($item['plan_interest'] ?? ''));
                        ?>
                        <article class="public-contact-card">
                            <div class="public-contact-card-top">
                                <div class="public-contact-title">
                                    <strong>#<?= $contactId ?> - <?= htmlspecialchars((string) ($item['contact_name'] ?? 'Contato')) ?></strong>
                                    <small><?= htmlspecialchars($companyName !== '' ? $companyName : 'Empresa nao informada') ?> · <?= htmlspecialchars($email !== '' ? $email : '-') ?></small>
                                </div>
                                <span class="badge <?= htmlspecialchars($statusBadgeClass) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                            </div>

                            <div class="public-contact-meta">
                                <div class="public-contact-meta-box">
                                    <span>Enviado em</span>
                                    <strong><?= htmlspecialchars($formatDate($item['created_at'] ?? null)) ?></strong>
                                </div>
                                <div class="public-contact-meta-box">
                                    <span>Telefone</span>
                                    <strong><?= htmlspecialchars($phone !== '' ? $phone : '-') ?></strong>
                                </div>
                                <div class="public-contact-meta-box">
                                    <span>Plano de interesse</span>
                                    <strong><?= htmlspecialchars($planInterest !== '' ? $planInterest : 'Nao definido') ?></strong>
                                </div>
                                <div class="public-contact-meta-box">
                                    <span>Ultimo retorno</span>
                                    <strong><?= htmlspecialchars($formatDate($item['responded_at'] ?? null)) ?></strong>
                                </div>
                            </div>

                            <div class="public-contact-quick-actions">
                                <?php if ($email !== ''): ?>
                                    <a class="public-contact-link" href="mailto:<?= htmlspecialchars($email) ?>">Responder por e-mail</a>
                                <?php endif; ?>
                                <?php if ($phoneDigits !== ''): ?>
                                    <a class="public-contact-link" href="tel:<?= htmlspecialchars($phoneDigits) ?>">Ligar</a>
                                    <a class="public-contact-link" href="https://wa.me/<?= htmlspecialchars($phoneDigits) ?>" target="_blank" rel="noreferrer">Abrir WhatsApp</a>
                                <?php endif; ?>
                            </div>

                            <details class="public-contact-details">
                                <summary>
                                    <span>Abrir lead comercial</span>
                                    <span class="public-contact-details-toggle">Expandir / recolher</span>
                                </summary>

                                <div class="public-contact-details-body">
                                    <div class="public-contact-message"><?= htmlspecialchars((string) ($item['message'] ?? '')) ?></div>

                                    <div class="public-contact-utm-grid">
                                        <div class="public-contact-meta-box">
                                            <span>Origem</span>
                                            <strong title="<?= htmlspecialchars($sourcePage !== '' ? $sourcePage : 'Origem nao informada') ?>"><?= htmlspecialchars($sourcePage !== '' ? $sourcePage : 'Nao informada') ?></strong>
                                        </div>
                                        <div class="public-contact-meta-box">
                                            <span>UTM</span>
                                            <strong><?= htmlspecialchars(trim((string) ($item['utm_source'] ?? '')) !== '' ? (string) ($item['utm_source'] ?? '') : 'Sem rastreio') ?></strong>
                                        </div>
                                        <div class="public-contact-meta-box">
                                            <span>Responsavel</span>
                                            <strong><?= htmlspecialchars((string) ($item['responded_by_user_name'] ?? '-')) ?></strong>
                                        </div>
                                    </div>

                                    <form method="POST" action="<?= htmlspecialchars(base_url('/saas/public-contacts/update')) ?>" class="public-contact-edit">
                                        <?= form_security_fields('saas.public_contacts.update.' . $contactId) ?>
                                        <input type="hidden" name="contact_id" value="<?= $contactId ?>">
                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                        <div class="public-contact-edit-grid">
                                            <div class="field">
                                                <label for="contact_name_<?= $contactId ?>">Nome</label>
                                                <input id="contact_name_<?= $contactId ?>" name="contact_name" type="text" value="<?= htmlspecialchars((string) ($item['contact_name'] ?? '')) ?>" required>
                                            </div>
                                            <div class="field">
                                                <label for="contact_email_<?= $contactId ?>">E-mail</label>
                                                <input id="contact_email_<?= $contactId ?>" name="contact_email" type="email" value="<?= htmlspecialchars($email) ?>" required>
                                            </div>
                                            <div class="field">
                                                <label for="phone_<?= $contactId ?>">Telefone / WhatsApp</label>
                                                <input id="phone_<?= $contactId ?>" name="phone" type="text" value="<?= htmlspecialchars($phone) ?>" required>
                                            </div>
                                            <div class="field">
                                                <label for="company_name_<?= $contactId ?>">Empresa</label>
                                                <input id="company_name_<?= $contactId ?>" name="company_name" type="text" value="<?= htmlspecialchars($companyName) ?>">
                                            </div>
                                            <div class="field">
                                                <label for="plan_interest_<?= $contactId ?>">Plano de interesse</label>
                                                <input id="plan_interest_<?= $contactId ?>" name="plan_interest" type="text" value="<?= htmlspecialchars($planInterest) ?>">
                                            </div>
                                            <div class="field">
                                                <label for="billing_cycle_interest_<?= $contactId ?>">Ciclo</label>
                                                <select id="billing_cycle_interest_<?= $contactId ?>" name="billing_cycle_interest">
                                                    <option value="">Definir depois</option>
                                                    <option value="mensal" <?= trim((string) ($item['billing_cycle_interest'] ?? '')) === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                                                    <option value="anual" <?= trim((string) ($item['billing_cycle_interest'] ?? '')) === 'anual' ? 'selected' : '' ?>>Anual</option>
                                                </select>
                                            </div>
                                            <div class="field">
                                                <label for="status_<?= $contactId ?>">Status</label>
                                                <select id="status_<?= $contactId ?>" name="status">
                                                    <?php foreach ($statusOptions as $value => $label): ?>
                                                        <?php if ($value === '') { continue; } ?>
                                                        <option value="<?= htmlspecialchars($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="field">
                                                <label for="response_channel_<?= $contactId ?>">Canal de retorno</label>
                                                <select id="response_channel_<?= $contactId ?>" name="response_channel">
                                                    <option value="">Selecionar depois</option>
                                                    <?php foreach ($channelOptions as $value => $label): ?>
                                                        <?php if ($value === '') { continue; } ?>
                                                        <option value="<?= htmlspecialchars($value) ?>" <?= trim((string) ($item['response_channel'] ?? '')) === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label for="message_<?= $contactId ?>">Mensagem</label>
                                            <textarea id="message_<?= $contactId ?>" name="message" rows="6" required><?= htmlspecialchars((string) ($item['message'] ?? '')) ?></textarea>
                                        </div>

                                        <div class="field">
                                            <label for="response_notes_<?= $contactId ?>">Observacoes comerciais</label>
                                            <textarea id="response_notes_<?= $contactId ?>" name="response_notes" rows="4" placeholder="Registre tentativa de contato, resposta do lead, canal usado e proximos passos."><?= htmlspecialchars((string) ($item['response_notes'] ?? '')) ?></textarea>
                                        </div>

                                        <div class="public-contact-actions">
                                            <button class="btn" type="submit">Salvar acompanhamento</button>
                                        </div>
                                    </form>

                                    <div class="public-contact-danger">
                                        <form method="POST" action="<?= htmlspecialchars(base_url('/saas/public-contacts/delete')) ?>" onsubmit="return confirm('Excluir este contato comercial? Esta acao nao pode ser desfeita.');">
                                            <?= form_security_fields('saas.public_contacts.delete.' . $contactId) ?>
                                            <input type="hidden" name="contact_id" value="<?= $contactId ?>">
                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                            <button class="btn secondary" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($contactTotal > 0): ?>
                    <div class="saas-pagination">
                        <div class="public-contacts-note">
                            Exibindo <?= htmlspecialchars((string) $contactFrom) ?> a <?= htmlspecialchars((string) $contactTo) ?> de <?= htmlspecialchars((string) $contactTotal) ?> contatos filtrados.
                        </div>
                        <?php if ($contactLastPage > 1): ?>
                            <div class="saas-pagination-controls">
                                <?php if ($contactPage > 1): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildContactsUrl(['contact_page' => $contactPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>

                                <?php
                                $lastRenderedPage = 0;
                                foreach ($contactPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($lastRenderedPage > 0 && $pageNumber - $lastRenderedPage > 1): ?>
                                        <span class="saas-page-ellipsis">...</span>
                                    <?php endif; ?>

                                    <a class="saas-page-btn<?= $pageNumber === $contactPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildContactsUrl(['contact_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>

                                    <?php $lastRenderedPage = $pageNumber; ?>
                                <?php endforeach; ?>

                                <?php if ($contactPage < $contactLastPage): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildContactsUrl(['contact_page' => $contactPage + 1])) ?>">Proxima</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <aside class="public-contacts-side">
            <section class="card">
                <div class="public-contacts-head">
                    <div>
                        <h3>Resumo comercial</h3>
                        <p class="public-contacts-note">Esta leitura ajuda a separar volume de interesse real, retorno em andamento e oportunidades que ja avancaram.</p>
                    </div>
                </div>

                <div class="public-contacts-summary">
                    <div class="public-contacts-summary-item">
                        <strong>Total filtrado</strong>
                        <span><?= htmlspecialchars((string) ($summary['total'] ?? 0)) ?></span>
                    </div>
                    <div class="public-contacts-summary-item">
                        <strong>Novos</strong>
                        <span><?= htmlspecialchars((string) ($summary['new_count'] ?? 0)) ?></span>
                    </div>
                    <div class="public-contacts-summary-item">
                        <strong>Contatados</strong>
                        <span><?= htmlspecialchars((string) ($summary['contacted_count'] ?? 0)) ?></span>
                    </div>
                    <div class="public-contacts-summary-item">
                        <strong>Ultimo envio</strong>
                        <span style="font-size:18px"><?= htmlspecialchars($formatDate($lastCreatedAt ?: null)) ?></span>
                    </div>
                </div>
            </section>

            <section class="public-contacts-rule">
                <h3>Leitura recomendada</h3>
                <p>Contato comercial sem resposta rapida perde timing. O melhor uso desta fila e registrar retorno, qualificar o lead e deixar claro o proximo passo da negociacao.</p>
                <ul>
                    <li>Use o status novo para o que ainda nao recebeu abordagem comercial.</li>
                    <li>Avance para contatado ou qualificado assim que houver retorno por e-mail, telefone ou WhatsApp.</li>
                    <li>Marque como convertido quando a conversa virar oportunidade real ou assinatura.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
