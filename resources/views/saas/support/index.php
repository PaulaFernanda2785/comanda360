<?php
$supportPanel = is_array($supportPanel ?? null) ? $supportPanel : [];
$tickets = is_array($supportPanel['tickets'] ?? null) ? $supportPanel['tickets'] : [];
$threads = is_array($supportPanel['threads'] ?? null) ? $supportPanel['threads'] : [];
$filters = is_array($supportPanel['filters'] ?? null) ? $supportPanel['filters'] : [];
$pagination = is_array($supportPanel['pagination'] ?? null) ? $supportPanel['pagination'] : [];
$summary = is_array($supportPanel['summary'] ?? null) ? $supportPanel['summary'] : [];

$supportSearch = trim((string) ($filters['search'] ?? ''));
$supportCompanySearch = trim((string) ($filters['company_search'] ?? ''));
$supportStatus = trim((string) ($filters['status'] ?? ''));
$supportPriority = trim((string) ($filters['priority'] ?? ''));
$supportAssignment = trim((string) ($filters['assignment'] ?? ''));

$supportTotal = (int) ($summary['total'] ?? ($pagination['total'] ?? count($tickets)));
$supportOpenCount = (int) ($summary['open_count'] ?? 0);
$supportInProgressCount = (int) ($summary['in_progress_count'] ?? 0);
$supportResolvedCount = (int) ($summary['resolved_count'] ?? 0);
$supportUrgentCount = (int) ($summary['urgent_count'] ?? 0);
$supportAssignedCount = (int) ($summary['assigned_count'] ?? 0);
$lastSupportActivityAt = trim((string) ($summary['last_created_at'] ?? ''));

$supportPage = max(1, (int) ($pagination['page'] ?? 1));
$supportLastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$supportFrom = (int) ($pagination['from'] ?? 0);
$supportTo = (int) ($pagination['to'] ?? 0);
$supportPages = is_array($pagination['pages'] ?? null) ? $pagination['pages'] : [];

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$returnQuery = http_build_query($currentQuery);

$baseFilters = [
    'support_search' => $supportSearch,
    'support_company_search' => $supportCompanySearch,
    'support_status' => $supportStatus,
    'support_priority' => $supportPriority,
    'support_assignment' => $supportAssignment,
];

