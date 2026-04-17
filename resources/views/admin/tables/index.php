<?php
$summary = is_array($summary ?? null) ? $summary : [];
$tables = is_array($tables ?? null) ? $tables : [];
$ordersByTableNumber = is_array($ordersByTableNumber ?? null) ? $ordersByTableNumber : [];
$commandsByTableNumber = is_array($commandsByTableNumber ?? null) ? $commandsByTableNumber : [];
$canManageTables = !empty($canManageTables);

$operationalSummary = [
    'pending' => 0,
    'received' => 0,
    'preparing' => 0,
    'ready' => 0,
    'delivered' => 0,
];
$paymentSummary = [
    'pending' => 0,
    'partial' => 0,
    'paid' => 0,
];

foreach ($ordersByTableNumber as $tablePanel) {
    if (!is_array($tablePanel)) {
        continue;
    }
    $orders = is_array($tablePanel['orders'] ?? null) ? $tablePanel['orders'] : [];
    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }

        $status = (string) ($order['status'] ?? '');
        if (isset($operationalSummary[$status])) {
            $operationalSummary[$status]++;
        }

        $paymentStatus = (string) ($order['payment_status'] ?? '');
        if (isset($paymentSummary[$paymentStatus])) {
            $paymentSummary[$paymentStatus]++;
        }
    }
}
?>

