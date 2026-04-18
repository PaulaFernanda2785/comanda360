<?php
$supportTickets = is_array($supportTickets ?? null) ? $supportTickets : [];
$supportThreads = is_array($supportThreads ?? null) ? $supportThreads : [];
$supportFilters = is_array($supportFilters ?? null) ? $supportFilters : [];
$supportPagination = is_array($supportPagination ?? null) ? $supportPagination : [];
$supportSummary = is_array($supportSummary ?? null) ? $supportSummary : [];

$supportSearch = trim((string) ($supportFilters['search'] ?? ''));
$supportStatus = trim((string) ($supportFilters['status'] ?? ''));
$supportPriority = trim((string) ($supportFilters['priority'] ?? ''));
$supportAssignment = trim((string) ($supportFilters['assignment'] ?? ''));

$supportTotal = (int) ($supportSummary['total'] ?? ($supportPagination['total'] ?? count($supportTickets)));
$supportOpenCount = (int) ($supportSummary['open_count'] ?? 0);
$supportInProgressCount = (int) ($supportSummary['in_progress_count'] ?? 0);
$supportResolvedCount = (int) ($supportSummary['resolved_count'] ?? 0);
$supportUrgentCount = (int) ($supportSummary['urgent_count'] ?? 0);
$supportAssignedCount = (int) ($supportSummary['assigned_count'] ?? 0);
$lastSupportOpenedAt = trim((string) ($supportSummary['last_created_at'] ?? ''));

$supportPage = max(1, (int) ($supportPagination['page'] ?? 1));
$supportLastPage = max(1, (int) ($supportPagination['last_page'] ?? 1));
$supportFrom = (int) ($supportPagination['from'] ?? 0);
$supportTo = (int) ($supportPagination['to'] ?? 0);
$supportPages = is_array($supportPagination['pages'] ?? null) ? $supportPagination['pages'] : [];

$currentSupportQuery = is_array($_GET ?? null) ? $_GET : [];
$currentSupportQuery['section'] = 'support';
$returnSupportQuery = http_build_query($currentSupportQuery);

$baseSupportFilters = [
    'section' => 'support',
    'support_search' => $supportSearch,
    'support_status' => $supportStatus,
    'support_priority' => $supportPriority,
    'support_assignment' => $supportAssignment,
];