$buildSupportUrl = static function (array $overrides = []) use ($baseFilters): string {
    $params = array_merge($baseFilters, $overrides);
    foreach ($params as $key => $value) {
        if (trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/saas/support' . ($query !== '' ? '?' . $query : ''));
};

$supportStatusOptions = [
    '' => 'Todos os status',
    'open' => 'Aberto',
    'in_progress' => 'Em andamento',
    'resolved' => 'Resolvido',
    'closed' => 'Fechado',
];

$supportPriorityOptions = [
    '' => 'Todas as prioridades',
    'urgent' => 'Urgente',
    'high' => 'Alta',
    'medium' => 'Media',
    'low' => 'Baixa',
];

$supportAssignmentOptions = [
    '' => 'Com e sem responsavel',
    'assigned' => 'Somente atribuidos',
    'unassigned' => 'Somente sem responsavel',
];

$supportPriorityLabels = [
    'low' => 'Baixa',
    'medium' => 'Media',
    'high' => 'Alta',
    'urgent' => 'Urgente',
];

$supportStatusLabels = [
    'open' => 'Aberto',
    'in_progress' => 'Em andamento',
    'resolved' => 'Resolvido',
    'closed' => 'Fechado',
];

$formatSupportDate = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y H:i', $timestamp);
};
?>

<style>
    .saas-support-page{display:grid;gap:16px}
    .saas-support-hero{border:1px solid #bfdbfe;background:linear-gradient(118deg,var(--theme-main-card,#0f172a) 0%,#1e3a8a 58%,#0891b2 100%);color:#fff;border-radius:16px;padding:18px;position:relative;overflow:hidden}
    .saas-support-hero:before{content:"";position:absolute;top:-56px;right:-42px;width:220px;height:220px;border-radius:999px;background:rgba(255,255,255,.12)}
    .saas-support-hero:after{content:"";position:absolute;bottom:-78px;left:-36px;width:185px;height:185px;border-radius:999px;background:rgba(255,255,255,.1)}
    .saas-support-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .saas-support-hero h1{margin:0 0 8px;font-size:26px}
    .saas-support-hero p{margin:0;color:#dbeafe;max-width:860px;line-height:1.45}
    .saas-support-pills{display:flex;gap:8px;flex-wrap:wrap}
    .saas-support-pill{border:1px solid rgba(255,255,255,.26);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .saas-support-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.95fr);gap:16px;align-items:start}
    .saas-support-main,.saas-support-side{display:grid;gap:16px}

    .saas-support-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-support-head h2,.saas-support-head h3{margin:0}
    .saas-support-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.45}
    .saas-support-badges{display:flex;gap:6px;flex-wrap:wrap}

    .saas-support-filter-grid{display:grid;grid-template-columns:1.2fr 1.2fr 1fr 1fr 1fr auto;gap:10px;align-items:end}
    .saas-support-filter-grid .field{margin:0}
    .saas-support-filter-actions{display:flex;gap:8px;flex-wrap:wrap}

    .saas-support-list{display:grid;gap:12px}
    .saas-support-ticket{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);padding:14px;display:grid;gap:12px}
    .saas-support-ticket-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-support-ticket-title{display:grid;gap:4px}
    .saas-support-ticket-title strong{font-size:16px;color:#0f172a}
    .saas-support-ticket-title small{font-size:12px;color:#64748b;line-height:1.35}
    .saas-support-ticket-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .saas-support-ticket-box{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:10px}
    .saas-support-ticket-box span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .saas-support-ticket-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}

    .saas-thread{display:grid;gap:10px;border-top:1px dashed #cbd5e1;padding-top:12px;max-height:420px;overflow-y:auto;padding-right:6px}
    .saas-thread-item{display:grid;gap:7px;border-radius:12px;padding:10px 12px;max-width:92%}
    .saas-thread-item.is-company{background:#fff7ed;border:1px solid #fed7aa;justify-self:start}
    .saas-thread-item.is-saas{background:#eff6ff;border:1px solid #bfdbfe;justify-self:end}
    .saas-thread-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-thread-head strong{font-size:13px;color:#0f172a}
    .saas-thread-head small{font-size:11px;color:#64748b}
    .saas-thread-badge{display:inline-flex;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
    .saas-thread-badge.is-company{background:#ffedd5;color:#c2410c}
    .saas-thread-badge.is-saas{background:#dbeafe;color:#1d4ed8}
    .saas-thread-message{margin:0;font-size:13px;color:#1e293b;line-height:1.55}

    .saas-reply-box{display:grid;gap:10px;border-top:1px dashed #cbd5e1;padding-top:12px}
    .saas-reply-grid{display:grid;grid-template-columns:1fr 220px;gap:10px}
    .saas-reply-grid .field{margin:0}
    .saas-reply-box textarea{min-height:116px}
    .saas-reply-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .saas-reply-note{font-size:12px;color:#64748b;line-height:1.45;max-width:760px}
    .saas-conversation{border-top:1px dashed #cbd5e1;padding-top:12px}
    .saas-conversation summary{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;list-style:none;font-weight:700;color:#0f172a}
    .saas-conversation summary::-webkit-details-marker{display:none}
    .saas-conversation-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .saas-conversation-count{font-size:12px;color:#64748b;font-weight:600}
    .saas-conversation-toggle{font-size:11px;color:#1d4ed8;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:4px 9px;font-weight:700}
    .saas-conversation[open] .saas-conversation-toggle{background:#eff6ff}
    .saas-conversation-body{display:grid;gap:10px;margin-top:10px}
    .saas-thread::-webkit-scrollbar{width:10px}
    .saas-thread::-webkit-scrollbar-track{background:#e2e8f0;border-radius:999px}
    .saas-thread::-webkit-scrollbar-thumb{background:#94a3b8;border-radius:999px}
    .saas-thread::-webkit-scrollbar-thumb:hover{background:#64748b}

    .saas-summary-grid{display:grid;gap:8px}
    .saas-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
    .saas-summary-item strong{color:#0f172a}
    .saas-summary-item span{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}

    .saas-status-open{background:#dcfce7;color:#166534}
    .saas-status-progress{background:#dbeafe;color:#1d4ed8}
    .saas-status-resolved{background:#ede9fe;color:#5b21b6}
    .saas-status-closed{background:#e5e7eb;color:#374151}
    .saas-priority-urgent{background:#fee2e2;color:#991b1b}
    .saas-priority-high{background:#fef3c7;color:#92400e}
    .saas-priority-medium{background:#e0f2fe;color:#075985}
    .saas-priority-low{background:#ecfccb;color:#3f6212}

    .saas-support-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .saas-support-pagination-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .saas-page-btn{display:inline-block;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none}
    .saas-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .saas-page-ellipsis{color:#64748b;padding:0 2px}

    @media (max-width:1180px){
        .saas-support-grid{grid-template-columns:1fr}
    }
    @media (max-width:980px){
        .saas-support-filter-grid{grid-template-columns:1fr 1fr 1fr}
        .saas-support-ticket-info,.saas-reply-grid{grid-template-columns:1fr}
    }
    @media (max-width:700px){
        .saas-support-filter-grid{grid-template-columns:1fr}
        .saas-support-hero h1{font-size:22px}
    }
</style>

<div class="saas-support-page">
    <div class="saas-support-hero">
        <div class="saas-support-hero-body">
            <div>
                <h1>Atendimento SaaS</h1>
                <p>Fila institucional de chamados das empresas assinantes. Cada ticket funciona como uma thread unica: a empresa abre, o administrador responde, o historico permanece visivel dos dois lados e o status evolui dentro da mesma conversa.</p>
            </div>
            <div class="saas-support-pills">
                <span class="saas-support-pill">Chamados filtrados: <?= htmlspecialchars((string) $supportTotal) ?></span>
                <span class="saas-support-pill">Em aberto: <?= htmlspecialchars((string) $supportOpenCount) ?></span>
                <span class="saas-support-pill">Em andamento: <?= htmlspecialchars((string) $supportInProgressCount) ?></span>
                <span class="saas-support-pill">Urgentes: <?= htmlspecialchars((string) $supportUrgentCount) ?></span>
            </div>
        </div>
    </div>

    <div class="saas-support-grid">
        <div class="saas-support-main">
            <div class="card">
                <div class="saas-support-head">
                    <div>
                        <h2>Fila de chamados</h2>
                        <p class="saas-support-note">Filtre por empresa, texto, status, prioridade ou atribuicao. O retorno do SaaS entra no mesmo chamado da empresa e pode alterar o status no ato da resposta.</p>
                    </div>
                    <div class="saas-support-badges">
                        <?php if ($lastSupportActivityAt !== ''): ?>
                            <span class="badge">Ultima atividade: <?= htmlspecialchars($formatSupportDate($lastSupportActivityAt)) ?></span>
                        <?php endif; ?>
                        <span class="badge">Atribuidos: <?= htmlspecialchars((string) $supportAssignedCount) ?></span>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/support')) ?>">
                    <div class="saas-support-filter-grid">
                        <div class="field">
                            <label for="support_company_search">Empresa</label>
                            <input id="support_company_search" name="support_company_search" type="text" value="<?= htmlspecialchars($supportCompanySearch) ?>" placeholder="Nome, slug, email ou ID da empresa">
                        </div>
                        <div class="field">
                            <label for="support_search">Busca inteligente</label>
                            <input id="support_search" name="support_search" type="text" value="<?= htmlspecialchars($supportSearch) ?>" placeholder="ID do ticket, assunto, mensagem, autor ou responsavel">
                        </div>
                        <div class="field">
                            <label for="support_status">Status</label>
                            <select id="support_status" name="support_status">
                                <?php foreach ($supportStatusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $supportStatus === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="support_priority">Prioridade</label>
                            <select id="support_priority" name="support_priority">
                                <?php foreach ($supportPriorityOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $supportPriority === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="support_assignment">Atribuicao</label>
                            <select id="support_assignment" name="support_assignment">
                                <?php foreach ($supportAssignmentOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $supportAssignment === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="saas-support-filter-actions">
                            <button class="btn" type="submit">Aplicar</button>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/support')) ?>">Limpar</a>
                        </div>
                    </div>
                </form>

                <?php if ($tickets === []): ?>
                    <div class="card" style="margin-top:16px;padding:14px;border:1px dashed #cbd5e1;box-shadow:none">
                        <?= ($supportSearch !== '' || $supportCompanySearch !== '' || $supportStatus !== '' || $supportPriority !== '' || $supportAssignment !== '')
                            ? 'Nenhum chamado encontrado para os filtros aplicados.'
                            : 'Nenhum chamado registrado ate o momento.' ?>
                    </div>
                <?php else: ?>
                    <div class="saas-support-list" style="margin-top:16px">
                        <?php foreach ($tickets as $ticket): ?>
                            <?php
                            $ticketId = (int) ($ticket['id'] ?? 0);
                            $statusRaw = strtolower(trim((string) ($ticket['status'] ?? 'open')));
                            $priorityRaw = strtolower(trim((string) ($ticket['priority'] ?? 'medium')));
                            $threadMessages = is_array($threads[$ticketId] ?? null) ? $threads[$ticketId] : [];
                            $messageCount = count($threadMessages);
                            $statusClass = match ($statusRaw) {
                                'open' => 'saas-status-open',
                                'in_progress' => 'saas-status-progress',
                                'resolved' => 'saas-status-resolved',
                                'closed' => 'saas-status-closed',
                                default => '',
                            };
                            $priorityClass = match ($priorityRaw) {
                                'urgent' => 'saas-priority-urgent',
                                'high' => 'saas-priority-high',
                                'medium' => 'saas-priority-medium',
                                'low' => 'saas-priority-low',
                                default => '',
                            };
                            ?>
                            <article class="saas-support-ticket">
                                <div class="saas-support-ticket-top">
                                    <div class="saas-support-ticket-title">
                                        <strong>#<?= $ticketId ?> - <?= htmlspecialchars((string) ($ticket['subject'] ?? '-')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($ticket['company_name'] ?? 'Empresa')) ?> (<?= htmlspecialchars((string) ($ticket['company_slug'] ?? '-')) ?>) · aberto por <?= htmlspecialchars((string) ($ticket['opened_by_user_name'] ?? '-')) ?></small>
                                    </div>
                                    <div class="saas-support-badges">
                                        <span class="badge <?= htmlspecialchars($priorityClass) ?>"><?= htmlspecialchars($supportPriorityLabels[$priorityRaw] ?? ucfirst($priorityRaw)) ?></span>
                                        <span class="badge <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($supportStatusLabels[$statusRaw] ?? ucfirst($statusRaw)) ?></span>
                                    </div>
                                </div>

                                <div class="saas-support-ticket-info">
                                    <div class="saas-support-ticket-box">
                                        <span>Empresa</span>
                                        <strong><?= htmlspecialchars((string) ($ticket['company_name'] ?? '-')) ?></strong>
                                    </div>
                                    <div class="saas-support-ticket-box">
                                        <span>Responsavel SaaS</span>
                                        <strong><?= htmlspecialchars((string) ($ticket['assigned_to_user_name'] ?? 'Nao atribuido')) ?></strong>
                                    </div>
                                    <div class="saas-support-ticket-box">
                                        <span>Ultima atividade</span>
                                        <strong><?= htmlspecialchars($formatSupportDate($ticket['updated_at'] ?? $ticket['created_at'] ?? '')) ?></strong>
                                    </div>
                                    <div class="saas-support-ticket-box">
                                        <span>Fechamento</span>
                                        <strong><?= htmlspecialchars($formatSupportDate($ticket['closed_at'] ?? '')) ?></strong>
                                    </div>
                                </div>

                                <details class="saas-conversation">
                                    <summary>
                                        <span>Conversa do chamado</span>
                                        <span class="saas-conversation-meta">
                                            <span class="saas-conversation-count"><?= htmlspecialchars((string) $messageCount) ?> mensagem(ns)</span>
                                            <span class="saas-conversation-toggle">Expandir / recolher</span>
                                        </span>
                                    </summary>

                                    <div class="saas-conversation-body">
                                        <div class="saas-thread">
                                            <?php foreach ($threadMessages as $message): ?>
                                                <?php
                                                $senderContext = strtolower(trim((string) ($message['sender_context'] ?? 'company')));
                                                $isCompany = $senderContext !== 'saas';
                                                $senderName = trim((string) ($message['sender_user_name'] ?? ''));
                                                if ($senderName === '') {
                                                    $senderName = $isCompany ? 'Empresa' : 'Suporte SaaS';
                                                }
                                                ?>
                                                <div class="saas-thread-item<?= $isCompany ? ' is-company' : ' is-saas' ?>">
                                                    <div class="saas-thread-head">
                                                        <div>
                                                            <strong><?= htmlspecialchars($senderName) ?></strong>
                                                            <div class="saas-thread-badge<?= $isCompany ? ' is-company' : ' is-saas' ?>"><?= $isCompany ? 'Empresa' : 'SaaS' ?></div>
                                                        </div>
                                                        <small><?= htmlspecialchars($formatSupportDate($message['created_at'] ?? '')) ?></small>
                                                    </div>
                                                    <p class="saas-thread-message"><?= nl2br(htmlspecialchars((string) ($message['message'] ?? '')), false) ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <form class="saas-reply-box" method="POST" action="<?= htmlspecialchars(base_url('/saas/support/reply')) ?>">
                                            <?= form_security_fields('saas.support.reply.' . $ticketId) ?>
                                            <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                                            <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                            <div class="saas-reply-grid">
                                                <div class="field">
                                                    <label for="saas_reply_message_<?= $ticketId ?>">Responder na mesma thread</label>
                                                    <textarea id="saas_reply_message_<?= $ticketId ?>" name="message" rows="5" required placeholder="Escreva a resposta que ficara visivel no historico da empresa."></textarea>
                                                </div>
                                                <div class="field">
                                                    <label for="saas_reply_status_<?= $ticketId ?>">Novo status</label>
                                                    <select id="saas_reply_status_<?= $ticketId ?>" name="status">
                                                        <option value="in_progress" <?= $statusRaw === 'open' ? 'selected' : '' ?>>Em andamento</option>
                                                        <option value="open" <?= $statusRaw === 'open' ? '' : '' ?>>Aberto</option>
                                                        <option value="resolved" <?= $statusRaw === 'resolved' ? 'selected' : '' ?>>Resolvido</option>
                                                        <option value="closed" <?= $statusRaw === 'closed' ? 'selected' : '' ?>>Fechado</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="saas-reply-footer">
                                                <p class="saas-reply-note">A resposta fica registrada na mesma conversa do chamado e passa a aparecer tambem no ambiente da empresa. Ao responder, o chamado fica atribuido ao usuario SaaS atual.</p>
                                                <button class="btn" type="submit">Responder chamado</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($supportLastPage > 1): ?>
                        <div class="saas-support-pagination">
                            <div class="muted">Exibindo <?= htmlspecialchars((string) $supportFrom) ?> a <?= htmlspecialchars((string) $supportTo) ?> de <?= htmlspecialchars((string) $supportTotal) ?> chamados.</div>
                            <div class="saas-support-pagination-controls">
                                <?php if ($supportPage > 1): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildSupportUrl(['support_page' => $supportPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>
                                <?php
                                $previousPage = null;
                                foreach ($supportPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($previousPage !== null && $pageNumber - $previousPage > 1): ?>
                                        <span class="saas-page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a class="saas-page-btn<?= $pageNumber === $supportPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildSupportUrl(['support_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                    <?php
                                    $previousPage = $pageNumber;
                                endforeach;
                                ?>
                                <?php if ($supportPage < $supportLastPage): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildSupportUrl(['support_page' => $supportPage + 1])) ?>">Proxima</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <aside class="saas-support-side">
            <div class="card">
                <div class="saas-support-head">
                    <div>
                        <h3>Resumo de fila</h3>
                        <p class="saas-support-note">Panorama consolidado dos chamados no recorte filtrado.</p>
                    </div>
                </div>
                <div class="saas-summary-grid">
                    <div class="saas-summary-item"><strong>Total de chamados</strong><span><?= htmlspecialchars((string) $supportTotal) ?></span></div>
                    <div class="saas-summary-item"><strong>Em aberto</strong><span><?= htmlspecialchars((string) $supportOpenCount) ?></span></div>
                    <div class="saas-summary-item"><strong>Em andamento</strong><span><?= htmlspecialchars((string) $supportInProgressCount) ?></span></div>
                    <div class="saas-summary-item"><strong>Resolvidos/fechados</strong><span><?= htmlspecialchars((string) $supportResolvedCount) ?></span></div>
                    <div class="saas-summary-item"><strong>Atribuidos</strong><span><?= htmlspecialchars((string) $supportAssignedCount) ?></span></div>
                    <div class="saas-summary-item"><strong>Urgentes</strong><span><?= htmlspecialchars((string) $supportUrgentCount) ?></span></div>
                </div>
            </div>

            <div class="card">
                <div class="saas-support-head">
                    <div>
                        <h3>Regra operacional</h3>
                        <p class="saas-support-note">A resposta do SaaS deve sempre contextualizar causa, acao tomada e proximo passo. Fechamento sem retorno claro tende a gerar reabertura desnecessaria.</p>
                    </div>
                </div>
                <div class="saas-summary-grid">
                    <div class="saas-summary-item"><strong>Ao responder</strong><span>Assume o chamado</span></div>
                    <div class="saas-summary-item"><strong>Resolvido</strong><span>Problema tratado, aguardando validacao</span></div>
                    <div class="saas-summary-item"><strong>Fechado</strong><span>Ciclo concluido</span></div>
                    <div class="saas-summary-item"><strong>Reabertura</strong><span>Empresa responde na mesma thread</span></div>
                </div>
            </div>
        </aside>
    </div>
</div>
