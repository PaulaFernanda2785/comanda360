<?php
$commands = is_array($commands ?? null) ? $commands : [];
$products = is_array($products ?? null) ? $products : [];
$deliveryZones = is_array($deliveryZones ?? null) ? $deliveryZones : [];

$productsJson = json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
if (!is_string($productsJson)) {
    $productsJson = '[]';
}
$deliveryZonesJson = json_encode($deliveryZones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
if (!is_string($deliveryZonesJson)) {
    $deliveryZonesJson = '[]';
}

$totalCommands = count($commands);
$totalProducts = count($products);
$productsWithAdditionals = count(array_filter($products, static fn (array $product): bool => (int) ($product['has_additionals'] ?? 0) === 1));
$canCreateOrder = $totalProducts > 0;
?>

<style>
    .order-create-page{display:grid;gap:16px}
    .kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(120px,1fr));gap:12px}
    .kpi-item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .kpi-item strong{display:block;font-size:24px;line-height:1.1}
    .kpi-item span{color:#64748b;font-size:12px}
    .order-form-layout{display:grid;grid-template-columns:minmax(0,2.18fr) minmax(280px,.62fr);gap:16px;align-items:start}
    .order-form-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
    .steps{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .step-pill{padding:4px 10px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:12px}
    .warning-strip{border:1px solid #fcd34d;background:#fffbeb;border-radius:10px;padding:10px;color:#92400e}
    .section-shell{display:grid;gap:16px}
    .section-block{border:1px solid #dbe3ee;border-radius:18px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);padding:16px}
    .section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}
    .section-head h3{margin:0;color:#0f172a}
    .section-head p{margin:5px 0 0;color:#64748b;font-size:13px;line-height:1.5;max-width:780px}
    .section-kpis{display:flex;gap:8px;flex-wrap:wrap}
    .section-kpi{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:12px;font-weight:700}
    .channel-shell{display:grid;gap:12px}
    .channel-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}
    .channel-card{border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:14px;min-width:0}
    .channel-card h4{margin:0 0 10px;color:#0f172a;font-size:14px}
    .channel-card .field:last-child{margin-bottom:0}
    .channel-card.is-core{grid-column:span 7}
    .channel-card.is-customer{grid-column:span 5}
    .channel-inline-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .channel-note{padding:10px 12px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:12px;line-height:1.5}
    .delivery-fields-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px}
    .delivery-fields-grid .field{grid-column:span 4}
    .delivery-fields-grid .field.is-wide{grid-column:span 12}
    .general-notes-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(220px,.65fr) minmax(220px,.65fr);gap:10px}
    .general-notes-grid .field{margin:0}
    .items-headline{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .items-headline h3{margin:0;color:#0f172a}
    .items-headline p{margin:5px 0 0;color:#64748b;font-size:13px;line-height:1.5;max-width:760px}
    .items-toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:12px 14px;border-radius:14px;background:#eff6ff;border:1px solid #bfdbfe}
    .items-toolbar-copy{font-size:12px;color:#1e3a8a;line-height:1.5}
    .items-table-wrap{border:0;border-radius:16px;overflow:visible;background:transparent}
    .items-table{width:100%;min-width:0;border-collapse:separate;border-spacing:0;margin:0;display:grid;gap:12px;table-layout:auto}
    .items-table colgroup,
    .items-table thead{display:none}
    .items-table tbody{display:grid;gap:12px;width:100%}
    .items-table tr{
        display:grid;
        grid-template-columns:minmax(220px,1fr) minmax(96px,120px) minmax(260px,1.2fr);
        grid-template-areas:
            "product quantity additionals"
            "notes total additionals"
            "action action additionals";
        gap:12px;
        width:100%;
        min-width:0;
        border:1px solid #e2e8f0;
        border-radius:14px;
        background:#f8fafc;
        padding:14px;
        box-shadow:0 8px 18px rgba(15,23,42,.05);
    }
    .items-table td{display:block;border:0;padding:0;min-width:0}
    .items-table td[data-cell="product"]{grid-area:product}
    .items-table td[data-cell="quantity"]{grid-area:quantity}
    .items-table td[data-cell="additionals"]{grid-area:additionals}
    .items-table td[data-cell="notes"]{grid-area:notes}
    .items-table td[data-cell="line-total"]{grid-area:total}
    .items-table td[data-cell="action"]{grid-area:action;align-self:end}
    .items-table td::before{content:attr(data-label);display:block;margin-bottom:6px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;font-weight:700}
    .product-picker{display:grid;gap:6px;position:relative}
    .product-suggestions{position:relative;border:1px solid #dbeafe;border-radius:10px;background:#fff;max-height:170px;overflow:auto}
    .product-suggestions[hidden]{display:none}
    .product-suggestion{width:100%;border:0;border-bottom:1px solid #e2e8f0;background:#fff;text-align:left;padding:8px;display:grid;gap:3px;cursor:pointer}
    .product-suggestion:last-child{border-bottom:0}
    .product-suggestion:hover,.product-suggestion:focus{background:#eff6ff;outline:none}
    .product-suggestion-name{font-size:12px;color:#0f172a;font-weight:600}
    .product-suggestion-meta{font-size:11px;color:#64748b}
    .product-selected-meta{font-size:11px;color:#1e40af}
    .notes-textarea{width:100%;resize:vertical;min-height:48px;max-height:180px;line-height:1.3}
    .notes-textarea.compact{min-height:44px;max-height:130px}
    .additionals-container{border:1px solid #cbd5e1;border-radius:12px;padding:10px;background:#fff;min-width:0;width:100%;max-width:100%}
    .additionals-shell{display:grid;gap:8px}
    .additionals-meta{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
    .additionals-rules{display:flex;gap:6px;flex-wrap:wrap}
    .additional-rule-chip{display:inline-block;padding:3px 8px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:11px}
    .additional-rule-chip.required{background:#fef3c7;color:#92400e}
    .additionals-counter{font-size:11px;color:#1e40af;background:#dbeafe;padding:3px 8px;border-radius:999px}
    .additionals-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:8px;max-height:260px;overflow-y:auto;overflow-x:hidden;padding-right:2px}
    .additional-card{position:relative;display:grid;gap:6px;padding:10px;border:1px solid #dbeafe;border-radius:12px;background:#f8fafc;cursor:pointer;transition:all .15s ease;align-content:start}
    .additional-card:hover{border-color:#93c5fd;background:#eff6ff}
    .additional-card.is-selected{border-color:#1d4ed8;background:#dbeafe;box-shadow:inset 0 0 0 1px #1d4ed8}
    .additional-card input{position:absolute;opacity:0;pointer-events:none}
    .additional-card-name{font-size:12px;color:#0f172a;font-weight:600;line-height:1.25;word-break:break-word}
    .additional-card-price{font-size:11px;color:#475569}
    .additional-card-controls{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;flex-wrap:wrap}
    .additional-toggle{border:1px solid #93c5fd;background:#fff;color:#1d4ed8;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap}
    .additional-card.is-selected .additional-toggle{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .additional-qty{display:inline-flex;align-items:center;gap:6px}
    .additional-qty button{width:24px;height:24px;border-radius:999px;border:1px solid #93c5fd;background:#fff;color:#1d4ed8;font-weight:700;cursor:pointer}
    .additional-qty button:disabled{opacity:.45;cursor:not-allowed}
    .additional-qty strong{min-width:18px;text-align:center;font-size:12px;color:#0f172a}
    .additionals-placeholder{display:block;padding:8px 10px;border-radius:8px;background:#f8fafc;color:#64748b;font-size:12px}
    .line-total{display:inline-block;padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:12px;white-space:nowrap}
    .remove-item{white-space:nowrap;min-width:96px}
    .summary-list{display:grid;gap:8px;margin-top:10px}
    .summary-item{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .summary-item strong{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px}
    .summary-item span{font-size:14px;color:#0f172a}
    .total-highlight{border-color:#bfdbfe;background:#eff6ff}
    .total-highlight span{font-size:18px;font-weight:700;color:#1d4ed8}
    .actions-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
    .channel-hidden{display:none !important}
    @media (max-width:1080px){
        .kpi-grid{grid-template-columns:repeat(3,minmax(120px,1fr))}
        .order-form-layout{grid-template-columns:1fr}
        .channel-card.is-core,.channel-card.is-customer{grid-column:span 12}
        .general-notes-grid{grid-template-columns:1fr}
        .delivery-fields-grid .field{grid-column:span 6}
    }
    @media (max-width:760px){
        .kpi-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}
        .channel-grid,.channel-inline-grid{grid-template-columns:1fr}
        .delivery-fields-grid .field{grid-column:span 12}
        .items-table tr{
            grid-template-columns:minmax(0,1fr) minmax(92px,120px);
            grid-template-areas:
                "product quantity"
                "additionals additionals"
                "notes notes"
                "total action";
        }
        .items-table td[data-cell="action"]{align-self:start}
    }
    @media (max-width:560px){
        .items-table tr{
            grid-template-columns:1fr;
            grid-template-areas:
                "product"
                "quantity"
                "additionals"
                "notes"
                "total"
                "action";
            padding:12px;
        }
        .items-table td[data-cell="action"] .btn{width:100%}
        .additional-card-controls{align-items:flex-start}
    }
    @media (max-width:560px){
        .kpi-grid{grid-template-columns:1fr}
    }
</style>

<div class="order-create-page ops-page">
    <div class="topbar">
        <div>
            <h1>Novo Pedido</h1>
            <p>Cadastro no padrão moderno com resumo operacional em tempo real.</p>
        </div>
        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Voltar</a>
    </div>

    <section class="ops-hero">
        <div class="ops-hero-copy">
            <span class="ops-eyebrow">Registro Operacional</span>
            <h1>Novo Pedido</h1>
            <p>Monte pedidos por canal, configure adicionais e acompanhe o resumo financeiro no mesmo padrão visual das áreas estratégicas do sistema.</p>
            <div class="ops-hero-meta">
                <span class="ops-hero-pill"><?= $totalProducts ?> produtos disponíveis</span>
                <span class="ops-hero-pill"><?= $totalCommands ?> comandas abertas</span>
                <span class="ops-hero-pill"><?= count($deliveryZones) ?> zonas para entrega</span>
            </div>
        </div>
        <div class="ops-hero-actions">
            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Voltar</a>
            <a class="btn" href="#orderCreateForm">Montar pedido</a>
        </div>
    </section>

    <div class="kpi-grid">
        <div class="kpi-item"><strong><?= $totalCommands ?></strong><span>Comandas abertas</span></div>
        <div class="kpi-item"><strong><?= count($deliveryZones) ?></strong><span>Zonas de entrega</span></div>
        <div class="kpi-item"><strong><?= $totalProducts ?></strong><span>Produtos disponiveis</span></div>
        <div class="kpi-item"><strong><?= $productsWithAdditionals ?></strong><span>Produtos com adicionais</span></div>
        <div class="kpi-item"><strong><?= $totalCommands > 0 ? 'OK' : 'Atenção' ?></strong><span>Canal mesa</span></div>
        <div class="kpi-item"><strong><?= count($deliveryZones) > 0 ? 'OK' : 'Atenção' ?></strong><span>Canal entrega</span></div>
        <div class="kpi-item"><strong><?= $canCreateOrder ? 'OK' : 'Pendente' ?></strong><span>Pronto para criar pedido</span></div>
    </div>

    <?php if (!$canCreateOrder): ?>
        <div class="warning-strip">
            <?php if ($totalProducts <= 0): ?>
                Nenhum produto disponivel. Cadastre/ative produtos para continuar.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/store')) ?>" id="orderCreateForm">
        <?= form_security_fields('orders.store') ?>

        <div class="order-form-layout">
            <div class="order-form-card">
                <div class="steps">
                    <span class="step-pill">1. Canal e dados gerais</span>
                    <span class="step-pill">2. Itens e adicionais</span>
                </div>

                <div class="section-shell">
                    <section class="section-block">
                        <div class="section-head">
                            <div>
                                <h3>Canal e dados gerais</h3>
                                <p>Defina o canal do atendimento, o vínculo com a comanda e os dados mínimos para abertura operacional do pedido.</p>
                            </div>
                            <div class="section-kpis">
                                <article class="section-kpi">
                                    <strong><?= count($commands) ?></strong>
                                    <span>comandas abertas</span>
                                </article>
                                <article class="section-kpi">
                                    <strong><?= count($deliveryZones) ?></strong>
                                    <span>zonas de entrega</span>
                                </article>
                            </div>
                        </div>

                        <div class="channel-shell">
                            <div class="channel-grid">
                                <div class="channel-card is-core">
                                    <div class="channel-inline-grid">
                                        <div class="field">
                                            <label for="channel">Canal do pedido</label>
                                            <select id="channel" name="channel" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                                <option value="table">Mesa</option>
                                                <option value="delivery">Entrega</option>
                                                <option value="pickup">Retirada</option>
                                                <option value="counter">Balcão</option>
                                            </select>
                                        </div>
                                        <div class="field" id="commandField">
                                            <label for="command_id">Comanda aberta</label>
                                            <select id="command_id" name="command_id" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                                <option value="">Selecione</option>
                                                <?php foreach ($commands as $command): ?>
                                                    <option value="<?= (int) $command['id'] ?>">
                                                        <?= $command['table_number'] !== null ? 'Mesa ' . (int) $command['table_number'] : 'Comanda sem mesa' ?>
                                                        <?= !empty($command['customer_name']) ? '- ' . htmlspecialchars((string) $command['customer_name']) : '' ?>
                                                        <?= !empty($command['opened_at']) ? '- Aberta em ' . htmlspecialchars((string) $command['opened_at']) : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small id="commandWarning" style="color:#b91c1c;display:none">Sem comanda aberta no momento para canal mesa.</small>
                                        </div>
                                    </div>
                                    <p class="channel-note">Pedidos por mesa exigem uma comanda aberta. Nos demais canais, o fluxo segue sem amarrar o pedido a uma comanda de salão.</p>
                                </div>

                                <div class="channel-card is-customer">
                                    <div class="channel-inline-grid">
                                        <div class="field" id="customerNameField">
                                            <label for="customer_name">Cliente</label>
                                            <input id="customer_name" name="customer_name" type="text" placeholder="Nome do cliente" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                        </div>
                                        <div class="field" id="customerPhoneField">
                                            <label for="customer_phone">Telefone</label>
                                            <input id="customer_phone" name="customer_phone" type="text" placeholder="Opcional (delivery)" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                        </div>
                                    </div>
                                    <p class="channel-note" id="customerChannelNote">Use estes dados para identificar retirada, balcão e entrega sem poluir o restante do formulário operacional.</p>
                                </div>
                            </div>

                            <div class="channel-card delivery-fields-grid channel-hidden" id="deliveryFields">
                                <div class="field">
                                    <label for="delivery_zone_id">Zona de entrega</label>
                                    <select id="delivery_zone_id" name="delivery_zone_id" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                        <option value="">Selecione</option>
                                        <?php foreach ($deliveryZones as $zone): ?>
                                            <option
                                                value="<?= (int) ($zone['id'] ?? 0) ?>"
                                                data-fee="<?= htmlspecialchars(number_format((float) ($zone['fee_amount'] ?? 0), 2, '.', '')) ?>"
                                                data-minimum="<?= $zone['minimum_order_amount'] !== null ? htmlspecialchars(number_format((float) $zone['minimum_order_amount'], 2, '.', '')) : '' ?>"
                                            >
                                                <?= htmlspecialchars((string) ($zone['name'] ?? 'Zona')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="deliveryZoneRule" style="color:#475569"></small>
                                </div>
                                <div class="field">
                                    <label for="delivery_label">Rótulo</label>
                                    <input id="delivery_label" name="delivery_label" type="text" placeholder="Casa, Trabalho..." <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                                <div class="field">
                                    <label for="delivery_street">Logradouro</label>
                                    <input id="delivery_street" name="delivery_street" type="text" placeholder="Rua / Avenida" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                                <div class="field">
                                    <label for="delivery_number">Número</label>
                                    <input id="delivery_number" name="delivery_number" type="text" placeholder="123" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                                <div class="field">
                                    <label for="delivery_complement">Complemento</label>
                                    <input id="delivery_complement" name="delivery_complement" type="text" placeholder="Opcional" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                                <div class="field">
                                    <label for="delivery_neighborhood">Bairro</label>
                                    <input id="delivery_neighborhood" name="delivery_neighborhood" type="text" placeholder="Bairro" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                                <div class="field">
                                    <label for="delivery_city">Cidade</label>
                                    <input id="delivery_city" name="delivery_city" type="text" placeholder="Cidade" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                                <div class="field">
                                    <label for="delivery_state">UF</label>
                                    <input id="delivery_state" name="delivery_state" type="text" maxlength="2" placeholder="UF" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                                <div class="field">
                                    <label for="delivery_zip_code">CEP</label>
                                    <input id="delivery_zip_code" name="delivery_zip_code" type="text" placeholder="Opcional" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                                <div class="field is-wide">
                                    <label for="delivery_reference">Referência / observação da entrega</label>
                                    <input id="delivery_reference" name="delivery_reference" type="text" placeholder="Opcional" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                </div>
                            </div>

                            <div class="channel-card">
                                <div class="general-notes-grid">
                                    <div class="field">
                                        <label for="notes">Observações gerais</label>
                                        <textarea id="notes" name="notes" class="notes-textarea" rows="2" placeholder="Opcional" <?= $canCreateOrder ? '' : 'disabled' ?>></textarea>
                                    </div>
                                    <div class="field">
                                        <label for="discount_amount">Desconto (R$)</label>
                                        <input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0" value="0.00" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                    </div>
                                    <div class="field">
                                        <label for="delivery_fee">Taxa de entrega (R$)</label>
                                        <input id="delivery_fee" name="delivery_fee" type="number" step="0.01" min="0" value="0.00" <?= $canCreateOrder ? '' : 'disabled' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="section-block">
                        <div class="items-headline">
                            <div>
                                <h3>Itens e adicionais</h3>
                                <p>Monte o pedido por linha com mais espaço para busca do produto, controle de quantidade, observações e composição dos adicionais.</p>
                            </div>
                        </div>

                        <div class="items-toolbar">
                            <div class="items-toolbar-copy">
                                <strong>Montagem do pedido</strong>
                                <span>Os adicionais continuam obedecendo as regras de mínimo e máximo definidas no produto, agora com leitura mais clara na operação.</span>
                            </div>
                            <button class="btn secondary" id="addItemBtn" type="button" <?= $canCreateOrder ? '' : 'disabled' ?>>Adicionar item</button>
                        </div>

                        <div class="items-table-wrap">
                            <table class="items-table" id="itemsTable">
                                <colgroup>
                                    <col style="width:25%">
                                    <col style="width:7%">
                                    <col style="width:33%">
                                    <col style="width:16%">
                                    <col style="width:10%">
                                    <col style="width:9%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Qtd</th>
                                        <th>Adicionais</th>
                                        <th>Observação</th>
                                        <th>Total linha</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody"></tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            <div class="order-form-card">
                <div class="steps">
                    <span class="step-pill">3. Resumo operacional</span>
                </div>

                <div class="summary-list">
                    <div class="summary-item">
                        <strong>Canal</strong>
                        <span id="summaryChannel">Mesa</span>
                    </div>
                    <div class="summary-item">
                        <strong>Comanda selecionada</strong>
                        <span id="summaryCommand">Não aplicável</span>
                    </div>
                    <div class="summary-item">
                        <strong>Itens totais</strong>
                        <span id="summaryItems">0</span>
                    </div>
                    <div class="summary-item">
                        <strong>Subtotal produtos</strong>
                        <span id="summaryBase">R$ 0,00</span>
                    </div>
                    <div class="summary-item">
                        <strong>Subtotal adicionais</strong>
                        <span id="summaryAdditionals">R$ 0,00</span>
                    </div>
                    <div class="summary-item">
                        <strong>Desconto</strong>
                        <span id="summaryDiscount">R$ 0,00</span>
                    </div>
                    <div class="summary-item">
                        <strong>Taxa de entrega</strong>
                        <span id="summaryDelivery">R$ 0,00</span>
                    </div>
                    <div class="summary-item total-highlight">
                        <strong>Total previsto</strong>
                        <span id="summaryTotal">R$ 0,00</span>
                    </div>
                    <div class="summary-item">
                        <strong>Observacao geral</strong>
                        <span id="summaryNotes">Sem observações.</span>
                    </div>
                </div>

                <div class="actions-row">
                    <button class="btn" id="submitOrderBtn" type="submit" <?= $canCreateOrder ? '' : 'disabled' ?>>Criar pedido</button>
                    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(() => {
    const products = <?= $productsJson ?>;
    const deliveryZones = <?= $deliveryZonesJson ?>;
    const canCreateOrder = <?= $canCreateOrder ? 'true' : 'false' ?>;
    const productsById = {};
    const deliveryZonesById = {};
    const tbody = document.getElementById('itemsBody');
    const addItemBtn = document.getElementById('addItemBtn');
    const channelSelect = document.getElementById('channel');
    const commandSelect = document.getElementById('command_id');
    const commandField = document.getElementById('commandField');
    const commandWarning = document.getElementById('commandWarning');
    const customerNameField = document.getElementById('customerNameField');
    const customerNameInput = document.getElementById('customer_name');
    const customerNameLabel = customerNameField ? customerNameField.querySelector('label[for="customer_name"]') : null;
    const customerPhoneField = document.getElementById('customerPhoneField');
    const customerPhoneInput = document.getElementById('customer_phone');
    const customerChannelNote = document.getElementById('customerChannelNote');
    const deliveryFields = document.getElementById('deliveryFields');
    const deliveryZoneSelect = document.getElementById('delivery_zone_id');
    const deliveryZoneRule = document.getElementById('deliveryZoneRule');
    const deliveryStreetInput = document.getElementById('delivery_street');
    const deliveryNumberInput = document.getElementById('delivery_number');
    const deliveryNeighborhoodInput = document.getElementById('delivery_neighborhood');
    const deliveryCityInput = document.getElementById('delivery_city');
    const deliveryStateInput = document.getElementById('delivery_state');
    const notesInput = document.getElementById('notes');
    const discountInput = document.getElementById('discount_amount');
    const deliveryInput = document.getElementById('delivery_fee');
    const summaryChannel = document.getElementById('summaryChannel');
    const summaryCommand = document.getElementById('summaryCommand');
    const summaryItems = document.getElementById('summaryItems');
    const summaryBase = document.getElementById('summaryBase');
    const summaryAdditionals = document.getElementById('summaryAdditionals');
    const summaryDiscount = document.getElementById('summaryDiscount');
    const summaryDelivery = document.getElementById('summaryDelivery');
    const summaryTotal = document.getElementById('summaryTotal');
    const summaryNotes = document.getElementById('summaryNotes');
    const form = document.getElementById('orderCreateForm');
    const submitOrderBtn = document.getElementById('submitOrderBtn');

    const money = (value) => `R$ ${Number(value || 0).toFixed(2).replace('.', ',')}`;
    const parseMoney = (value) => {
        const parsed = Number(String(value || '').replace(',', '.'));
        return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
    };
    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const normalizeSearchText = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    const effectivePrice = (product) => {
        if (!product || typeof product !== 'object') {
            return 0;
        }
        if (product.promotional_price !== null && product.promotional_price !== undefined && product.promotional_price !== '') {
            return Number(product.promotional_price || 0);
        }
        return Number(product.price || 0);
    };

    products.forEach((product) => {
        const productId = Number(product.id || 0);
        if (productId > 0) {
            productsById[productId] = product;
        }
    });
    deliveryZones.forEach((zone) => {
        const zoneId = Number(zone.id || 0);
        if (zoneId > 0) {
            deliveryZonesById[zoneId] = zone;
        }
    });

    const channelLabel = (channel) => {
        if (channel === 'delivery') return 'Entrega';
        if (channel === 'pickup') return 'Retirada';
        if (channel === 'counter') return 'Balcão';
        return 'Mesa';
    };
    const productSearchIndex = products
        .map((product) => {
            const productId = Number(product.id || 0);
            if (productId <= 0) {
                return null;
            }

            const name = String(product.name || 'Produto');
            const category = String(product.category_name || '');
            const label = category !== '' ? `${name} (${category})` : name;
            const searchText = normalizeSearchText(`${name} ${category}`);
            const searchName = normalizeSearchText(name);
            return { productId, label, searchText, searchName };
        })
        .filter(Boolean);

    const additionalPrice = (product, additionalId) => {
        if (!product || !Array.isArray(product.additionals)) {
            return 0;
        }
        const found = product.additionals.find((item) => Number(item.id || 0) === Number(additionalId || 0));
        return found ? Number(found.price || 0) : 0;
    };

    const isSubsequence = (needle, haystack) => {
        if (!needle || !haystack) {
            return false;
        }
        let index = 0;
        for (let i = 0; i < haystack.length && index < needle.length; i++) {
            if (haystack[i] === needle[index]) {
                index++;
            }
        }
        return index === needle.length;
    };

    const findProductMatches = (queryRaw) => {
        const query = normalizeSearchText(queryRaw);
        if (query === '') {
            return productSearchIndex.slice(0, 10).map((entry) => productsById[entry.productId]).filter(Boolean);
        }

        const tokens = query.split(/\s+/).filter(Boolean);
        const scored = [];

        productSearchIndex.forEach((entry) => {
            if (!entry) {
                return;
            }

            let score = 0;
            const startsWithName = entry.searchName.startsWith(query);
            const includesName = entry.searchName.includes(query);
            const includesAllTokens = tokens.every((token) => entry.searchText.includes(token));
            const fuzzyByLetters = isSubsequence(query, entry.searchText.replace(/\s+/g, ''));

            if (startsWithName) score += 120;
            if (includesName) score += 70;
            if (includesAllTokens) score += 50;
            if (fuzzyByLetters) score += 25;

            if (score <= 0) {
                return;
            }

            scored.push({
                productId: entry.productId,
                score,
            });
        });

        scored.sort((left, right) => {
            if (right.score !== left.score) {
                return right.score - left.score;
            }
            const leftName = String(productsById[left.productId]?.name || '');
            const rightName = String(productsById[right.productId]?.name || '');
            return leftName.localeCompare(rightName, 'pt-BR');
        });

        return scored.slice(0, 12)
            .map((entry) => productsById[entry.productId])
            .filter(Boolean);
    };

    const productDisplayLabel = (product) => {
        if (!product) {
            return '';
        }
        const safeName = String(product.name || 'Produto');
        const category = String(product.category_name || '');
        return category !== '' ? `${safeName} (${category})` : safeName;
    };

    const setupProductPicker = (row) => {
        const picker = row.querySelector('.product-picker');
        const searchInput = row.querySelector('.product-search-input');
        const hiddenInput = row.querySelector('input[name="product_id[]"]');
        const suggestions = row.querySelector('.product-suggestions');
        const selectedMeta = row.querySelector('.product-selected-meta');

        if (!picker || !searchInput || !hiddenInput || !suggestions || !selectedMeta) {
            return;
        }

        const closeSuggestions = () => {
            suggestions.hidden = true;
        };

        const setSelectedProduct = (product) => {
            if (!product) {
                hiddenInput.value = '';
                searchInput.value = '';
                selectedMeta.textContent = '';
                renderAdditionals(row);
                refreshSummary();
                return;
            }

            const productId = Number(product.id || 0);
            if (productId <= 0) {
                return;
            }

            hiddenInput.value = String(productId);
            searchInput.value = productDisplayLabel(product);
            selectedMeta.textContent = `Selecionado: ${productDisplayLabel(product)} - ${money(effectivePrice(product))}`;
            closeSuggestions();
            renderAdditionals(row);
            refreshSummary();
        };

        const renderSuggestions = (query) => {
            const matches = findProductMatches(query);
            if (matches.length === 0) {
                suggestions.innerHTML = '<div class="product-suggestion"><span class="product-suggestion-meta">Nenhum produto encontrado.</span></div>';
                suggestions.hidden = false;
                return;
            }

            suggestions.innerHTML = matches.map((product) => {
                const productId = Number(product.id || 0);
                const label = escapeHtml(productDisplayLabel(product));
                const category = String(product.category_name || '').trim();
                const categoryText = category !== '' ? `Categoria: ${escapeHtml(category)} | ` : '';
                const meta = `${categoryText}Preco: ${escapeHtml(money(effectivePrice(product)))}`;
                return `
                    <button class="product-suggestion" type="button" data-product-id="${productId}">
                        <span class="product-suggestion-name">${label}</span>
                        <span class="product-suggestion-meta">${meta}</span>
                    </button>
                `;
            }).join('');
            suggestions.hidden = false;
        };

        searchInput.addEventListener('input', () => {
            const query = String(searchInput.value || '').trim();
            hiddenInput.value = '';
            selectedMeta.textContent = '';
            renderAdditionals(row);
            refreshSummary();

            if (query === '') {
                closeSuggestions();
                return;
            }
            renderSuggestions(query);
        });

        searchInput.addEventListener('focus', () => {
            const query = String(searchInput.value || '').trim();
            if (query !== '') {
                renderSuggestions(query);
            }
        });

        searchInput.addEventListener('blur', () => {
            window.setTimeout(closeSuggestions, 120);
        });

        suggestions.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        suggestions.addEventListener('click', (event) => {
            const target = event.target instanceof HTMLElement ? event.target.closest('[data-product-id]') : null;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const productId = Number(target.getAttribute('data-product-id') || 0);
            const product = productsById[productId] || null;
            if (!product) {
                return;
            }
            setSelectedProduct(product);
        });

        const initialProductId = Number(hiddenInput.value || 0);
        if (initialProductId > 0 && productsById[initialProductId]) {
            setSelectedProduct(productsById[initialProductId]);
        }
    };

    const readAdditionalSelections = (container) => {
        const selections = [];
        container.querySelectorAll('.additional-card[data-additional-id]').forEach((card) => {
            const additionalId = Number(card.getAttribute('data-additional-id') || 0);
            const checkbox = card.querySelector('input[type="checkbox"][data-additional-id]');
            const qtyLabel = card.querySelector('[data-additional-qty]');
            const qty = Math.max(0, Number(qtyLabel ? qtyLabel.textContent || 0 : 0));
            if (additionalId > 0 && checkbox && checkbox.checked && qty > 0) {
                selections.push({ id: additionalId, quantity: qty });
            }
        });

        return selections;
    };

    const totalAdditionalUnits = (container) => readAdditionalSelections(container)
        .reduce((sum, selection) => sum + Number(selection.quantity || 0), 0);

    const encodeAdditionalSelections = (container) => readAdditionalSelections(container)
        .map((selection) => `${selection.id}:${selection.quantity}`)
        .join(',');

    const syncAdditionalSelectionUi = (container) => {
        const selections = readAdditionalSelections(container);
        const selectedCount = selections.length;
        const totalUnits = selections.reduce((sum, selection) => sum + Number(selection.quantity || 0), 0);

        container.querySelectorAll('.additional-card').forEach((card) => {
            const checkbox = card.querySelector('input[type="checkbox"][data-additional-id]');
            const qtyLabel = card.querySelector('[data-additional-qty]');
            const decreaseButton = card.querySelector('[data-additional-decrease]');
            const increaseButton = card.querySelector('[data-additional-increase]');
            const toggleButton = card.querySelector('[data-additional-toggle]');
            const isSelected = Boolean(checkbox && checkbox.checked);
            const qty = Math.max(0, Number(qtyLabel ? qtyLabel.textContent || 0 : 0));

            card.classList.toggle('is-selected', isSelected);
            if (toggleButton) {
                toggleButton.textContent = isSelected ? 'Remover' : 'Adicionar';
            }
            if (decreaseButton) {
                decreaseButton.disabled = !isSelected || qty <= 1;
            }
            if (increaseButton) {
                increaseButton.disabled = !isSelected;
            }
        });

        const counter = container.querySelector('[data-selected-count]');
        if (counter) {
            counter.textContent = `${selectedCount} adicional(is) · ${totalUnits} unidade(s)`;
        }
    };

    const syncAdditionalHidden = (row) => {
        const hidden = row.querySelector('input[name="additional_item_ids[]"]');
        const container = row.querySelector('.additionals-container');
        if (!hidden || !container) {
            return;
        }
        hidden.value = encodeAdditionalSelections(container);
    };

    const lineTotals = (row) => {
        const productIdInput = row.querySelector('input[name="product_id[]"]');
        const qtyInput = row.querySelector('input[name="quantity[]"]');
        const hidden = row.querySelector('input[name="additional_item_ids[]"]');
        const productId = Number(productIdInput ? productIdInput.value || 0 : 0);
        const quantity = Math.max(1, Number(qtyInput ? qtyInput.value || 1 : 1));
        const product = productsById[productId] || null;
        const base = product ? effectivePrice(product) * quantity : 0;
        let additionals = 0;

        if (product && hidden) {
            String(hidden.value || '').split(',').forEach((token) => {
                const [idPart, qtyPart] = String(token || '').split(':');
                const additionalId = Number(idPart || 0);
                const additionalQty = Math.max(0, Number(qtyPart !== undefined && qtyPart !== '' ? qtyPart : 1));
                if (additionalId > 0 && additionalQty > 0) {
                    additionals += additionalPrice(product, additionalId) * additionalQty * quantity;
                }
            });
        }

        const lineBadge = row.querySelector('.item-line-total');
        if (lineBadge) {
            lineBadge.textContent = money(base + additionals);
        }

        return { quantity: product ? quantity : 0, base, additionals };
    };

    const updateDeliveryZoneRule = () => {
        if (!deliveryZoneSelect || !deliveryZoneRule) {
            return;
        }

        const zoneId = Number(deliveryZoneSelect.value || 0);
        const zone = deliveryZonesById[zoneId] || null;
        if (!zone) {
            deliveryZoneRule.textContent = '';
            return;
        }

        const fee = Number(zone.fee_amount || 0);
        const minimum = zone.minimum_order_amount !== null && zone.minimum_order_amount !== undefined && zone.minimum_order_amount !== ''
            ? Number(zone.minimum_order_amount || 0)
            : null;
        deliveryZoneRule.textContent = minimum !== null
            ? `Taxa ${money(fee)} | Pedido minimo ${money(minimum)}`
            : `Taxa ${money(fee)} | Sem pedido minimo`;
    };

    const isTableChannelAvailable = () => {
        if (!commandSelect || !commandSelect.options) {
            return false;
        }
        return commandSelect.options.length > 1;
    };

    const setElementRequired = (element, required) => {
        if (!element) {
            return;
        }
        element.required = required;
    };

    const updateChannelUi = () => {
        const channel = String(channelSelect ? channelSelect.value : 'table');
        const isTable = channel === 'table';
        const isDelivery = channel === 'delivery';
        const isPickup = channel === 'pickup';
        const isCounter = channel === 'counter';

        if (commandField) {
            commandField.classList.toggle('channel-hidden', !isTable);
        }
        if (customerNameField) {
            customerNameField.classList.toggle('channel-hidden', isTable);
        }
        if (customerPhoneField) {
            customerPhoneField.classList.toggle('channel-hidden', !isDelivery);
        }
        if (deliveryFields) {
            deliveryFields.classList.toggle('channel-hidden', !isDelivery);
        }

        setElementRequired(commandSelect, isTable);
        setElementRequired(customerNameInput, isDelivery || isPickup || isCounter);
        setElementRequired(deliveryZoneSelect, isDelivery);
        setElementRequired(deliveryStreetInput, isDelivery);
        setElementRequired(deliveryNumberInput, isDelivery);
        setElementRequired(deliveryNeighborhoodInput, isDelivery);
        setElementRequired(deliveryCityInput, isDelivery);
        setElementRequired(deliveryStateInput, isDelivery);

        if (customerNameLabel) {
            customerNameLabel.textContent = isDelivery
                ? 'Cliente / recebedor'
                : (isPickup || isCounter ? 'Cliente obrigatório' : 'Cliente');
        }

        if (customerNameInput) {
            customerNameInput.placeholder = isPickup
                ? 'Nome obrigatório para retirada'
                : (isCounter
                    ? 'Nome obrigatório para balcão'
                    : (isDelivery ? 'Nome do cliente que vai receber' : 'Nome do cliente'));
        }

        if (customerChannelNote) {
            customerChannelNote.textContent = isDelivery
                ? 'Entrega exige nome do cliente, telefone e endereço completo para expedição.'
                : (isPickup
                    ? 'Retirada exige nome do cliente para identificação no balcão.'
                    : (isCounter
                        ? 'Balcão também exige nome do cliente para rastreabilidade e conferência de pagamento.'
                        : 'Use estes dados para identificar retirada, balcão e entrega sem poluir o restante do formulário operacional.'));
        }

        if (isTable && commandWarning) {
            const unavailable = !isTableChannelAvailable();
            commandWarning.style.display = unavailable ? '' : 'none';
            if (submitOrderBtn) {
                submitOrderBtn.disabled = unavailable || !canCreateOrder;
            }
        } else if (submitOrderBtn) {
            submitOrderBtn.disabled = !canCreateOrder;
        }

        if (!isDelivery && deliveryZoneSelect) {
            deliveryZoneSelect.value = '';
            updateDeliveryZoneRule();
            if (channel === 'pickup' || channel === 'counter') {
                if (deliveryInput) {
                    deliveryInput.value = '0.00';
                }
            }
        }

        if (isDelivery && deliveryZoneSelect) {
            const zoneId = Number(deliveryZoneSelect.value || 0);
            const zone = deliveryZonesById[zoneId] || null;
            if (zone && deliveryInput) {
                deliveryInput.value = Number(zone.fee_amount || 0).toFixed(2);
            }
            updateDeliveryZoneRule();
        }

        if (deliveryInput) {
            deliveryInput.readOnly = isDelivery;
        }
    };

    const refreshSummary = () => {
        let totalItems = 0;
        let totalBase = 0;
        let totalAdditionals = 0;

        Array.from(tbody.querySelectorAll('tr')).forEach((row) => {
            const line = lineTotals(row);
            totalItems += line.quantity;
            totalBase += line.base;
            totalAdditionals += line.additionals;
        });

        const discount = parseMoney(discountInput ? discountInput.value : 0);
        const delivery = parseMoney(deliveryInput ? deliveryInput.value : 0);
        const total = Math.max(0, (totalBase + totalAdditionals) - discount + delivery);

        if (summaryItems) summaryItems.textContent = String(totalItems);
        if (summaryBase) summaryBase.textContent = money(totalBase);
        if (summaryAdditionals) summaryAdditionals.textContent = money(totalAdditionals);
        if (summaryDiscount) summaryDiscount.textContent = money(discount);
        if (summaryDelivery) summaryDelivery.textContent = money(delivery);
        if (summaryTotal) summaryTotal.textContent = money(total);

        if (summaryChannel && channelSelect) {
            summaryChannel.textContent = channelLabel(String(channelSelect.value || 'table'));
        }

        if (summaryCommand && commandSelect) {
            const channel = String(channelSelect ? channelSelect.value : 'table');
            if (channel !== 'table') {
                summaryCommand.textContent = 'Não aplicável';
            } else {
                const label = commandSelect.options[commandSelect.selectedIndex]?.textContent || '';
                summaryCommand.textContent = commandSelect.value ? String(label).trim() : 'Não selecionada';
            }
        }

        if (summaryNotes && notesInput) {
            const notes = String(notesInput.value || '').trim();
            summaryNotes.textContent = notes !== '' ? notes : 'Sem observações.';
        }
    };

    const setAdditionalQuantity = (container, card, quantity, maxSelection) => {
        const checkbox = card.querySelector('input[type="checkbox"][data-additional-id]');
        const qtyLabel = card.querySelector('[data-additional-qty]');
        if (!checkbox || !qtyLabel) {
            return false;
        }

        const currentSelections = readAdditionalSelections(container);
        const currentCardId = Number(card.getAttribute('data-additional-id') || 0);
        const currentSelection = currentSelections.find((selection) => selection.id === currentCardId) || { quantity: 0 };
        const currentTotal = currentSelections.reduce((sum, selection) => sum + Number(selection.quantity || 0), 0);
        const normalizedQuantity = Math.max(0, Number(quantity || 0));
        const nextTotal = currentTotal - Number(currentSelection.quantity || 0) + normalizedQuantity;

        if (maxSelection !== null && nextTotal > maxSelection) {
            alert(`Este produto permite no maximo ${maxSelection} unidade(s) de adicionais por item.`);
            return false;
        }

        checkbox.checked = normalizedQuantity > 0;
        qtyLabel.textContent = String(normalizedQuantity > 0 ? normalizedQuantity : 0);
        syncAdditionalSelectionUi(container);
        return true;
    };

    const renderAdditionals = (row) => {
        const productInput = row.querySelector('input[name="product_id[]"]');
        const additionalsContainer = row.querySelector('.additionals-container');
        const hidden = row.querySelector('input[name="additional_item_ids[]"]');
        if (!productInput || !additionalsContainer || !hidden) {
            return;
        }

        hidden.value = '';
        const productId = Number(productInput.value || 0);
        const product = productsById[productId] || null;

        if (!product || !Array.isArray(product.additionals) || product.additionals.length === 0) {
            additionalsContainer.innerHTML = '<span class="additionals-placeholder">Sem adicionais disponiveis.</span>';
            refreshSummary();
            return;
        }

        const maxSelection = product.additionals_max_selection !== null ? Number(product.additionals_max_selection) : null;
        const minSelection = product.additionals_min_selection !== null ? Number(product.additionals_min_selection) : 0;
        const isRequired = Boolean(product.additionals_is_required);

        let rulesHtml = '';
        const maxLabel = maxSelection !== null ? String(maxSelection) : 'Sem limite';
        rulesHtml += `<span class="additional-rule-chip">Max: ${maxLabel}</span>`;
        rulesHtml += `<span class="additional-rule-chip">Min: ${minSelection}</span>`;
        if (isRequired) {
            rulesHtml += '<span class="additional-rule-chip required">Obrigatorio</span>';
        }

        let cardsHtml = '';
        product.additionals.forEach((additional) => {
            const additionalId = Number(additional.id || 0);
            const additionalName = escapeHtml(additional.name || 'Adicional');
            const additionalPriceText = money(Number(additional.price || 0));
            cardsHtml += `
                <div class="additional-card" data-additional-id="${additionalId}">
                    <input type="checkbox" data-additional-id="${additionalId}">
                    <span class="additional-card-name">${additionalName}</span>
                    <span class="additional-card-price">+ ${additionalPriceText}</span>
                    <div class="additional-card-controls">
                        <button class="additional-toggle" type="button" data-additional-toggle>Adicionar</button>
                        <div class="additional-qty">
                            <button type="button" data-additional-decrease disabled>-</button>
                            <strong data-additional-qty>0</strong>
                            <button type="button" data-additional-increase disabled>+</button>
                        </div>
                    </div>
                </div>
            `;
        });

        additionalsContainer.innerHTML = `
            <div class="additionals-shell">
                <div class="additionals-meta">
                    <div class="additionals-rules">${rulesHtml}</div>
                    <span class="additionals-counter" data-selected-count>0 selecionado(s)</span>
                </div>
                <div class="additionals-grid">${cardsHtml}</div>
            </div>
        `;

        additionalsContainer.querySelectorAll('.additional-card[data-additional-id]').forEach((card) => {
            const toggleButton = card.querySelector('[data-additional-toggle]');
            const decreaseButton = card.querySelector('[data-additional-decrease]');
            const increaseButton = card.querySelector('[data-additional-increase]');

            toggleButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const checkbox = card.querySelector('input[type="checkbox"][data-additional-id]');
                const isSelected = Boolean(checkbox && checkbox.checked);
                const nextQuantity = isSelected ? 0 : 1;
                if (setAdditionalQuantity(additionalsContainer, card, nextQuantity, maxSelection)) {
                    syncAdditionalHidden(row);
                    refreshSummary();
                }
            });

            decreaseButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const qtyLabel = card.querySelector('[data-additional-qty]');
                const currentQty = Math.max(0, Number(qtyLabel ? qtyLabel.textContent || 0 : 0));
                if (setAdditionalQuantity(additionalsContainer, card, currentQty - 1, maxSelection)) {
                    syncAdditionalHidden(row);
                    refreshSummary();
                }
            });

            increaseButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const qtyLabel = card.querySelector('[data-additional-qty]');
                const currentQty = Math.max(0, Number(qtyLabel ? qtyLabel.textContent || 0 : 0));
                if (setAdditionalQuantity(additionalsContainer, card, currentQty + 1, maxSelection)) {
                    syncAdditionalHidden(row);
                    refreshSummary();
                }
            });
        });

        syncAdditionalSelectionUi(additionalsContainer);
        refreshSummary();
    };

    const addRow = () => {
        if (!canCreateOrder || products.length === 0) {
            return;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td data-label="Produto" data-cell="product">
                <div class="product-picker">
                    <input class="product-search-input" type="text" placeholder="Digite nome/categoria do produto" autocomplete="off">
                    <input name="product_id[]" type="hidden" value="">
                    <small class="product-selected-meta"></small>
                    <div class="product-suggestions" hidden></div>
                </div>
            </td>
            <td data-label="Quantidade" data-cell="quantity">
                <input name="quantity[]" type="number" min="1" step="1" value="1" required>
            </td>
            <td data-label="Adicionais" data-cell="additionals">
                <div class="additionals-container">
                    <span class="additionals-placeholder">Selecione um produto para ver os adicionais.</span>
                </div>
                <input type="hidden" name="additional_item_ids[]" value="">
            </td>
            <td data-label="Observacao" data-cell="notes">
                <textarea name="item_notes[]" class="notes-textarea compact" rows="2" placeholder="Opcional"></textarea>
            </td>
            <td data-label="Total da linha" data-cell="line-total">
                <span class="line-total item-line-total">R$ 0,00</span>
            </td>
            <td data-label="Ação" data-cell="action">
                <button class="btn secondary remove-item" type="button">Remover</button>
            </td>
        `;
        tbody.appendChild(tr);

        const quantity = tr.querySelector('input[name="quantity[]"]');
        setupProductPicker(tr);

        if (quantity) {
            quantity.addEventListener('input', refreshSummary);
        }

        refreshSummary();
    };

    if (addItemBtn) {
        addItemBtn.addEventListener('click', addRow);
    }

    if (tbody) {
        tbody.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement) || !target.classList.contains('remove-item')) {
                return;
            }
            const row = target.closest('tr');
            if (row) {
                row.remove();
            }
            if (tbody.children.length === 0 && products.length > 0) {
                addRow();
            }
            refreshSummary();
        });
    }

    [channelSelect, commandSelect, notesInput, discountInput, deliveryInput, customerNameInput, customerPhoneInput].forEach((input) => {
        if (input) {
            input.addEventListener('input', refreshSummary);
            input.addEventListener('change', refreshSummary);
        }
    });

    if (channelSelect) {
        channelSelect.addEventListener('change', () => {
            updateChannelUi();
            refreshSummary();
        });
    }

    if (deliveryZoneSelect) {
        deliveryZoneSelect.addEventListener('change', () => {
            const zoneId = Number(deliveryZoneSelect.value || 0);
            const zone = deliveryZonesById[zoneId] || null;
            if (zone && deliveryInput) {
                deliveryInput.value = Number(zone.fee_amount || 0).toFixed(2);
            }
            updateDeliveryZoneRule();
            refreshSummary();
        });
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            const channel = String(channelSelect ? channelSelect.value : 'table');
            if (channel === 'table') {
                if (!commandSelect || String(commandSelect.value || '') === '') {
                    event.preventDefault();
                    alert('Selecione uma comanda para pedidos no canal mesa.');
                    return;
                }
            } else if (channel === 'delivery') {
                if (!deliveryZoneSelect || String(deliveryZoneSelect.value || '') === '') {
                    event.preventDefault();
                    alert('Selecione a zona de entrega.');
                    return;
                }
                if (!customerNameInput || String(customerNameInput.value || '').trim() === '') {
                    event.preventDefault();
                    alert('Informe o nome do cliente para entrega.');
                    return;
                }
                const requiredAddressInputs = [deliveryStreetInput, deliveryNumberInput, deliveryNeighborhoodInput, deliveryCityInput, deliveryStateInput];
                const hasMissingAddress = requiredAddressInputs.some((input) => !input || String(input.value || '').trim() === '');
                if (hasMissingAddress) {
                    event.preventDefault();
                    alert('Preencha os campos obrigatorios do endereco de entrega.');
                    return;
                }
            } else if (channel === 'pickup') {
                if (!customerNameInput || String(customerNameInput.value || '').trim() === '') {
                    event.preventDefault();
                    alert('Informe o nome do cliente para retirada.');
                    return;
                }
            }

            const rows = Array.from(tbody.querySelectorAll('tr'));
            for (const row of rows) {
                const productIdInput = row.querySelector('input[name="product_id[]"]');
                const hidden = row.querySelector('input[name="additional_item_ids[]"]');
                const productId = Number(productIdInput ? productIdInput.value || 0 : 0);
                if (productId <= 0) {
                    event.preventDefault();
                    alert('Selecione um produto valido em todas as linhas do pedido.');
                    return;
                }
                const product = productsById[productId] || null;
                if (!product || !hidden) {
                    continue;
                }

                const selectedCount = String(hidden.value || '').split(',').reduce((sum, token) => {
                    const [, qtyPart] = String(token || '').split(':');
                    return sum + Math.max(0, Number(qtyPart !== undefined && qtyPart !== '' ? qtyPart : 1));
                }, 0);
                const isRequired = Boolean(product.additionals_is_required);
                const minSelection = product.additionals_min_selection !== null ? Number(product.additionals_min_selection) : (isRequired ? 1 : 0);
                if (minSelection > 0 && selectedCount < minSelection) {
                    event.preventDefault();
                    alert(`O produto ${product.name || 'selecionado'} exige ao menos ${minSelection} adicional(is).`);
                    return;
                }
            }
        });
    }

    if (canCreateOrder && products.length > 0 && tbody.children.length === 0) {
        addRow();
    }
    updateChannelUi();
    updateDeliveryZoneRule();
    refreshSummary();
})();
</script>