$buildSupportUrl = static function (array $overrides = []) use ($baseSupportFilters): string {
    $params = array_merge($baseSupportFilters, $overrides);
    foreach ($params as $key => $value) {
        if ($key !== 'section' && trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/admin/dashboard' . ($query !== '' ? '?' . $query : ''));
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
    'medium' => 'Média',
    'low' => 'Baixa',
];

$supportAssignmentOptions = [
    '' => 'Com e sem responsável',
    'assigned' => 'Somente atribuídos',
    'unassigned' => 'Somente sem responsável',
];

$supportAssignmentLabels = [
    'assigned' => 'Atribuídos',
    'unassigned' => 'Sem responsável',
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

$formatSupportAttachmentSize = static function (mixed $value): string {
    $bytes = max(0, (int) ($value ?? 0));
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' B';
};

$supportAttachmentUrl = static function (array $attachment): string {
    $attachmentId = (int) ($attachment['id'] ?? 0);
    if ($attachmentId > 0) {
        return base_url('/media/support-attachment?attachment_id=' . $attachmentId);
    }

    return base_url('/media/support-attachment?message_id=' . (int) ($attachment['message_id'] ?? 0));
};
?>

<section class="dash-section<?= $activeSection === 'support' ? ' active' : '' ?>" data-section="support">
    <style>
        .st-shell{display:grid;gap:14px}
        .st-hero{border:1px solid #bfdbfe;background:linear-gradient(118deg,var(--theme-main-card,#0f172a) 0%,#1e3a8a 58%,#0ea5e9 100%);color:#fff;border-radius:14px;padding:16px;position:relative;overflow:hidden}
        .st-hero:before{content:"";position:absolute;top:-60px;right:-48px;width:210px;height:210px;border-radius:999px;background:rgba(255,255,255,.12)}
        .st-hero:after{content:"";position:absolute;bottom:-70px;left:-34px;width:180px;height:180px;border-radius:999px;background:rgba(255,255,255,.1)}
        .st-hero-body{position:relative;z-index:1;display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}
        .st-hero h2{margin:0 0 8px;font-size:22px}
        .st-hero p{margin:0;color:#dbeafe;max-width:780px;line-height:1.45}
        .st-hero-metrics{display:flex;gap:8px;flex-wrap:wrap}
        .st-hero-pill{border:1px solid rgba(255,255,255,.3);background:rgba(15,23,42,.38);border-radius:999px;padding:6px 11px;font-size:12px;font-weight:600;white-space:nowrap}

        .st-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(0,.95fr);gap:14px;align-items:start}
        .st-main,.st-side{display:grid;gap:14px}

        .st-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
        .st-card-head h3{margin:0;color:#0f172a}
        .st-card-note{margin:4px 0 0;color:#475569;font-size:13px;line-height:1.45;max-width:760px}
        .st-badges{display:flex;gap:6px;flex-wrap:wrap;align-items:center}

        .st-form-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:10px}
        .st-form-grid .field{margin:0}
        .st-form-grid .field.full{grid-column:1 / -1}
        .st-form-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px}
        .st-form-note{font-size:12px;color:#64748b;line-height:1.45;max-width:680px}

        .st-filter-grid{display:grid;grid-template-columns:1.7fr 1fr 1fr 1fr auto;gap:10px;align-items:end}
        .st-filter-grid .field{margin:0}
        .st-filter-actions{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}

        .st-ticket-list{display:grid;gap:10px}
        .st-ticket-item{border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);padding:12px;display:grid;gap:10px}
        .st-ticket-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
        .st-ticket-title{display:grid;gap:4px}
        .st-ticket-title strong{font-size:15px;color:#0f172a}
        .st-ticket-title small{font-size:12px;color:#64748b;line-height:1.35}
        .st-ticket-meta{display:flex;gap:6px;flex-wrap:wrap}
        .st-ticket-info{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
        .st-ticket-info-item{border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:10px}
        .st-ticket-info-item span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .st-ticket-info-item strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}
        .st-thread{display:grid;gap:10px;border-top:1px dashed #cbd5e1;padding-top:10px;max-height:420px;overflow-y:auto;padding-right:6px}
        .st-thread-item{display:grid;gap:7px;border-radius:12px;padding:10px 12px;max-width:92%}
        .st-thread-item.is-company{background:#eff6ff;border:1px solid #bfdbfe;justify-self:end}
        .st-thread-item.is-saas{background:#f8fafc;border:1px solid #e2e8f0;justify-self:start}
        .st-thread-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
        .st-thread-head strong{font-size:13px;color:#0f172a}
        .st-thread-head small{font-size:11px;color:#64748b}
        .st-thread-badge{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
        .st-thread-badge.is-company{background:#dbeafe;color:#1d4ed8}
        .st-thread-badge.is-saas{background:#e2e8f0;color:#334155}
        .st-thread-message{margin:0;color:#1e293b;font-size:13px;line-height:1.55}
        .st-thread-attachment{display:grid;gap:8px;padding:10px;border-radius:10px;background:rgba(255,255,255,.82);border:1px solid #cbd5e1;min-width:0}
        .st-thread-attachment a{font-weight:700;color:#1d4ed8;text-decoration:none;overflow-wrap:anywhere}
        .st-thread-attachment a:hover{text-decoration:underline}
        .st-thread-attachment small{color:#64748b;font-size:11px}
        .st-thread-attachments{display:grid;gap:8px}
        .st-thread-attachments.is-image-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
        .st-thread-attachment.is-image{padding:8px;background:#fff;align-content:start}
        .st-thread-attachment-media{display:block;border-radius:10px;overflow:hidden;background:#f8fafc;border:1px solid #dbe2ea}
        .st-thread-attachment-preview{display:block;width:100%;min-height:150px;aspect-ratio:1/1;object-fit:cover;background:#f8fafc}
        .st-thread-attachment-copy{display:grid;gap:4px;min-width:0}
        .st-thread-attachment-copy a{font-size:12px;line-height:1.4}
        .st-thread-attachment.is-image.is-preview-failed .st-thread-attachment-media{display:none}
        .st-thread-attachment-fallback{display:none;grid-template-columns:auto 1fr;align-items:center;gap:10px;padding:10px;border-radius:10px;background:#f8fafc;border:1px dashed #cbd5e1}
        .st-thread-attachment.is-image.is-preview-failed .st-thread-attachment-fallback{display:grid}
        .st-thread-attachment.is-file{grid-template-columns:auto 1fr;align-items:center}
        .st-thread-attachment-icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:#dcfce7;color:#166534;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
        .st-thread-bubble{display:grid;gap:8px}
        .st-composer{display:grid;gap:10px}
        .st-uploader{display:grid;gap:10px}
        .st-uploader-input{display:none}
        .st-uploader-dropzone{border:1px dashed #86efac;background:linear-gradient(180deg,#f0fdf4 0%,#dcfce7 100%);border-radius:14px;padding:14px;display:grid;gap:6px;cursor:pointer;transition:border-color .18s ease, transform .18s ease, box-shadow .18s ease}
        .st-uploader.is-dragover .st-uploader-dropzone{border-color:#16a34a;box-shadow:0 12px 24px rgba(22,163,74,.12);transform:translateY(-1px)}
        .st-uploader-dropzone strong{font-size:14px;color:#166534}
        .st-uploader-dropzone span{font-size:12px;color:#166534}
        .st-uploader-meta{display:flex;gap:8px;flex-wrap:wrap}
        .st-uploader-pill{padding:4px 8px;border-radius:999px;background:#bbf7d0;color:#166534;font-size:11px;font-weight:700}
        .st-uploader-list{display:grid;gap:8px}
        .st-uploader-item{display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:center;padding:10px;border:1px solid #dbe2ea;border-radius:12px;background:#fff}
        .st-uploader-thumb{width:52px;height:52px;border-radius:10px;object-fit:cover;border:1px solid #dbe2ea;background:#f8fafc}
        .st-uploader-fileicon{width:52px;height:52px;border-radius:10px;display:grid;place-items:center;background:#e0f2fe;color:#075985;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
        .st-uploader-copy{display:grid;gap:4px;min-width:0}
        .st-uploader-copy strong{font-size:13px;color:#0f172a;overflow-wrap:anywhere}
        .st-uploader-copy small{font-size:11px;color:#64748b}
        .st-uploader-remove{border:0;background:#fee2e2;color:#991b1b;border-radius:10px;padding:8px 10px;cursor:pointer;font-size:12px;font-weight:700}
        .st-reply-box{display:grid;gap:10px;border-top:1px dashed #cbd5e1;padding-top:10px}
        .st-reply-box .field{margin:0}
        .st-reply-box textarea{min-height:108px}
        .st-reply-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
        .st-reply-note{font-size:12px;color:#64748b;line-height:1.45;max-width:720px}
        .st-conversation{border-top:1px dashed #cbd5e1;padding-top:10px}
        .st-conversation summary{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;list-style:none;font-weight:700;color:#0f172a}
        .st-conversation summary::-webkit-details-marker{display:none}
        .st-conversation-meta{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .st-conversation-count{font-size:12px;color:#64748b;font-weight:600}
        .st-conversation-toggle{font-size:11px;color:#1d4ed8;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:4px 9px;font-weight:700}
        .st-conversation[open] .st-conversation-toggle{background:#eff6ff}
        .st-conversation-body{display:grid;gap:10px;margin-top:10px}
        .st-thread::-webkit-scrollbar{width:10px}
        .st-thread::-webkit-scrollbar-track{background:#e2e8f0;border-radius:999px}
        .st-thread::-webkit-scrollbar-thumb{background:#94a3b8;border-radius:999px}
        .st-thread::-webkit-scrollbar-thumb:hover{background:#64748b}

        .st-summary-grid{display:grid;gap:8px}
        .st-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
        .st-summary-item strong{color:#0f172a}
        .st-summary-item span{color:#64748b;font-size:13px}
        .st-summary-badge{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}

        .st-págination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px}
        .st-págination .dash-págination-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

        .st-governance{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
        .st-governance h4{margin:0 0 8px;color:#1e1b4b}
        .st-governance p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
        .st-governance-list{display:grid;gap:8px;margin-top:10px}
        .st-governance-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;background:#fff;border:1px solid #c7d2fe;font-size:13px}
        .st-governance-item strong{color:#312e81}
        .st-governance-badge{padding:4px 8px;border-radius:999px;background:#e0e7ff;color:#312e81;font-size:11px;font-weight:600}

        @media (max-width:1180px){
            .st-layout{grid-template-columns:1fr}
        }
        @media (max-width:900px){
            .st-filter-grid{grid-template-columns:1fr 1fr}
        }
        @media (max-width:820px){
            .st-form-grid,.st-ticket-info{grid-template-columns:1fr}
        }
        @media (max-width:640px){
            .st-hero h2{font-size:20px}
            .st-form-footer{align-items:flex-start}
            .st-filter-grid{grid-template-columns:1fr}
        }
    </style>

    <div class="st-shell">
        <div class="st-hero">
            <div class="st-hero-body">
                <div>
                    <h2>Fale com a equipe técnica</h2>
                    <p>Abra chamados operacionais no mesmo padrão visual de Usuários internos e Personalização. O histórico agora trabalha com filtros inteligentes e paginação de no máximo 10 registros por página.</p>
                </div>
                <div class="st-hero-metrics">
                    <span class="st-hero-pill">Chamados filtrados: <?= htmlspecialchars((string) $supportTotal) ?></span>
                    <span class="st-hero-pill">Em aberto: <?= htmlspecialchars((string) $supportOpenCount) ?></span>
                    <span class="st-hero-pill">Em andamento: <?= htmlspecialchars((string) $supportInProgressCount) ?></span>
                    <span class="st-hero-pill">Urgentes: <?= htmlspecialchars((string) $supportUrgentCount) ?></span>
                </div>
            </div>
        </div>

        <div class="st-layout">
            <div class="st-main">
                <div class="card">
                    <div class="st-card-head">
                        <div>
                            <h3>Abrir novo chamado</h3>
                            <p class="st-card-note">Descreva o problema com impacto, horário, ambiente e comportamento observado. Quanto melhor o contexto, menor o retrabalho para triagem e retorno técnico.</p>
                        </div>
                        <div class="st-badges">
                            <span class="badge status-default">Fila centralizada</span>
                            <span class="badge status-default">Histórico rastreável</span>
                            <span class="badge status-default">Prioridade controlada</span>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/support/store')) ?>" enctype="multipart/form-data">
                        <?= form_security_fields('dashboard.support.store') ?>
                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSupportQuery) ?>">

                        <div class="st-form-grid">
                            <div class="field">
                                <label for="ticket_subject">Assunto</label>
                                <input id="ticket_subject" name="subject" type="text" maxlength="180" required placeholder="Ex.: falha ao fechar caixa no turno da noite">
                            </div>
                            <div class="field">
                                <label for="ticket_priority">Prioridade</label>
                                <select id="ticket_priority" name="priority">
                                    <option value="medium">Média</option>
                                    <option value="low">Baixa</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </div>
                            <div class="field full">
                                <label for="ticket_description">Mensagem para a equipe técnica</label>
                                <textarea id="ticket_description" name="description" rows="6" placeholder="Informe o que aconteceu, quando ocorreu, quem foi impactado, se existe bloqueio operacional e como reproduzir o erro."></textarea>
                            </div>
                            <div class="field full">
                                <div class="st-uploader" data-chat-uploader>
                                    <label for="ticket_attachments">Anexos da conversa</label>
                                    <input class="st-uploader-input" id="ticket_attachments" data-uploader-input name="attachments[]" type="file" multiple accept=".png,.jpg,.jpeg,.webp,.gif,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
                                    <div class="st-uploader-dropzone" data-uploader-dropzone tabindex="0">
                                        <strong>Arraste, cole ou clique para anexar arquivos</strong>
                                        <span>Envie varias imagens e documentos na mesma mensagem, como em uma conversa de app.</span>
                                        <div class="st-uploader-meta">
                                            <span class="st-uploader-pill">Ate 8 arquivos</span>
                                            <span class="st-uploader-pill">Maximo 10MB por arquivo</span>
                                        </div>
                                    </div>
                                    <div class="st-uploader-list" data-uploader-list hidden></div>
                                </div>
                            </div>
                        </div>

                        <div class="st-form-footer">
                            <p class="st-form-note">Use `Urgente` apenas para indisponibilidade operacional real. Se houver evidencia, anexe imagem, PDF, planilha, TXT ou ZIP com ate 10MB.</p>
                            <button class="btn" type="submit">Abrir chamado</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="st-card-head">
                        <div>
                            <h3>Histórico de chamados</h3>
                            <p class="st-card-note">Use busca por ID, assunto, descrição, quem abriu ou responsável. Os resultados abaixo exibem no máximo 10 itens por página.</p>
                        </div>
                        <div class="st-badges">
                            <?php if ($lastSupportOpenedAt !== ''): ?>
                                <span class="badge status-default">Última abertura: <?= htmlspecialchars($formatSupportDate($lastSupportOpenedAt)) ?></span>
                            <?php endif; ?>
                            <span class="badge status-default">Atribuidos: <?= htmlspecialchars((string) $supportAssignedCount) ?></span>
                        </div>
                    </div>

                    <form method="GET" action="<?= htmlspecialchars(base_url('/admin/dashboard')) ?>">
                        <input type="hidden" name="section" value="support">
                        <div class="st-filter-grid">
                            <div class="field">
                                <label for="support_search">Busca inteligente</label>
                                <input id="support_search" name="support_search" type="text" value="<?= htmlspecialchars($supportSearch) ?>" placeholder="ID, assunto, descrição, autor ou responsável">
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
                                <label for="support_assignment">Atribuição</label>
                                <select id="support_assignment" name="support_assignment">
                                    <?php foreach ($supportAssignmentOptions as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= $supportAssignment === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="st-filter-actions">
                                <button class="btn" type="submit">Aplicar</button>
                                <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/dashboard?section=support')) ?>">Limpar</a>
                            </div>
                        </div>
                    </form>

                    <div class="st-badges" style="margin-top:10px">
                        <span class="badge status-default">Total filtrado: <?= htmlspecialchars((string) $supportTotal) ?></span>
                        <?php if ($supportSearch !== ''): ?><span class="badge status-default">Busca: <?= htmlspecialchars($supportSearch) ?></span><?php endif; ?>
                        <?php if ($supportStatus !== ''): ?><span class="badge status-default">Status: <?= htmlspecialchars($supportStatusLabels[$supportStatus] ?? ucfirst($supportStatus)) ?></span><?php endif; ?>
                        <?php if ($supportPriority !== ''): ?><span class="badge status-default">Prioridade: <?= htmlspecialchars($supportPriorityLabels[$supportPriority] ?? ucfirst($supportPriority)) ?></span><?php endif; ?>
                        <?php if ($supportAssignment !== ''): ?><span class="badge status-default">Atribuição: <?= htmlspecialchars($supportAssignmentLabels[$supportAssignment] ?? $supportAssignment) ?></span><?php endif; ?>
                    </div>

                    <?php if ($supportTickets === []): ?>
                        <div class="empty-state" style="margin-top:10px">
                            <?= ($supportSearch !== '' || $supportStatus !== '' || $supportPriority !== '' || $supportAssignment !== '')
                                ? 'Nenhum chamado encontrado para os filtros aplicados.'
                                : 'Nenhum chamado aberto ate o momento.' ?>
                        </div>
                    <?php else: ?>
                        <div class="st-ticket-list" style="margin-top:10px">
                            <?php foreach ($supportTickets as $ticket): ?>
                                <?php
                                $priorityRaw = strtolower(trim((string) ($ticket['priority'] ?? 'medium')));
                                $statusRaw = strtolower(trim((string) ($ticket['status'] ?? 'open')));
                                $priorityBadge = match ($priorityRaw) {
                                    'low' => 'status-default',
                                    'medium' => 'status-pending',
                                    'high' => 'status-waiting',
                                    'urgent' => 'status-canceled',
                                    default => 'status-default',
                                };
                                $statusBadge = match ($statusRaw) {
                                    'open' => 'status-open',
                                    'in_progress' => 'status-received',
                                    'resolved' => 'status-success',
                                    'closed' => 'status-closed',
                                    default => 'status-default',
                                };
                                $ticketId = (int) ($ticket['id'] ?? 0);
                                $threadMessages = is_array($supportThreads[$ticketId] ?? null) ? $supportThreads[$ticketId] : [];
                                $messageCount = count($threadMessages);
                                ?>
                                <article class="st-ticket-item">
                                    <div class="st-ticket-top">
                                        <div class="st-ticket-title">
                                            <strong>#<?= $ticketId ?> - <?= htmlspecialchars((string) ($ticket['subject'] ?? '-')) ?></strong>
                                            <small>Aberto por <?= htmlspecialchars((string) ($ticket['opened_by_user_name'] ?? '-')) ?> em <?= htmlspecialchars($formatSupportDate($ticket['created_at'] ?? '')) ?></small>
                                        </div>
                                        <div class="st-ticket-meta">
                                            <span class="badge <?= htmlspecialchars($priorityBadge) ?>"><?= htmlspecialchars($supportPriorityLabels[$priorityRaw] ?? ucfirst($priorityRaw)) ?></span>
                                            <span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars($supportStatusLabels[$statusRaw] ?? ucfirst($statusRaw)) ?></span>
                                        </div>
                                    </div>

                                    <div class="st-ticket-info">
                                        <div class="st-ticket-info-item">
                                            <span>Responsavel</span>
                                            <strong><?= htmlspecialchars((string) ($ticket['assigned_to_user_name'] ?? 'Não atribuído')) ?></strong>
                                        </div>
                                        <div class="st-ticket-info-item">
                                            <span>Última atualização</span>
                                            <strong><?= htmlspecialchars($formatSupportDate($ticket['updated_at'] ?? '')) ?></strong>
                                        </div>
                                        <div class="st-ticket-info-item">
                                            <span>Fechamento</span>
                                            <strong><?= htmlspecialchars($formatSupportDate($ticket['closed_at'] ?? '')) ?></strong>
                                        </div>
                                    </div>

                                    <details class="st-conversation">
                                        <summary>
                                            <span>Conversa do chamado</span>
                                            <span class="st-conversation-meta">
                                                <span class="st-conversation-count"><?= htmlspecialchars((string) $messageCount) ?> mensagem(ns)</span>
                                                <span class="st-conversation-toggle">Expandir / recolher</span>
                                            </span>
                                        </summary>

                                        <div class="st-conversation-body">
                                            <div class="st-thread">
                                                <?php foreach ($threadMessages as $threadMessage): ?>
                                                    <?php
                                                    $senderContext = strtolower(trim((string) ($threadMessage['sender_context'] ?? 'company')));
                                                    $isCompanySender = $senderContext !== 'saas';
                                                    $senderBadge = $isCompanySender ? 'Empresa' : 'SaaS';
                                                    $senderName = trim((string) ($threadMessage['sender_user_name'] ?? ''));
                                                    if ($senderName === '') {
                                                        $senderName = $isCompanySender ? 'Empresa' : 'Suporte SaaS';
                                                    }
                                                    ?>
                                                    <div class="st-thread-item<?= $isCompanySender ? ' is-company' : ' is-saas' ?>">
                                                        <div class="st-thread-head">
                                                            <div>
                                                                <strong><?= htmlspecialchars($senderName) ?></strong>
                                                                <div class="st-thread-badge<?= $isCompanySender ? ' is-company' : ' is-saas' ?>"><?= htmlspecialchars($senderBadge) ?></div>
                                                            </div>
                                                            <small><?= htmlspecialchars($formatSupportDate($threadMessage['created_at'] ?? '')) ?></small>
                                                        </div>
                                                        <div class="st-thread-bubble">
                                                            <?php $attachments = is_array($threadMessage['attachments'] ?? null) ? $threadMessage['attachments'] : []; ?>
                                                            <?php if ($attachments !== []): ?>
                                                                <div class="st-thread-attachments<?= count(array_filter($attachments, static fn (array $attachment): bool => (bool) ($attachment['is_image'] ?? false))) === count($attachments) ? ' is-image-grid' : '' ?>">
                                                                    <?php foreach ($attachments as $attachment): ?>
                                                                        <?php
                                                                        $attachmentUrl = $supportAttachmentUrl($attachment);
                                                                        $attachmentName = (string) ($attachment['attachment_original_name'] ?? 'Anexo');
                                                                        $attachmentMime = (string) ($attachment['attachment_mime_type'] ?? 'arquivo');
                                                                        $attachmentSize = (int) ($attachment['attachment_size_bytes'] ?? 0);
                                                                        $attachmentExt = strtoupper(substr((string) pathinfo($attachmentName !== '' ? $attachmentName : 'arquivo', PATHINFO_EXTENSION), 0, 4)) ?: 'DOC';
                                                                        ?>
                                                                        <?php if ((bool) ($attachment['is_image'] ?? false)): ?>
                                                                            <div class="st-thread-attachment is-image" data-support-inline-attachment>
                                                                                <a class="st-thread-attachment-media" href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
                                                                                    <img class="st-thread-attachment-preview" src="<?= htmlspecialchars($attachmentUrl) ?>" alt="<?= htmlspecialchars($attachmentName !== '' ? $attachmentName : 'Imagem do chamado') ?>" loading="lazy" decoding="async" data-support-inline-image>
                                                                                </a>
                                                                                <div class="st-thread-attachment-fallback">
                                                                                    <div class="st-thread-attachment-icon"><?= htmlspecialchars($attachmentExt) ?></div>
                                                                                    <div class="st-thread-attachment-copy">
                                                                                        <a href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
                                                                                            <?= htmlspecialchars($attachmentName !== '' ? $attachmentName : 'Imagem do chamado') ?>
                                                                                        </a>
                                                                                        <small>Preview indisponivel na conversa. Abra a imagem em nova guia.</small>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="st-thread-attachment-copy">
                                                                                    <a href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
                                                                                        <?= htmlspecialchars($attachmentName !== '' ? $attachmentName : 'Imagem do chamado') ?>
                                                                                    </a>
                                                                                    <small>
                                                                                        <?= htmlspecialchars($attachmentMime) ?>
                                                                                        <?php if ($attachmentSize > 0): ?>
                                                                                            - <?= htmlspecialchars($formatSupportAttachmentSize($attachmentSize)) ?>
                                                                                        <?php endif; ?>
                                                                                    </small>
                                                                                </div>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="st-thread-attachment is-file">
                                                                                <div class="st-thread-attachment-icon"><?= htmlspecialchars($attachmentExt) ?></div>
                                                                                <div class="st-thread-attachment-copy">
                                                                                    <a href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
                                                                                        <?= htmlspecialchars($attachmentName) ?>
                                                                                    </a>
                                                                                    <small>
                                                                                        <?= htmlspecialchars($attachmentMime) ?>
                                                                                        <?php if ($attachmentSize > 0): ?>
                                                                                            - <?= htmlspecialchars($formatSupportAttachmentSize($attachmentSize)) ?>
                                                                                        <?php endif; ?>
                                                                                    </small>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php $threadBody = trim((string) ($threadMessage['message'] ?? '')); ?>
                                                            <?php if ($threadBody !== ''): ?>
                                                                <p class="st-thread-message"><?= nl2br(htmlspecialchars($threadBody), false) ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <form class="st-reply-box" method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/support/reply')) ?>" enctype="multipart/form-data">
                                                <?= form_security_fields('dashboard.support.reply.' . $ticketId) ?>
                                                <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSupportQuery) ?>">

                                                <div class="field">
                                                    <label for="support_reply_<?= $ticketId ?>">Responder no mesmo chamado</label>
                                                    <textarea id="support_reply_<?= $ticketId ?>" name="message" rows="4" placeholder="Escreva a continuidade da conversa para o suporte SaaS."></textarea>
                                                </div>

                                                <div class="field">
                                                    <div class="st-uploader" data-chat-uploader>
                                                        <label for="support_reply_attachments_<?= $ticketId ?>">Arquivos da resposta</label>
                                                        <input class="st-uploader-input" id="support_reply_attachments_<?= $ticketId ?>" data-uploader-input name="attachments[]" type="file" multiple accept=".png,.jpg,.jpeg,.webp,.gif,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
                                                        <div class="st-uploader-dropzone" data-uploader-dropzone tabindex="0">
                                                            <strong>Arraste, cole ou clique para anexar</strong>
                                                            <span>Imagem, PDF, planilha, ZIP e outros arquivos permitidos na mesma resposta.</span>
                                                            <div class="st-uploader-meta">
                                                                <span class="st-uploader-pill">Ate 8 arquivos</span>
                                                                <span class="st-uploader-pill">Maximo 10MB por arquivo</span>
                                                            </div>
                                                        </div>
                                                        <div class="st-uploader-list" data-uploader-list hidden></div>
                                                    </div>
                                                </div>

                                                <div class="st-reply-footer">
                                                    <p class="st-reply-note">
                                                        <?php if (in_array($statusRaw, ['resolved', 'closed'], true)): ?>
                                                            Nova mensagem da empresa reabre o chamado no histórico para continuar a tratativa.
                                                        <?php else: ?>
                                                            A resposta entra na mesma thread do chamado, sem criar outro registro no histórico.
                                                        <?php endif; ?>
                                                        Tambem e possivel enviar somente anexos, sem texto, quando os arquivos ja explicam a ocorrencia.
                                                    </p>
                                                    <button class="btn secondary" type="submit">Enviar resposta</button>
                                                </div>
                                            </form>
                                        </div>
                                    </details>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($supportLastPage > 1): ?>
                            <div class="st-págination">
                                <div class="dash-págination-info">
                                    Exibindo <?= htmlspecialchars((string) $supportFrom) ?> a <?= htmlspecialchars((string) $supportTo) ?> de <?= htmlspecialchars((string) $supportTotal) ?> chamados.
                                </div>
                                <div class="dash-págination-controls">
                                    <?php if ($supportPage > 1): ?>
                                        <a class="dash-page-btn" href="<?= htmlspecialchars($buildSupportUrl(['support_page' => $supportPage - 1])) ?>">Anterior</a>
                                    <?php endif; ?>

                                    <?php
                                    $previousSupportPage = null;
                                    foreach ($supportPages as $pageNumber):
                                        $pageNumber = (int) $pageNumber;
                                        if ($previousSupportPage !== null && $pageNumber - $previousSupportPage > 1): ?>
                                            <span class="págination-ellipsis">...</span>
                                        <?php endif; ?>
                                        <a class="dash-page-btn<?= $pageNumber === $supportPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildSupportUrl(['support_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                        <?php
                                        $previousSupportPage = $pageNumber;
                                    endforeach;
                                    ?>

                                    <?php if ($supportPage < $supportLastPage): ?>
                                        <a class="dash-page-btn" href="<?= htmlspecialchars($buildSupportUrl(['support_page' => $supportPage + 1])) ?>">Próxima</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="st-side">
                <div class="card">
                    <div class="st-card-head">
                        <div>
                            <h3>Resumo operacional</h3>
                            <p class="st-card-note">Leitura consolidada da fila atual conforme os filtros aplicados no histórico.</p>
                        </div>
                    </div>

                    <div class="st-summary-grid">
                        <div class="st-summary-item">
                            <strong>Total de chamados</strong>
                            <span class="st-summary-badge"><?= htmlspecialchars((string) $supportTotal) ?></span>
                        </div>
                        <div class="st-summary-item">
                            <strong>Chamados em aberto</strong>
                            <span class="st-summary-badge"><?= htmlspecialchars((string) $supportOpenCount) ?></span>
                        </div>
                        <div class="st-summary-item">
                            <strong>Chamados em andamento</strong>
                            <span class="st-summary-badge"><?= htmlspecialchars((string) $supportInProgressCount) ?></span>
                        </div>
                        <div class="st-summary-item">
                            <strong>Chamados resolvidos/fechados</strong>
                            <span class="st-summary-badge"><?= htmlspecialchars((string) $supportResolvedCount) ?></span>
                        </div>
                        <div class="st-summary-item">
                            <strong>Chamados atribuidos</strong>
                            <span class="st-summary-badge"><?= htmlspecialchars((string) $supportAssignedCount) ?></span>
                        </div>
                        <div class="st-summary-item">
                            <strong>Prioridade urgente</strong>
                            <span class="st-summary-badge"><?= htmlspecialchars((string) $supportUrgentCount) ?></span>
                        </div>
                    </div>
                </div>

                <div class="st-governance">
                    <h4>Triage e governanca</h4>
                    <p>Esse canal deve concentrar incidentes técnicos e bloqueios reais da operação. Ajustes cosméticos, dúvidas simples ou mudanças de processo precisam ser descritos com clareza para não competir com incidentes críticos.</p>
                    <div class="st-governance-list">
                        <div class="st-governance-item">
                            <strong>Uso ideal</strong>
                            <span class="st-governance-badge">Erro, indisponibilidade ou falha de fluxo</span>
                        </div>
                        <div class="st-governance-item">
                            <strong>Dados mínimos</strong>
                            <span class="st-governance-badge">Impacto, horário e reproducao</span>
                        </div>
                        <div class="st-governance-item">
                            <strong>Criticidade</strong>
                            <span class="st-governance-badge">Urgente so para bloqueio operacional</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
<script>
(() => {
    const supportsDataTransfer = () => new DataTransfer();
    const imageMime = (file) => typeof file.type === 'string' && file.type.startsWith('image/');
    const formatSize = (size) => {
        const bytes = Number(size || 0);
        if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(2).replace('.', ',')} MB`;
        if (bytes >= 1024) return `${(bytes / 1024).toFixed(1).replace('.', ',')} KB`;
        return `${bytes} B`;
    };

    document.querySelectorAll('[data-chat-uploader]').forEach((root) => {
        const input = root.querySelector('[data-uploader-input]');
        const dropzone = root.querySelector('[data-uploader-dropzone]');
        const list = root.querySelector('[data-uploader-list]');
        const form = root.closest('form');
        if (!input || !dropzone || !list || !form) {
            return;
        }

        const syncFiles = (files) => {
            const dt = supportsDataTransfer();
            files.forEach((file) => dt.items.add(file));
            input.files = dt.files;
        };

        const getFiles = () => Array.from(input.files || []);
        const render = () => {
            const files = getFiles();
            list.innerHTML = '';
            list.hidden = files.length === 0;
            files.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'st-uploader-item';
                const preview = imageMime(file)
                    ? `<img class="st-uploader-thumb" src="${URL.createObjectURL(file)}" alt="${file.name.replace(/"/g, '&quot;')}">`
                    : `<div class="st-uploader-fileicon">${(file.name.split('.').pop() || 'DOC').slice(0, 4).toUpperCase()}</div>`;
                item.innerHTML = `
                    ${preview}
                    <div class="st-uploader-copy">
                        <strong>${file.name}</strong>
                        <small>${file.type || 'arquivo'} - ${formatSize(file.size)}</small>
                    </div>
                    <button class="st-uploader-remove" type="button" data-remove-index="${index}">Remover</button>
                `;
                list.appendChild(item);
            });
        };

        const appendFiles = (incoming) => {
            const current = getFiles();
            const next = [...current];
            Array.from(incoming || []).forEach((file) => {
                if (!(file instanceof File)) return;
                if (next.length >= 8) return;
                next.push(file);
            });
            syncFiles(next);
            render();
        };

        input.addEventListener('change', () => render());
        dropzone.addEventListener('click', () => input.click());
        dropzone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });
        ['dragenter', 'dragover'].forEach((type) => {
            dropzone.addEventListener(type, (event) => {
                event.preventDefault();
                root.classList.add('is-dragover');
            });
        });
        ['dragleave', 'dragend', 'drop'].forEach((type) => {
            dropzone.addEventListener(type, (event) => {
                event.preventDefault();
                root.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', (event) => {
            appendFiles(event.dataTransfer?.files || []);
        });
        form.addEventListener('paste', (event) => {
            if (!form.contains(document.activeElement)) {
                return;
            }
            const clipboardFiles = Array.from(event.clipboardData?.files || []);
            if (clipboardFiles.length === 0) {
                return;
            }
            event.preventDefault();
            appendFiles(clipboardFiles);
        });
        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-index]');
            if (!button) return;
            const removeIndex = Number(button.getAttribute('data-remove-index'));
            const next = getFiles().filter((_, index) => index !== removeIndex);
            syncFiles(next);
            render();
        });
    });

    document.querySelectorAll('[data-support-inline-image]').forEach((image) => {
        image.addEventListener('error', () => {
            const card = image.closest('[data-support-inline-attachment]');
            if (card) {
                card.classList.add('is-preview-failed');
            }
        }, { once: true });
    });
})();
</script>
