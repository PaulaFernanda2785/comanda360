<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Guest\DigitalMenuService;

final class DigitalMenuController extends Controller
{
    public function __construct(
        private readonly DigitalMenuService $service = new DigitalMenuService()
    ) {}

    public function index(Request $request): Response
    {
        try {
            $payload = $this->service->entry($request->all());

            return $this->view('digital_menu/index', [
                'title' => 'Menu digital da mesa',
            ] + $payload, 'layouts/digital_menu');
        } catch (ValidationException $e) {
            return $this->view('digital_menu/index', [
                'title' => 'Menu digital da mesa',
                'menuTheme' => $this->service->defaultTheme(),
                'fatalError' => $e->getMessage(),
                'access' => [],
                'categories' => [],
                'products' => [],
                'currentCommand' => null,
                'currentCommandPanel' => [
                    'command' => null,
                    'summary' => [],
                    'orders' => [],
                    'is_current' => false,
                    'has_orders' => false,
                ],
                'tableCommands' => [],
                'tableSummary' => [],
                'openCommandsCount' => 0,
                'refreshIntervalSeconds' => 1200,
            ], 'layouts/digital_menu');
        }
    }

    public function openCommand(Request $request): Response
    {
        $redirectTo = $this->menuUrl($request);
        $guard = $this->guardSingleSubmit($request, 'digital_menu.command.open', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }
        $rateLimit = $this->guardPublicRateLimit(
            $request,
            'digital_menu.command.open.' . $this->tableRateKey($request),
            12,
            3600,
            $redirectTo
        );
        if ($rateLimit !== null) {
            return $rateLimit;
        }

        try {
            $this->service->openCommand($request->all());
            return $this->backWithSuccess('Comanda aberta com sucesso. Agora você já pode montar seus pedidos.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function cart(Request $request): Response
    {
        try {
            $payload = $this->service->entry($request->all());

            return $this->view('digital_menu/checkout', [
                'title' => 'Carrinho da mesa',
            ] + $payload, 'layouts/digital_menu');
        } catch (ValidationException $e) {
            return $this->view('digital_menu/checkout', [
                'title' => 'Carrinho da mesa',
                'menuTheme' => $this->service->defaultTheme(),
                'fatalError' => $e->getMessage(),
                'access' => [],
                'categories' => [],
                'products' => [],
                'currentCommand' => null,
                'currentCommandPanel' => [
                    'command' => null,
                    'summary' => [],
                    'orders' => [],
                    'is_current' => false,
                    'has_orders' => false,
                ],
                'tableCommands' => [],
                'tableSummary' => [],
                'openCommandsCount' => 0,
                'refreshIntervalSeconds' => 1200,
            ], 'layouts/digital_menu');
        }
    }

    public function storeOrder(Request $request): Response
    {
        $redirectTo = $this->menuUrl($request);
        $guard = $this->guardSingleSubmit($request, 'digital_menu.order.store', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }
        $rateLimit = $this->guardPublicRateLimit(
            $request,
            'digital_menu.order.store.' . $this->tableRateKey($request),
            30,
            600,
            $redirectTo
        );
        if ($rateLimit !== null) {
            return $rateLimit;
        }

        try {
            $orderId = $this->service->createOrder($request->all());
            return $this->backWithSuccess(
                'Pedido enviado com sucesso. Acompanhe o andamento logo abaixo.',
                $this->menuUrl($request, ['last_order_id' => $orderId])
            );
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function ticket(Request $request): Response
    {
        $redirectTo = $this->menuUrl($request);

        try {
            $payload = $this->service->ticketContext($request->all());

            return $this->view('digital_menu/ticket', [
                'title' => 'Ticket da mesa',
            ] + $payload, 'layouts/digital_menu');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    private function menuUrl(Request $request, array $extra = []): string
    {
        $params = [
            'empresa' => trim((string) ($request->input('empresa', ''))),
            'mesa' => (int) ($request->input('mesa', 0)),
            'token' => trim((string) ($request->input('token', ''))),
        ];

        foreach ($extra as $key => $value) {
            $params[$key] = $value;
        }

        return base_url('/menu-digital?' . http_build_query($params));
    }

    private function tableRateKey(Request $request): string
    {
        return hash('sha256', implode('|', [
            trim((string) ($request->input('empresa', ''))),
            (string) ((int) ($request->input('mesa', 0))),
            trim((string) ($request->input('token', ''))),
        ]));
    }
}