<style>
    .tables-page{display:grid;gap:16px}
    .kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:12px}
    .kpi-item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .kpi-item strong{display:block;font-size:24px;line-height:1.1}
    .kpi-item span{color:#64748b;font-size:12px}
    .search-row{display:grid;grid-template-columns:1fr auto;gap:8px}
    .search-info{color:#64748b;font-size:12px;margin-top:6px}

    .tables-grid{display:grid;grid-template-columns:repeat(3,minmax(230px,1fr));gap:12px}
    .table-card{background:#fff;border:2px solid #e5e7eb;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:10px;cursor:pointer;transition:border-color .2s,transform .12s,box-shadow .2s}
    .table-card:hover{transform:translateY(-1px);box-shadow:0 10px 20px rgba(2,6,23,.08)}
    .table-card:focus-visible{outline:3px solid #93c5fd;outline-offset:2px}
    .table-card.search-hit{outline:2px solid #60a5fa;outline-offset:1px}

    .table-card.status-livre{border-color:#16a34a}
    .table-card.status-ocupada{border-color:#dc2626}
    .table-card.status-aguardando_fechamento{border-color:#d97706}
    .table-card.status-bloqueada{border-color:#64748b}

    .table-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
    .table-title strong{font-size:18px;line-height:1}
    .table-title small{color:#64748b}
    .table-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .table-meta-item{border:1px solid #e2e8f0;border-radius:10px;padding:9px;background:#f8fafc}
    .table-meta-item strong{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .table-meta-item span{font-size:14px;color:#0f172a}

    .table-service-strip{display:flex;gap:6px;flex-wrap:wrap}
    .legend-panel{display:grid;gap:10px}
    .legend-group{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .legend-title{display:block;font-size:12px;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
    .legend-row{display:flex;gap:8px;flex-wrap:wrap}

    .table-actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
    .btn-qr{background:linear-gradient(135deg,#0f766e,#0d9488);color:#fff;border:1px solid #0f766e}
    .btn-qr:hover{filter:brightness(1.04)}

    .table-modal-shell[hidden]{display:none}
    .table-modal-shell{position:fixed;inset:0;z-index:900;display:flex;align-items:center;justify-content:center;padding:18px}
    .table-modal-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.58)}
    .table-modal-panel{position:relative;background:#fff;border-radius:14px;border:1px solid #cbd5e1;max-width:1080px;width:100%;max-height:min(88vh,900px);display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 60px rgba(2,6,23,.35)}
    .table-modal-header{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #e2e8f0;background:linear-gradient(180deg,#f8fafc,#f1f5f9)}
    .table-modal-header h3{margin:0;font-size:20px}
    .table-modal-notice{margin:10px 14px 0;padding:8px 10px;border-radius:8px;font-size:13px}
    .table-modal-notice.success{background:#dcfce7;border:1px solid #86efac;color:#166534}
    .table-modal-notice.error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    .table-modal-body{padding:14px;overflow:auto;display:grid;gap:12px;background:#f8fafc}
    .modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .modal-block{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px}
    .modal-block h4{margin:0 0 8px}

    .command-list{display:grid;gap:8px}
    .command-item{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .command-item p{margin:6px 0 0;color:#334155;font-size:13px;line-height:1.35}

    .orders-list{display:grid;gap:8px}
    .order-item{border:1px solid #dbeafe;border-radius:10px;padding:10px;background:#eff6ff}
    .order-item-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap}
    .order-item-head p{margin:4px 0 0;color:#334155;font-size:13px}
    .order-badges{display:flex;gap:6px;flex-wrap:wrap}
    .order-products{margin-top:8px;display:grid;gap:6px}
    .order-products .title{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .order-product-item{border:1px solid #bfdbfe;background:#dbeafe;border-radius:8px;padding:8px}
    .order-product-head{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;flex-wrap:wrap}
    .order-product-head strong{font-size:13px}
    .order-product-meta{font-size:12px;color:#1e3a8a;margin-top:3px}
    .order-product-notes{font-size:12px;color:#475569;margin-top:3px}
    .order-product-additionals{margin-top:5px;font-size:12px;color:#334155}
    .order-actions{display:grid;gap:8px;margin-top:8px}
    .order-actions .row{display:flex;gap:8px;flex-wrap:wrap}

    .muted{color:#64748b}

    @media (max-width:1100px){
        .kpi-grid{grid-template-columns:repeat(3,minmax(120px,1fr))}
        .tables-grid{grid-template-columns:repeat(2,minmax(220px,1fr))}
        .modal-grid{grid-template-columns:1fr}
    }
    @media (max-width:680px){
        .kpi-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}
        .tables-grid{grid-template-columns:1fr}
    }
</style>

<div class="tables-page">
    <div class="topbar">
        <div>
            <h1>Mesas</h1>
            <p>Painel operacional com status da mesa, comandas e pedidos ativos.</p>
        </div>
        <?php if ($canManageTables): ?>
            <a class="btn" href="<?= htmlspecialchars(base_url('/admin/tables/create')) ?>">Nova mesa</a>
        <?php endif; ?>
    </div>

    <div class="kpi-grid">
        <div class="kpi-item"><strong><?= (int) ($summary['total'] ?? 0) ?></strong><span>Total de mesas</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['livre'] ?? 0) ?></strong><span>Livres</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['ocupada'] ?? 0) ?></strong><span>Ocupadas</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['aguardando_fechamento'] ?? 0) ?></strong><span>Aguardando fechamento</span></div>
        <div class="kpi-item"><strong><?= (int) ($summary['bloqueada'] ?? 0) ?></strong><span>Bloqueadas</span></div>
    </div>

    <div class="card legend-panel">
        <div class="legend-group">
            <strong class="legend-title">Legenda de Cores - Status das Mesas</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('table_status', 'livre')) ?>">Mesa Livre</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('table_status', 'ocupada')) ?>">Mesa Ocupada</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('table_status', 'aguardando_fechamento')) ?>">Aguardando fechamento</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('table_status', 'bloqueada')) ?>">Mesa Bloqueada</span>
            </div>
        </div>
        <div class="legend-group">
            <strong class="legend-title">Legenda de Cores - Status Operacional dos Pedidos</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'pending')) ?>">Pendentes: <?= (int) ($operationalSummary['pending'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'received')) ?>">Recebidos: <?= (int) ($operationalSummary['received'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'preparing')) ?>">Em preparo: <?= (int) ($operationalSummary['preparing'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'ready')) ?>">Prontos: <?= (int) ($operationalSummary['ready'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'delivered')) ?>">Entregues: <?= (int) ($operationalSummary['delivered'] ?? 0) ?></span>
            </div>
        </div>
        <div class="legend-group">
            <strong class="legend-title">Legenda de Cores - Status de Pagamento</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'pending')) ?>">Pagamento Pendente: <?= (int) ($paymentSummary['pending'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'partial')) ?>">Pagamento Parcial: <?= (int) ($paymentSummary['partial'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'paid')) ?>">Pagamento Pago: <?= (int) ($paymentSummary['paid'] ?? 0) ?></span>
            </div>
        </div>
        <p class="search-info" style="margin:0">Atualizacao automatica da página a cada 30 segundos (quando o modal estiver fechado).</p>
    </div>

    <div class="card">
        <div class="search-row">
            <input id="tableSearch" type="text" placeholder="Buscar por numero, nome, status, capacidade, pedido, comanda ou token QR">
            <button id="clearTableSearch" class="btn secondary" type="button">Limpar</button>
        </div>
        <div id="tableSearchInfo" class="search-info">Digite para filtrar as mesas de forma inteligente.</div>
    </div>

    <div class="tables-grid" id="tablesGrid">
        <?php if (empty($tables)): ?>
            <div class="table-card">Nenhuma mesa cadastrada.</div>
        <?php else: ?>
            <?php foreach ($tables as $table): ?>
                <?php
                $tableId = (int) ($table['id'] ?? 0);
                $tableNumber = (int) ($table['number'] ?? 0);
                $tableName = trim((string) ($table['name'] ?? ''));
                $statusValue = (string) ($table['status'] ?? 'livre');
                $capacity = array_key_exists('capacity', $table) && $table['capacity'] !== null ? (int) $table['capacity'] : null;
                $token = (string) ($table['qr_code_token'] ?? '');
                $statusLabel = status_label('table_status', $statusValue);

                $orderPanel = is_array($ordersByTableNumber[$tableNumber] ?? null) ? $ordersByTableNumber[$tableNumber] : [];
                $tableOrdersCount = (int) ($orderPanel['orders_count'] ?? 0);
                $tableItemsTotal = (int) ($orderPanel['items_total'] ?? 0);
                $tableAmountTotal = (float) ($orderPanel['amount_total'] ?? 0);

                $tableCommands = is_array($commandsByTableNumber[$tableNumber] ?? null) ? $commandsByTableNumber[$tableNumber] : [];
                $tableCommandsCount = count($tableCommands);

                $searchText = strtolower(trim(implode(' ', [
                    (string) $tableNumber,
                    $tableName,
                    (string) $capacity,
                    $token,
                    $statusValue,
                    (string) $statusLabel,
                    (string) $tableOrdersCount,
                    (string) $tableCommandsCount,
                    number_format($tableAmountTotal, 2, '.', ''),
                ])));
                ?>
                <div
                    class="table-card status-<?= htmlspecialchars($statusValue) ?>"
                    data-search="<?= htmlspecialchars($searchText) ?>"
                    data-table-id="<?= $tableId ?>"
                    data-table-label="Mesa <?= $tableNumber > 0 ? $tableNumber : '-' ?>"
                    role="button"
                    tabindex="0"
                >
                    <div class="table-head">
                        <div class="table-title">
                            <strong>Mesa <?= $tableNumber > 0 ? $tableNumber : '-' ?></strong><br>
                            <small><?= htmlspecialchars($tableName !== '' ? $tableName : 'Sem nome definido') ?></small>
                        </div>
                        <span class="badge <?= htmlspecialchars(status_badge_class('table_status', $statusValue)) ?>">
                            <?= htmlspecialchars($statusLabel) ?>
                        </span>
                    </div>

                    <div class="table-meta">
                        <div class="table-meta-item">
                            <strong>Capacidade</strong>
                            <span><?= $capacity !== null ? $capacity . ' pessoa(s)' : 'Não informada' ?></span>
                        </div>
                        <div class="table-meta-item">
                            <strong>Token QR</strong>
                            <span title="<?= htmlspecialchars($token) ?>"><?= htmlspecialchars(substr($token, 0, 18) . (strlen($token) > 18 ? '...' : '')) ?></span>
                        </div>
                    </div>

                    <div class="table-service-strip">
                        <span class="badge status-received">Pedidos ativos: <?= $tableOrdersCount ?></span>
                        <span class="badge status-open">Comandas abertas: <?= $tableCommandsCount ?></span>
                        <span class="badge status-default">Itens: <?= $tableItemsTotal ?></span>
                        <span class="badge status-paid">R$ <?= number_format($tableAmountTotal, 2, ',', '.') ?></span>
                    </div>

                    <div class="table-actions">
                        <a class="btn btn-qr" href="<?= htmlspecialchars(base_url('/admin/tables/print-qr?table_id=' . $tableId)) ?>">QR para imprimir</a>

                        <?php if ($canManageTables): ?>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/tables/edit?table_id=' . $tableId)) ?>">Editar</a>
                            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/tables/delete')) ?>" onsubmit="return confirm('Excluir esta mesa? Esta acao nao pode ser desfeita.');">
                                <?= form_security_fields('tables.delete') ?>
                                <input type="hidden" name="table_id" value="<?= $tableId ?>">
                                <button class="btn secondary" type="submit">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="tableModal" class="table-modal-shell" hidden>
    <div class="table-modal-backdrop" data-close-modal="1"></div>
    <section class="table-modal-panel" role="dialog" aria-modal="true" aria-labelledby="tableModalTitle">
        <header class="table-modal-header">
            <h3 id="tableModalTitle">Operação da mesa</h3>
            <button class="btn secondary" type="button" data-close-modal="1">Fechar</button>
        </header>
        <div id="tableModalNotice" class="table-modal-notice" hidden></div>
        <div id="tableModalBody" class="table-modal-body"></div>
    </section>
</div>

<script>
(() => {
    const cards = Array.from(document.querySelectorAll('.table-card[data-search][data-table-id]'));
    const searchInput = document.getElementById('tableSearch');
    const clearButton = document.getElementById('clearTableSearch');
    const searchInfo = document.getElementById('tableSearchInfo');
    const modalEndpointBase = <?= json_encode(base_url('/admin/tables/modal-content')) ?>;

    const modalShell = document.getElementById('tableModal');
    const modalBody = document.getElementById('tableModalBody');
    const modalTitle = document.getElementById('tableModalTitle');
    const modalNotice = document.getElementById('tableModalNotice');
    const modalRefreshMs = 30000;
    const pageRefreshMs = 30000;

    let modalRefreshTimer = null;
    let activeTableId = null;
    let modalLoadSequence = 0;
    let modalNoticeTimer = null;

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const hideModalNotice = () => {
        if (!modalNotice) {
            return;
        }
        if (modalNoticeTimer !== null) {
            window.clearTimeout(modalNoticeTimer);
            modalNoticeTimer = null;
        }
        modalNotice.hidden = true;
        modalNotice.className = 'table-modal-notice';
        modalNotice.textContent = '';
    };

    const showModalNotice = (message, type = 'success') => {
        if (!modalNotice) {
            return;
        }
        if (modalNoticeTimer !== null) {
            window.clearTimeout(modalNoticeTimer);
            modalNoticeTimer = null;
        }
        modalNotice.hidden = false;
        modalNotice.className = `table-modal-notice ${type === 'error' ? 'error' : 'success'}`;
        modalNotice.textContent = String(message || '');
        modalNoticeTimer = window.setTimeout(() => {
            hideModalNotice();
        }, 3800);
    };

    const stopModalAutoRefresh = () => {
        if (modalRefreshTimer !== null) {
            window.clearInterval(modalRefreshTimer);
            modalRefreshTimer = null;
        }
        activeTableId = null;
    };

    const loadModalContent = async (tableId, showLoading = false) => {
        if (!modalBody || !modalShell || modalShell.hidden) {
            return;
        }

        const requestId = ++modalLoadSequence;

        if (showLoading) {
            modalBody.innerHTML = '<div class="modal-grid"><section class="modal-block"><p class="muted">Atualizando dados da mesa...</p></section></div>';
        }

        try {
            const url = `${modalEndpointBase}?table_id=${encodeURIComponent(String(tableId))}&_ts=${Date.now()}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            const html = await response.text();
            const stale = requestId !== modalLoadSequence || activeTableId !== tableId || modalShell.hidden;
            if (stale) {
                return;
            }

            if (response.ok || html.trim() !== '') {
                modalBody.innerHTML = html;
                return;
            }

            modalBody.innerHTML = '<div class="modal-grid"><section class="modal-block"><p class="muted">Não foi possível atualizar as informações da mesa.</p></section></div>';
        } catch (_error) {
            if (activeTableId !== tableId || modalShell.hidden) {
                return;
            }

            if (modalBody.innerHTML.trim() === '') {
                modalBody.innerHTML = '<div class="modal-grid"><section class="modal-block"><p class="muted">Falha temporaria ao carregar dados da mesa. Tente novamente.</p></section></div>';
            }
        }
    };

    const startModalAutoRefresh = (tableId) => {
        stopModalAutoRefresh();
        activeTableId = tableId;
        void loadModalContent(tableId, true);
        modalRefreshTimer = window.setInterval(() => {
            if (activeTableId === tableId && modalShell && !modalShell.hidden) {
                void loadModalContent(tableId, false);
            }
        }, modalRefreshMs);
    };

    const closeModal = () => {
        if (!modalShell) {
            return;
        }
        stopModalAutoRefresh();
        modalShell.hidden = true;
        document.body.style.overflow = '';
        hideModalNotice();
        if (modalBody) {
            modalBody.innerHTML = '';
        }
    };

    const openModalFromCard = (card) => {
        if (!modalShell || !modalBody) {
            return;
        }

        const tableId = Number.parseInt(card.getAttribute('data-table-id') || '0', 10);
        if (!Number.isFinite(tableId) || tableId <= 0) {
            return;
        }

        const tableLabel = card.getAttribute('data-table-label') || 'Mesa';
        if (modalTitle) {
            modalTitle.textContent = `${tableLabel} - Comandas e Pedidos`;
        }

        modalShell.hidden = false;
        document.body.style.overflow = 'hidden';
        hideModalNotice();
        startModalAutoRefresh(tableId);
    };

    const isInteractiveTarget = (target) => {
        if (!(target instanceof Element)) {
            return false;
        }
        return target.closest('a,button,input,select,textarea,form,label') !== null;
    };

    cards.forEach((card) => {
        card.addEventListener('click', (event) => {
            if (isInteractiveTarget(event.target)) {
                return;
            }
            openModalFromCard(card);
        });

        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openModalFromCard(card);
            }
        });
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (target.getAttribute('data-close-modal') === '1') {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalShell && !modalShell.hidden) {
            closeModal();
        }
    });

    if (modalBody) {
        modalBody.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (form.getAttribute('data-modal-async-action') !== '1') {
                return;
            }

            event.preventDefault();

            const action = form.getAttribute('action') || '';
            if (action === '') {
                return;
            }

            const submitButtons = Array.from(form.querySelectorAll('button[type="submit"],input[type="submit"]'));
            submitButtons.forEach((button) => {
                if (button instanceof HTMLButtonElement || button instanceof HTMLInputElement) {
                    button.disabled = true;
                }
            });

            try {
                const response = await fetch(action, {
                    method: (form.getAttribute('method') || 'POST').toUpperCase(),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: new FormData(form),
                    cache: 'no-store',
                });

                let payload = null;
                try {
                    payload = await response.json();
                } catch (_jsonError) {
                    payload = null;
                }

                if (!response.ok || !payload || payload.ok !== true) {
                    const message = payload && payload.message ? payload.message : 'Falha ao processar a acao.';
                    showModalNotice(message, 'error');
                    return;
                }

                showModalNotice(payload.message || 'Operação executada com sucesso.', 'success');
                if (activeTableId !== null) {
                    await loadModalContent(activeTableId, false);
                }
            } catch (_error) {
                showModalNotice('Falha de comunicacao com o servidor.', 'error');
            } finally {
                submitButtons.forEach((button) => {
                    if (button instanceof HTMLButtonElement || button instanceof HTMLInputElement) {
                        button.disabled = false;
                    }
                });
            }
        });
    }

    const applyFilter = () => {
        const rawQuery = searchInput ? searchInput.value : '';
        const tokens = normalize(rawQuery).split(/\s+/).filter(Boolean);

        let visibleCount = 0;
        let firstVisibleCard = null;

        cards.forEach((card) => {
            card.classList.remove('search-hit');
            const haystack = normalize(card.getAttribute('data-search') || '');
            const match = tokens.every((token) => haystack.includes(token));
            card.style.display = match ? '' : 'none';

            if (match) {
                visibleCount++;
                if (firstVisibleCard === null) {
                    firstVisibleCard = card;
                }
            }
        });

        if (firstVisibleCard) {
            firstVisibleCard.classList.add('search-hit');
        }

        if (searchInfo) {
            if (tokens.length === 0) {
                searchInfo.textContent = 'Digite para filtrar as mesas de forma inteligente.';
            } else {
                searchInfo.textContent = visibleCount > 0
                    ? `Filtro ativo: ${visibleCount} mesa(s) encontrada(s).`
                    : 'Nenhuma mesa encontrada para o filtro informado.';
            }
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
    }

    if (clearButton && searchInput) {
        clearButton.addEventListener('click', () => {
            searchInput.value = '';
            applyFilter();
            searchInput.focus();
        });
    }

    window.setInterval(() => {
        if (document.hidden) {
            return;
        }
        if (modalShell && !modalShell.hidden) {
            return;
        }

        const activeElement = document.activeElement;
        if (activeElement instanceof HTMLElement) {
            const tagName = activeElement.tagName;
            if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
                return;
            }
        }

        window.location.reload();
    }, pageRefreshMs);

    applyFilter();
})();
</script>
