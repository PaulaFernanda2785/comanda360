<?php
declare(strict_types=1);

namespace App\Services\Guest;

use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Repositories\AppShellRepository;
use App\Repositories\CommandRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusHistoryRepository;
use App\Repositories\TableRepository;
use App\Services\Admin\CommandService;
use App\Services\Admin\CompanyPlanFeatureService;
use App\Services\Admin\OrderService;
use App\Services\Admin\ProductService;

final class DigitalMenuService
{
    private const SESSION_KEY = 'digital_menu_access_sessions';
    private const REFRESH_INTERVAL_SECONDS = 1200;
    private const SESSION_COMMAND_TTL_SECONDS = 43200;
    private const MAX_PUBLIC_CUSTOMER_NAME_LENGTH = 120;
    private const MAX_PUBLIC_COMMAND_NOTES_LENGTH = 255;

    public function __construct(
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly TableRepository $tables = new TableRepository(),
        private readonly AppShellRepository $shell = new AppShellRepository(),
        private readonly CommandRepository $commands = new CommandRepository(),
        private readonly CommandService $commandService = new CommandService(),
        private readonly ProductService $products = new ProductService(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly OrderItemRepository $orderItems = new OrderItemRepository(),
        private readonly OrderStatusHistoryRepository $statusHistory = new OrderStatusHistoryRepository(),
        private readonly OrderService $orderService = new OrderService(),
        private readonly CompanyPlanFeatureService $planFeatures = new CompanyPlanFeatureService()
    ) {}

    public function defaultTheme(): array
    {
        return [
            'company_name' => 'Estabelecimento',
            'title' => 'Menu digital',
            'description' => '',
            'primary_color' => '#1d4ed8',
            'secondary_color' => '#0f172a',
            'accent_color' => '#0ea5e9',
            'main_card_color' => '#0f172a',
            'logo_path' => '',
            'banner_path' => '',
            'footer_text' => 'Comanda360 - Atendimento digital da mesa.',
        ];
    }

    public function entry(array $input): array
    {
        $access = $this->resolveAccess($input);
        $companyId = (int) ($access['company']['id'] ?? 0);
        $tableId = (int) ($access['table']['id'] ?? 0);
        $currentCommand = $this->activeCommandForAccess($access);
        $currentCommandId = (int) ($currentCommand['id'] ?? 0);

        $products = $this->products->listForOrderForm($companyId);
        $categories = $this->groupProductsByCategory($products);
        $tableCommands = $this->tableCommandsPanel($companyId, $tableId, $currentCommandId);
        $tableSummary = $this->buildTableSummary($tableCommands);

        return [
            'access' => $access,
            'menuTheme' => $access['theme'],
            'categories' => $categories,
            'products' => $products,
            'currentCommand' => $currentCommand,
            'currentCommandPanel' => $this->findCurrentCommandPanel($tableCommands, $currentCommandId),
            'tableCommands' => $tableCommands,
            'tableSummary' => $tableSummary,
            'openCommandsCount' => count($tableCommands),
            'refreshIntervalSeconds' => self::REFRESH_INTERVAL_SECONDS,
        ];
    }

    public function openCommand(array $input): int
    {
        $access = $this->resolveAccess($input);
        $activeCommand = $this->activeCommandForAccess($access);
        if ($activeCommand !== null) {
            return (int) ($activeCommand['id'] ?? 0);
        }

        $companyId = (int) ($access['company']['id'] ?? 0);
        $tableId = (int) ($access['table']['id'] ?? 0);
        $commandId = $this->commandService->open($companyId, 0, [
            'table_id' => $tableId,
            'customer_name' => $this->normalizePublicText(
                $input['customer_name'] ?? '',
                self::MAX_PUBLIC_CUSTOMER_NAME_LENGTH,
                true,
                'Informe o nome do cliente.'
            ),
            'notes' => $this->normalizePublicText(
                $input['notes'] ?? null,
                self::MAX_PUBLIC_COMMAND_NOTES_LENGTH,
                false,
                'Observacao da comanda invalida.'
            ),
        ]);

        $this->storeSessionCommand($access, $commandId);
        return $commandId;
    }

    public function createOrder(array $input): int
    {
        $access = $this->resolveAccess($input);
        $command = $this->requireActiveCommand($access);
        $companyId = (int) ($access['company']['id'] ?? 0);
        $featureState = is_array($access['feature_state'] ?? null) ? $access['feature_state'] : [];

        return $this->orderService->create($companyId, 0, [
            'channel' => 'table',
            'command_id' => (int) ($command['id'] ?? 0),
            'notes' => $input['notes'] ?? null,
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'product_id' => $input['product_id'] ?? [],
            'quantity' => $input['quantity'] ?? [],
            'item_notes' => $input['item_notes'] ?? [],
            'additional_item_ids' => $input['additional_item_ids'] ?? [],
            'auto_send_kitchen' => !empty($featureState['cozinha']),
        ]);
    }

    public function ticketContext(array $input): array
    {
        $access = $this->resolveAccess($input);
        $companyId = (int) ($access['company']['id'] ?? 0);
        $commandId = (int) ($input['command_id'] ?? 0);
        $orderId = (int) ($input['order_id'] ?? 0);
        $scope = strtolower(trim((string) ($input['scope'] ?? 'order')));

        if ($scope === 'table') {
            $ticket = $this->tableTicket($companyId, (int) ($access['table']['id'] ?? 0));
            return [
                'access' => $access,
                'menuTheme' => $access['theme'],
                'ticket' => $ticket,
                'ticketScopeLabel' => 'Ticket geral da mesa',
                'ticketBackLabel' => 'Voltar ao menu da mesa',
            ];
        }

        if ($scope === 'command' || $commandId > 0) {
            $ticket = $this->commandTicket($companyId, (int) ($access['table']['id'] ?? 0), $commandId);
            return [
                'access' => $access,
                'menuTheme' => $access['theme'],
                'ticket' => $ticket,
                'ticketScopeLabel' => 'Ticket da comanda',
                'ticketBackLabel' => 'Voltar às comandas da mesa',
            ];
        }

        $currentCommand = $this->requireActiveCommand($access);
        $currentCommandId = (int) ($currentCommand['id'] ?? 0);
        $order = $this->orders->findByIdForCommand($companyId, $currentCommandId, $orderId);
        if ($order === null) {
            throw new ValidationException('Ticket indisponível para esta comanda.');
        }

        return [
            'access' => $access,
            'menuTheme' => $access['theme'],
            'ticket' => $this->orderService->ticketPrintContext($companyId, $orderId),
            'ticketScopeLabel' => 'Ticket do pedido',
            'ticketBackLabel' => 'Voltar ao acompanhamento',
        ];
    }

    private function resolveAccess(array $input): array
    {
        $companySlug = strtolower(trim((string) ($input['empresa'] ?? '')));
        $tableNumber = (int) ($input['mesa'] ?? 0);
        $token = trim((string) ($input['token'] ?? ''));

        if ($companySlug === '' || $tableNumber <= 0 || $token === '') {
            throw new ValidationException('QR da mesa inválido ou incompleto.');
        }

        $company = $this->companies->findPublicBySlug($companySlug);
        if ($company === null) {
            throw new ValidationException('Empresa não encontrada para este QR.');
        }

        $companyStatus = strtolower(trim((string) ($company['status'] ?? '')));
        if (!in_array($companyStatus, ['ativa', 'teste'], true)) {
            throw new ValidationException('O menu digital desta empresa está indisponível no momento.');
        }

        $companyId = (int) ($company['id'] ?? 0);
        $featureState = $this->planFeatures->featureStateForCompany($companyId);
        foreach (['cardapio_digital', 'qrcode_mesa', 'comandas'] as $featureKey) {
            if (empty($featureState[$featureKey])) {
                throw new ValidationException('O plano desta empresa não libera o menu digital por QR Code.');
            }
        }

        $table = $this->tables->findByNumberAndTokenForPublic($companyId, $tableNumber, $token);
        if ($table === null) {
            throw new ValidationException('Mesa não encontrada para o QR informado.');
        }

        if (strtolower(trim((string) ($table['status'] ?? ''))) === 'bloqueada') {
            throw new ValidationException('Esta mesa está temporariamente bloqueada para pedidos.');
        }

        return [
            'company' => $company,
            'table' => $table,
            'token' => $token,
            'feature_state' => $featureState,
            'theme' => $this->resolveTheme($companyId),
        ];
    }

    private function resolveTheme(int $companyId): array
    {
        $theme = $this->defaultTheme();
        $profile = $this->shell->findCompanyShellConfig($companyId);
        if ($profile === null) {
            return $theme;
        }

        $companyName = trim((string) ($profile['name'] ?? ''));
        $title = trim((string) ($profile['title'] ?? ''));
        $description = trim((string) ($profile['description'] ?? ''));
        $footerText = trim((string) ($profile['footer_text'] ?? ''));

        $theme['company_name'] = $companyName !== '' ? $companyName : $theme['company_name'];
        $theme['title'] = $title !== '' ? $title : ($companyName !== '' ? $companyName : $theme['title']);
        $theme['description'] = $description;
        $theme['footer_text'] = $footerText !== '' ? $footerText : $theme['footer_text'];
        $theme['primary_color'] = $this->normalizeColor($profile['primary_color'] ?? null, $theme['primary_color']);
        $theme['secondary_color'] = $this->normalizeColor($profile['secondary_color'] ?? null, $theme['secondary_color']);
        $theme['accent_color'] = $this->normalizeColor($profile['accent_color'] ?? null, $theme['accent_color']);
        $theme['main_card_color'] = $this->normalizeColor($profile['main_card_color'] ?? null, $theme['main_card_color']);
        $theme['logo_path'] = trim((string) ($profile['logo_path'] ?? ''));
        $theme['banner_path'] = trim((string) ($profile['banner_path'] ?? ''));

        return $theme;
    }

    private function normalizeColor(mixed $value, string $fallback): string
    {
        $color = strtolower(trim((string) ($value ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
            return $fallback;
        }

        return $color;
    }

    private function groupProductsByCategory(array $products): array
    {
        $grouped = [];

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $categoryName = trim((string) ($product['category_name'] ?? 'Cardápio'));
            $categoryKey = $this->categoryKey($product, $categoryName);
            if (!isset($grouped[$categoryKey])) {
                $grouped[$categoryKey] = [
                    'key' => $categoryKey,
                    'name' => $categoryName !== '' ? $categoryName : 'Cardápio',
                    'products' => [],
                    'products_count' => 0,
                ];
            }

            $grouped[$categoryKey]['products'][] = $product;
            $grouped[$categoryKey]['products_count']++;
        }

        return array_values($grouped);
    }

    private function activeCommandForAccess(array $access): ?array
    {
        $scope = $this->sessionScope($access);
        $sessions = Session::get(self::SESSION_KEY, []);
        if (!is_array($sessions)) {
            $sessions = [];
        }

        $sessionData = is_array($sessions[$scope] ?? null) ? $sessions[$scope] : null;
        $commandId = (int) ($sessionData['command_id'] ?? 0);
        if ($commandId <= 0) {
            return null;
        }

        $storedAt = (int) ($sessionData['stored_at'] ?? 0);
        if ($storedAt <= 0 || (time() - $storedAt) > self::SESSION_COMMAND_TTL_SECONDS) {
            $this->clearSessionCommand($access);
            return null;
        }

        $companyId = (int) ($access['company']['id'] ?? 0);
        $tableId = (int) ($access['table']['id'] ?? 0);
        $command = $this->commands->findOpenById($companyId, $commandId);

        if ($command === null || (int) ($command['table_id'] ?? 0) !== $tableId) {
            $this->clearSessionCommand($access);
            return null;
        }

        return $command;
    }

    private function requireActiveCommand(array $access): array
    {
        $command = $this->activeCommandForAccess($access);
        if ($command === null) {
            throw new ValidationException('Abra sua comanda nesta mesa antes de enviar pedidos.');
        }

        return $command;
    }

    private function storeSessionCommand(array $access, int $commandId): void
    {
        $scope = $this->sessionScope($access);
        $sessions = Session::get(self::SESSION_KEY, []);
        if (!is_array($sessions)) {
            $sessions = [];
        }

        $sessions[$scope] = [
            'command_id' => $commandId,
            'stored_at' => time(),
        ];

        Session::put(self::SESSION_KEY, $sessions);
    }

    private function clearSessionCommand(array $access): void
    {
        $scope = $this->sessionScope($access);
        $sessions = Session::get(self::SESSION_KEY, []);
        if (!is_array($sessions) || !isset($sessions[$scope])) {
            return;
        }

        unset($sessions[$scope]);
        Session::put(self::SESSION_KEY, $sessions);
    }

    private function sessionScope(array $access): string
    {
        return implode(':', [
            (int) ($access['company']['id'] ?? 0),
            (int) ($access['table']['id'] ?? 0),
            hash('sha256', (string) ($access['token'] ?? '')),
        ]);
    }

    private function categoryKey(array $product, string $categoryName): string
    {
        $rawSlug = strtolower(trim((string) ($product['category_slug'] ?? '')));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $rawSlug);
        $slug = trim((string) $slug, '-');
        if ($slug !== '') {
            return $slug;
        }

        $fallbackBase = strtolower(trim($categoryName !== '' ? $categoryName : 'cardapio'));
        $transliterated = function_exists('iconv')
            ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fallbackBase) ?: $fallbackBase)
            : $fallbackBase;
        $fallback = preg_replace('/[^a-z0-9]+/', '-', $transliterated);
        $fallback = trim((string) $fallback, '-');

