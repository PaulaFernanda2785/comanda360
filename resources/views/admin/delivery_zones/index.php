<?php
$zones = is_array($zones ?? null) ? $zones : [];
$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');

$activeCount = 0;
$inactiveCount = 0;
$totalFee = 0.0;
foreach ($zones as $zone) {
    if (!is_array($zone)) {
        continue;
    }
    $status = trim((string) ($zone['status'] ?? 'inativo'));
    if ($status === 'ativo') {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    $totalFee += (float) ($zone['fee_amount'] ?? 0);
}
?>

<style>
    .zones-page{display:grid;gap:16px}
    .zones-topbar p{margin:6px 0 0;color:#475569}
    .zones-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:12px}
    .zones-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
    .zones-kpi strong{display:block;font-size:22px;line-height:1.1;color:#0f172a}
    .zones-kpi span{display:block;margin-top:6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .zones-create-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
    .zones-create-grid .field{margin:0}
    .zones-filter-card{display:grid;gap:10px}
    .zones-filter-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(180px,220px) minmax(180px,220px) auto;gap:10px;align-items:end}
    .zones-filter-note{margin:0;color:#64748b;font-size:12px}
    .zones-board{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:12px}
    .zone-card{background:#fff;border:1px solid #dbeafe;border-radius:12px;padding:12px;display:grid;gap:10px;box-shadow:0 10px 18px rgba(15,23,42,.06)}
    .zone-card.status-ativo{border-left:4px solid #16a34a}
    .zone-card.status-inativo{border-left:4px solid #64748b}
    .zone-head{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;flex-wrap:wrap}
    .zone-head h3{margin:0;color:#0f172a;font-size:16px;word-break:break-word}
    .zone-head p{margin:6px 0 0;color:#64748b;font-size:12px;word-break:break-word}
    .zone-meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .zone-meta-item{border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;padding:8px}
    .zone-meta-item span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .zone-meta-item strong{display:block;font-size:13px;color:#0f172a;word-break:break-word}
    .zone-description{border:1px dashed #cbd5e1;background:#fff;border-radius:10px;padding:8px;color:#475569;font-size:12px;word-break:break-word}
    .zone-edit{border:1px solid #dbeafe;border-radius:10px;background:#f8fafc;padding:10px;display:grid;gap:10px}
    .zone-edit-summary{cursor:pointer;font-weight:600;color:#1e3a8a}
    .zone-edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .zone-edit-grid .field{margin:0}
    .zone-actions{display:flex;gap:8px;flex-wrap:wrap}
    .zones-empty{border:1px dashed #cbd5e1;border-radius:12px;padding:18px;color:#334155;background:#fff}
    .zones-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .zones-pagination-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .zones-page-btn{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:7px 10px;cursor:pointer;min-width:36px}
    .zones-page-btn[disabled]{opacity:.5;cursor:not-allowed}
    .zones-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    @media (max-width:1180px){.zones-kpi-grid{grid-template-columns:repeat(2,minmax(130px,1fr))}.zones-create-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.zones-filter-grid{grid-template-columns:1fr 1fr}}
    @media (max-width:760px){.zones-board{grid-template-columns:1fr}.zone-meta-grid,.zone-edit-grid,.zones-create-grid,.zones-filter-grid{grid-template-columns:1fr}}
</style>

<div class="zones-page">
    <div class="topbar zones-topbar">
        <div>
            <h1>Zonas e Taxas de Entrega</h1>
            <p>Gestão moderna das zonas, com filtros e paginação para operação rápida.</p>
        </div>
        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/deliveries')) ?>">Ver painel de entregas</a>
    </div>

    <section class="zones-kpi-grid">
        <article class="zones-kpi"><strong><?= count($zones) ?></strong><span>Zonas cadastradas</span></article>
        <article class="zones-kpi"><strong><?= $activeCount ?></strong><span>Ativas</span></article>
        <article class="zones-kpi"><strong><?= $inactiveCount ?></strong><span>Inativas</span></article>
        <article class="zones-kpi"><strong><?= $formatMoney($totalFee) ?></strong><span>Soma das taxas</span></article>
    </section>

    <section class="card">
        <h3 style="margin:0 0 10px">Nova zona</h3>
        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/delivery-zones/store')) ?>">
            <?= form_security_fields('delivery_zones.store') ?>
            <div class="zones-create-grid">
                <div class="field">
                    <label for="zone_new_name">Nome da zona</label>
                    <input id="zone_new_name" name="name" type="text" required placeholder="Ex.: Centro / Norte">
                </div>
                <div class="field">
                    <label for="zone_new_fee">Taxa (R$)</label>
                    <input id="zone_new_fee" name="fee_amount" type="number" min="0" step="0.01" value="0.00" required>
                </div>
                <div class="field">
                    <label for="zone_new_minimum">Pedido mínimo (R$)</label>
                    <input id="zone_new_minimum" name="minimum_order_amount" type="number" min="0" step="0.01" placeholder="Opcional">
                </div>
                <div class="field">
                    <label for="zone_new_status">Status</label>
                    <select id="zone_new_status" name="status">
                        <option value="ativo">Ativa</option>
                        <option value="inativo">Inativa</option>
                    </select>
                </div>
                <div class="field">
                    <label for="zone_new_description">Descrição</label>
                    <input id="zone_new_description" name="description" type="text" placeholder="Opcional">
                </div>
            </div>
            <div style="margin-top:10px">
                <button class="btn" type="submit">Salvar zona</button>
            </div>
        </form>
    </section>

    <section class="card zones-filter-card">
        <div class="zones-filter-grid">
            <div class="field" style="margin:0">
                <label for="zonesSearch">Busca rápida</label>
                <input id="zonesSearch" type="text" placeholder="Nome, descrição, taxa, pedido mínimo...">
            </div>
            <div class="field" style="margin:0">
                <label for="zonesStatusFilter">Status</label>
                <select id="zonesStatusFilter">
                    <option value="">Todos</option>
                    <option value="ativo">Ativas</option>
                    <option value="inativo">Inativas</option>
                </select>
            </div>
            <div class="field" style="margin:0">
                <label for="zonesPeriodFilter">Período</label>
                <select id="zonesPeriodFilter">
                    <option value="all">Todos</option>
                    <option value="today">Hoje</option>
                    <option value="yesterday">Ontem</option>
                    <option value="last7">Últimos 7 dias</option>
                    <option value="last30">Últimos 30 dias</option>
                    <option value="month_current">Mês atual</option>
                    <option value="month_previous">Mês anterior</option>
                    <option value="year_current">Ano atual</option>
                </select>
            </div>
            <button id="zonesClearFilters" class="btn secondary" type="button">Limpar</button>
        </div>
        <p id="zonesFilterInfo" class="zones-filter-note">Filtros ativos para organizar rapidamente as zonas de entrega.</p>
    </section>

    <?php if ($zones === []): ?>
        <div class="zones-empty">Nenhuma zona cadastrada ainda.</div>
    <?php else: ?>
        <section class="zones-board" id="zonesBoard">
            <?php foreach ($zones as $zone): ?>
                <?php
                if (!is_array($zone)) {
                    continue;
                }
                $zoneId = (int) ($zone['id'] ?? 0);
                if ($zoneId <= 0) {
                    continue;
                }
                $zoneName = trim((string) ($zone['name'] ?? '-'));
                $zoneStatus = trim((string) ($zone['status'] ?? 'inativo'));
                if ($zoneStatus === '') {
                    $zoneStatus = 'inativo';
                }
                $zoneDescription = trim((string) ($zone['description'] ?? ''));
                $zoneMinimumRaw = $zone['minimum_order_amount'] !== null ? (float) $zone['minimum_order_amount'] : null;
                $zoneMinimumLabel = $zoneMinimumRaw !== null ? $formatMoney($zoneMinimumRaw) : 'Sem mínimo';
                $createdAt = trim((string) ($zone['created_at'] ?? ''));
                $updatedAt = trim((string) ($zone['updated_at'] ?? ''));
                $searchText = implode(' ', [
                    (string) $zoneId,
                    (string) $zoneName,
                    (string) $zoneStatus,
                    (string) status_label('delivery_zone_status', $zoneStatus),
                    (string) $zoneDescription,
                    number_format((float) ($zone['fee_amount'] ?? 0), 2, '.', ''),
                    $zoneMinimumRaw !== null ? number_format($zoneMinimumRaw, 2, '.', '') : '',
                    (string) $createdAt,
                    (string) $updatedAt,
                ]);
                ?>
                <article
                    class="zone-card status-<?= htmlspecialchars($zoneStatus) ?>"
                    data-zone-card
                    data-status="<?= htmlspecialchars($zoneStatus) ?>"
                    data-created-at="<?= htmlspecialchars($createdAt) ?>"
                    data-search="<?= htmlspecialchars($searchText) ?>"
                >
                    <header class="zone-head">
                        <div>
                            <h3><?= htmlspecialchars($zoneName !== '' ? $zoneName : ('Zona #' . $zoneId)) ?></h3>
                            <p>ID: <?= $zoneId ?> | Criada em: <?= htmlspecialchars($createdAt !== '' ? $createdAt : '-') ?></p>
                        </div>
                        <span class="badge <?= htmlspecialchars(status_badge_class('delivery_zone_status', $zoneStatus)) ?>"><?= htmlspecialchars(status_label('delivery_zone_status', $zoneStatus)) ?></span>
                    </header>

                    <div class="zone-meta-grid">
                        <div class="zone-meta-item">
                            <span>Taxa</span>
                            <strong><?= $formatMoney($zone['fee_amount'] ?? 0) ?></strong>
                        </div>
                        <div class="zone-meta-item">
                            <span>Pedido mínimo</span>
                            <strong><?= htmlspecialchars($zoneMinimumLabel) ?></strong>
                        </div>
                        <div class="zone-meta-item">
                            <span>Atualizada em</span>
                            <strong><?= htmlspecialchars($updatedAt !== '' ? $updatedAt : '-') ?></strong>
                        </div>
                        <div class="zone-meta-item">
                            <span>Status</span>
                            <strong><?= htmlspecialchars(status_label('delivery_zone_status', $zoneStatus)) ?></strong>
                        </div>
                    </div>

                    <?php if ($zoneDescription !== ''): ?>
                        <div class="zone-description"><strong>Descrição:</strong> <?= htmlspecialchars($zoneDescription) ?></div>
                    <?php endif; ?>

                    <details class="zone-edit">
                        <summary class="zone-edit-summary">Editar zona</summary>
                        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/delivery-zones/update')) ?>">
                            <?= form_security_fields('delivery_zones.update') ?>
                            <input type="hidden" name="zone_id" value="<?= $zoneId ?>">
                            <div class="zone-edit-grid">
                                <div class="field">
                                    <label>Nome</label>
                                    <input name="name" type="text" value="<?= htmlspecialchars($zoneName) ?>" required>
                                </div>
                                <div class="field">
                                    <label>Taxa (R$)</label>
                                    <input name="fee_amount" type="number" min="0" step="0.01" value="<?= htmlspecialchars(number_format((float) ($zone['fee_amount'] ?? 0), 2, '.', '')) ?>" required>
                                </div>
                                <div class="field">
                                    <label>Pedido mínimo</label>
                                    <input name="minimum_order_amount" type="number" min="0" step="0.01" value="<?= $zoneMinimumRaw !== null ? htmlspecialchars(number_format($zoneMinimumRaw, 2, '.', '')) : '' ?>" placeholder="Opcional">
                                </div>
                                <div class="field">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="ativo" <?= $zoneStatus === 'ativo' ? 'selected' : '' ?>>Ativa</option>
                                        <option value="inativo" <?= $zoneStatus === 'inativo' ? 'selected' : '' ?>>Inativa</option>
                                    </select>
                                </div>
                                <div class="field" style="grid-column:1 / -1">
                                    <label>Descrição</label>
                                    <input name="description" type="text" value="<?= htmlspecialchars((string) ($zone['description'] ?? '')) ?>" placeholder="Opcional">
                                </div>
                            </div>
                            <div class="zone-actions">
                                <button class="btn secondary" type="submit">Atualizar</button>
                            </div>
                        </form>
                    </details>

                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/delivery-zones/delete')) ?>" onsubmit="return window.confirm('Excluir esta zona de entrega?')">
                        <?= form_security_fields('delivery_zones.delete') ?>
                        <input type="hidden" name="zone_id" value="<?= $zoneId ?>">
                        <button class="btn secondary" type="submit">Excluir</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="card zones-pagination" id="zonesPagination" hidden>
            <span id="zonesPaginationInfo"></span>
            <div id="zonesPaginationControls" class="zones-pagination-controls"></div>
        </section>

        <div id="zonesNoResults" class="zones-empty" hidden>Nenhuma zona encontrada com os filtros atuais.</div>
    <?php endif; ?>
</div>

<script>
(() => {
    const board = document.getElementById('zonesBoard');
    const cards = board ? Array.from(board.querySelectorAll('[data-zone-card]')) : [];
    const searchInput = document.getElementById('zonesSearch');
    const statusFilter = document.getElementById('zonesStatusFilter');
    const periodFilter = document.getElementById('zonesPeriodFilter');
    const clearButton = document.getElementById('zonesClearFilters');
    const filterInfo = document.getElementById('zonesFilterInfo');
    const noResults = document.getElementById('zonesNoResults');
    const pagination = document.getElementById('zonesPagination');
    const paginationInfo = document.getElementById('zonesPaginationInfo');
    const paginationControls = document.getElementById('zonesPaginationControls');

    if (!board || cards.length === 0 || !searchInput || !statusFilter || !periodFilter || !clearButton) {
        return;
    }

    const pageSize = 10;
    let currentPage = 1;
    const normalize = (value) => String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    const parseDate = (value) => {
        const raw = String(value || '').trim();
        if (raw === '') {
            return null;
        }
        const date = new Date(raw.replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const isDateInPeriod = (date, period) => {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return false;
        }
        if (period === 'all') {
            return true;
        }

        const now = new Date();
        const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());

        if (period === 'today') {
            return date >= startOfToday;
        }
        if (period === 'yesterday') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 1);
            return date >= start && date < startOfToday;
        }
        if (period === 'last7') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 6);
            return date >= start;
        }
        if (period === 'last30') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 29);
            return date >= start;
        }
        if (period === 'month_current') {
            return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
        }
        if (period === 'month_previous') {
            const previousMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            return date.getFullYear() === previousMonth.getFullYear() && date.getMonth() === previousMonth.getMonth();
        }
        if (period === 'year_current') {
            return date.getFullYear() === now.getFullYear();
        }
        return true;
    };

    const renderPagination = (totalFiltered) => {
        if (!pagination || !paginationInfo || !paginationControls) {
            return;
        }

        if (totalFiltered <= 0) {
            pagination.hidden = true;
            paginationInfo.textContent = '';
            paginationControls.innerHTML = '';
            return;
        }

        pagination.hidden = false;
        const totalPages = Math.max(1, Math.ceil(totalFiltered / pageSize));
        if (currentPage > totalPages) {
            currentPage = 1;
        }

        const start = (currentPage - 1) * pageSize + 1;
        const end = Math.min(currentPage * pageSize, totalFiltered);
        paginationInfo.textContent = `Mostrando ${start}-${end} de ${totalFiltered} zona(s).`;
        paginationControls.innerHTML = '';

        const addButton = (label, page, disabled, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `zones-page-btn${active ? ' is-active' : ''}`;
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => {
                if (!disabled && page !== currentPage) {
                    currentPage = page;
                    applyFilter(false);
                }
            });
            paginationControls.appendChild(button);
        };

        addButton('Anterior', Math.max(1, currentPage - 1), currentPage <= 1);
        const maxButtons = 5;
        const half = Math.floor(maxButtons / 2);
        let first = Math.max(1, currentPage - half);
        let last = Math.min(totalPages, first + maxButtons - 1);
        first = Math.max(1, last - maxButtons + 1);
        for (let page = first; page <= last; page += 1) {
            addButton(String(page), page, false, page === currentPage);
        }
        addButton('Próxima', Math.min(totalPages, currentPage + 1), currentPage >= totalPages);
    };

    const applyFilter = (resetPage = true) => {
        const searchText = normalize(searchInput.value);
        const statusValue = normalize(statusFilter.value);
        const periodValue = normalize(periodFilter.value || 'all');

        const filteredCards = cards.filter((card) => {
            const cardSearch = normalize(card.getAttribute('data-search'));
            const cardStatus = normalize(card.getAttribute('data-status'));
            const cardDate = parseDate(card.getAttribute('data-created-at'));
            return (searchText === '' || cardSearch.includes(searchText))
                && (statusValue === '' || cardStatus === statusValue)
                && isDateInPeriod(cardDate, periodValue === '' ? 'all' : periodValue);
        });

        if (resetPage) {
            currentPage = 1;
        }

        const from = (currentPage - 1) * pageSize;
        const visible = filteredCards.slice(from, from + pageSize);
        const visibleSet = new Set(visible);
        cards.forEach((card) => {
            card.style.display = visibleSet.has(card) ? '' : 'none';
        });

        if (filterInfo) {
            if (searchText === '' && statusValue === '' && periodValue === 'all') {
                filterInfo.textContent = 'Filtros ativos para organizar rapidamente as zonas de entrega.';
            } else if (filteredCards.length > 0) {
                filterInfo.textContent = `Filtro ativo: ${filteredCards.length} zona(s) encontrada(s).`;
            } else {
                filterInfo.textContent = 'Nenhuma zona encontrada para os filtros aplicados.';
            }
        }

        if (noResults) {
            noResults.hidden = filteredCards.length > 0;
        }

        renderPagination(filteredCards.length);
    };

    searchInput.addEventListener('input', () => applyFilter(true));
    statusFilter.addEventListener('change', () => applyFilter(true));
    periodFilter.addEventListener('change', () => applyFilter(true));
    clearButton.addEventListener('click', () => {
        searchInput.value = '';
        statusFilter.value = '';
        periodFilter.value = 'all';
        applyFilter(true);
        searchInput.focus();
    });

    applyFilter(true);
})();
</script>

