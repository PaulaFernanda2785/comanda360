<div class="topbar">
    <div>
        <h1>Produtos</h1>
        <p>Listagem inicial do módulo administrativo de produtos.</p>
    </div>
    <a class="btn" href="<?= htmlspecialchars(base_url('/admin/products/create')) ?>">Novo produto</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Produto</th>
                <th>Categoria</th>
                <th>Preço</th>
                <th>Promoção</th>
                <th>Ativo</th>
                <th>Pausado</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($products)): ?>
            <tr>
                <td colspan="7">Nenhum produto encontrado.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= (int)$product['id'] ?></td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                    <td>R$ <?= number_format((float)$product['price'], 2, ',', '.') ?></td>
                    <td>
                        <?= $product['promotional_price'] !== null
                            ? 'R$ ' . number_format((float)$product['promotional_price'], 2, ',', '.')
                            : '-' ?>
                    </td>
                    <td><?= (int)$product['is_active'] === 1 ? 'Sim' : 'Não' ?></td>
                    <td><?= (int)$product['is_paused'] === 1 ? 'Sim' : 'Não' ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