        return $fallback !== '' ? $fallback : 'cardapio';
    }

    private function normalizePublicText(mixed $value, int $maxLength, bool $required, string $requiredMessage): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            if ($required) {
                throw new ValidationException($requiredMessage);
            }

            return null;
        }

        $text = function_exists('mb_substr')
            ? mb_substr($text, 0, $maxLength)
            : substr($text, 0, $maxLength);
        $text = trim($text);

        if ($text === '') {
            if ($required) {
                throw new ValidationException($requiredMessage);
            }

            return null;
        }

        return $text;
    }

    private function tableCommandsPanel(int $companyId, int $tableId, int $currentCommandId): array
    {
        $commands = $this->commands->openCommandsByTable($companyId, $tableId);
        if ($commands === []) {
            return [];
        }

        $commandIds = array_values(array_filter(array_map(
            static fn (array $command): int => (int) ($command['id'] ?? 0),
            $commands
        )));
        $orders = $this->orders->allByCommandIds($companyId, $commandIds);
        $ordersByCommandId = [];
        foreach ($orders as $order) {
            $commandId = (int) ($order['command_id'] ?? 0);
            if ($commandId <= 0) {
                continue;
            }
            if (!isset($ordersByCommandId[$commandId])) {
                $ordersByCommandId[$commandId] = [];
            }
            $ordersByCommandId[$commandId][] = $order;
        }

        $panels = [];
        foreach ($commands as $command) {
            $commandId = (int) ($command['id'] ?? 0);
            if ($commandId <= 0) {
                continue;
            }

            $panel = $this->trackingPanel($companyId, $commandId, $ordersByCommandId[$commandId] ?? null);
            $summary = is_array($panel['summary'] ?? null) ? $panel['summary'] : [];
            $panels[] = [
                'command' => $command,
                'summary' => $summary,
                'orders' => is_array($panel['orders'] ?? null) ? $panel['orders'] : [],
                'is_current' => $currentCommandId > 0 && $commandId === $currentCommandId,
                'has_orders' => !empty($summary['total_orders']),
            ];
        }

        return $panels;
    }

    private function findCurrentCommandPanel(array $tableCommands, int $currentCommandId): array
    {
        foreach ($tableCommands as $panel) {
            if (!is_array($panel)) {
                continue;
            }

            $command = is_array($panel['command'] ?? null) ? $panel['command'] : [];
            if ((int) ($command['id'] ?? 0) === $currentCommandId) {
                return $panel;
            }
        }

        return [
            'command' => null,
            'summary' => $this->emptyTrackingPanel()['summary'],
            'orders' => [],
            'is_current' => false,
            'has_orders' => false,
        ];
    }

    private function buildTableSummary(array $tableCommands): array
    {
        $summary = [
            'commands_count' => 0,
            'orders_count' => 0,
            'active_orders' => 0,
            'total_amount' => 0.0,
            'pending' => 0,
            'received' => 0,
            'preparing' => 0,
            'ready' => 0,
            'delivered' => 0,
        ];

        foreach ($tableCommands as $panel) {
            if (!is_array($panel)) {
                continue;
            }

            $summary['commands_count']++;
            $commandSummary = is_array($panel['summary'] ?? null) ? $panel['summary'] : [];
            $summary['orders_count'] += (int) ($commandSummary['total_orders'] ?? 0);
            $summary['active_orders'] += (int) ($commandSummary['active_orders'] ?? 0);
            $summary['total_amount'] = round($summary['total_amount'] + (float) ($commandSummary['total_amount'] ?? 0), 2);

            foreach (['pending', 'received', 'preparing', 'ready', 'delivered'] as $key) {
                $summary[$key] += (int) ($commandSummary[$key] ?? 0);
            }
        }

        return $summary;
    }

    private function commandTicket(int $companyId, int $tableId, int $commandId): array
    {
        if ($commandId <= 0) {
            throw new ValidationException('Comanda inválida para geração do ticket.');
        }

        $command = $this->commands->findOpenById($companyId, $commandId);
        if ($command === null || (int) ($command['table_id'] ?? 0) !== $tableId) {
            throw new ValidationException('A comanda informada não pertence a esta mesa.');
        }

        $orders = $this->orders->allByCommand($companyId, $commandId);
        $orderIds = array_values(array_filter(array_map(
            static fn (array $order): int => (int) ($order['id'] ?? 0),
            $orders
        )));
        if ($orderIds === []) {
            throw new ValidationException('Esta comanda ainda não possui pedidos para ticket.');
        }

        return $this->orderService->ticketPrintContextByOrderIds($companyId, $orderIds);
    }

    private function tableTicket(int $companyId, int $tableId): array
    {
        $commands = $this->commands->openCommandsByTable($companyId, $tableId);
        $commandIds = array_values(array_filter(array_map(
            static fn (array $command): int => (int) ($command['id'] ?? 0),
            $commands
        )));
        if ($commandIds === []) {
            throw new ValidationException('Não existem comandas abertas nesta mesa para emitir um ticket geral.');
        }

        $orders = $this->orders->allByCommandIds($companyId, $commandIds);
        $orderIds = array_values(array_filter(array_map(
            static fn (array $order): int => (int) ($order['id'] ?? 0),
            $orders
        )));
        if ($orderIds === []) {
            throw new ValidationException('Não existem pedidos ativos nesta mesa para gerar o ticket geral.');
        }

        return $this->orderService->ticketPrintContextByOrderIds($companyId, $orderIds);
    }

    private function trackingPanel(int $companyId, int $commandId, ?array $orders = null): array
    {
        $orders = is_array($orders) ? $orders : $this->orders->allByCommand($companyId, $commandId);
        if ($orders === []) {
            return $this->emptyTrackingPanel();
        }

        $orderIds = array_values(array_filter(array_map(
            static fn (array $order): int => (int) ($order['id'] ?? 0),
            $orders
        )));
        $latestHistory = $this->statusHistory->latestByOrderIds($companyId, $orderIds);
        $itemRows = $this->orderItems->activeItemsByOrderIds($companyId, $orderIds);
        $orderItemIds = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $itemRows
        )));
        $additionalRows = $this->orderItems->additionalsByOrderItemIds($companyId, $orderItemIds);
        $additionalsByOrderItemId = $this->indexAdditionalsByOrderItemId($additionalRows);
        $itemsByOrderId = $this->indexItemsByOrderId($itemRows, $additionalsByOrderItemId);

        $summary = [
            'total_orders' => count($orders),
            'active_orders' => 0,
            'total_amount' => 0.0,
            'pending' => 0,
            'received' => 0,
            'preparing' => 0,
            'ready' => 0,
            'delivered' => 0,
            'finished' => 0,
            'canceled' => 0,
        ];

        foreach ($orders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            $status = strtolower(trim((string) ($order['status'] ?? 'pending')));
            $summary['total_amount'] = round($summary['total_amount'] + (float) ($order['total_amount'] ?? 0), 2);
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
            if (!in_array($status, ['finished', 'canceled'], true)) {
                $summary['active_orders']++;
            }

            $history = is_array($latestHistory[$orderId] ?? null) ? $latestHistory[$orderId] : null;
            $order['latest_status_changed_at'] = $history['changed_at'] ?? null;
            $order['latest_status_note'] = $history['notes'] ?? null;
            $order['items'] = is_array($itemsByOrderId[$orderId] ?? null) ? $itemsByOrderId[$orderId] : [];
        }
        unset($order);

        return [
            'summary' => $summary,
            'orders' => $orders,
        ];
    }

    private function emptyTrackingPanel(): array
    {
        return [
            'summary' => [
                'total_orders' => 0,
                'active_orders' => 0,
                'total_amount' => 0.0,
                'pending' => 0,
                'received' => 0,
                'preparing' => 0,
                'ready' => 0,
                'delivered' => 0,
                'finished' => 0,
                'canceled' => 0,
            ],
            'orders' => [],
        ];
    }

    private function indexAdditionalsByOrderItemId(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $orderItemId = (int) ($row['order_item_id'] ?? 0);
            if ($orderItemId <= 0) {
                continue;
            }

            if (!isset($indexed[$orderItemId])) {
                $indexed[$orderItemId] = [];
            }

            $indexed[$orderItemId][] = [
                'name' => (string) ($row['additional_name_snapshot'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'line_subtotal' => (float) ($row['line_subtotal'] ?? 0),
            ];
        }

        return $indexed;
    }

    private function indexItemsByOrderId(array $rows, array $additionalsByOrderItemId): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $orderId = (int) ($row['order_id'] ?? 0);
            $orderItemId = (int) ($row['id'] ?? 0);
            if ($orderId <= 0 || $orderItemId <= 0) {
                continue;
            }

            if (!isset($indexed[$orderId])) {
                $indexed[$orderId] = [];
            }

            $indexed[$orderId][] = [
                'id' => $orderItemId,
                'name' => (string) ($row['product_name_snapshot'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'line_subtotal' => (float) ($row['line_subtotal'] ?? 0),
                'notes' => trim((string) ($row['notes'] ?? '')),
                'additionals' => is_array($additionalsByOrderItemId[$orderItemId] ?? null)
                    ? $additionalsByOrderItemId[$orderItemId]
                    : [],
            ];
        }

        return $indexed;
    }
}
