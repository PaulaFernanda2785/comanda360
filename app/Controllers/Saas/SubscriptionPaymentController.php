<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Saas\SubscriptionPaymentService;

final class SubscriptionPaymentController extends Controller
{
    public function __construct(
        private readonly SubscriptionPaymentService $service = new SubscriptionPaymentService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('saas/subscription_payments/index', [
            'title' => 'Cobrancas SaaS',
            'user' => $user,
            'paymentPanel' => $this->service->panel($request->query),
        ], 'layouts/saas');
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('saas/subscription_payments/create', [
            'title' => 'Nova Cobranca',
            'user' => $user,
            'subscriptions' => $this->service->subscriptionsForBilling(),
        ], 'layouts/saas');
    }

    public function store(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.store', '/saas/subscription-payments/create');
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->createCharge($request->all());
            return $this->backWithSuccess('Cobranca criada com sucesso. Agora use "Gerar PIX no gateway" na lista para transformar essa cobranca em PIX real e entrar no fluxo automatico.', '/saas/subscription-payments');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/saas/subscription-payments/create');
        }
    }

    public function markPaid(Request $request): Response
    {
        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $redirectTo = $this->resolvePaymentsRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.mark_paid.' . $paymentId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->markPaid($request->all());
            return $this->backWithSuccess('Cobranca marcada como paga.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function markOverdue(Request $request): Response
    {
        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $redirectTo = $this->resolvePaymentsRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.mark_overdue.' . $paymentId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->markOverdue($request->all());
            return $this->backWithSuccess('Cobranca marcada como vencida.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function cancel(Request $request): Response
    {
        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $redirectTo = $this->resolvePaymentsRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.cancel.' . $paymentId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->cancel($request->all());
            return $this->backWithSuccess('Cobranca cancelada com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function syncGateway(Request $request): Response
    {
        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $redirectTo = $this->resolvePaymentsRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.sync_gateway.' . $paymentId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->syncGateway($request->all());
            return $this->backWithSuccess('Cobranca sincronizada com o gateway.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function generateGatewayPix(Request $request): Response
    {
        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $redirectTo = $this->resolvePaymentsRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.generate_gateway_pix.' . $paymentId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->generateGatewayPix($request->all());
            return $this->backWithSuccess('PIX real gerado no gateway. Essa cobranca agora entrou no fluxo automatico.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    private function resolvePaymentsRedirect(Request $request): string
    {
        $default = '/saas/subscription-payments';
        $queryRaw = trim((string) ($request->input('return_query', '')));
        if ($queryRaw === '') {
            return $default;
        }

        parse_str($queryRaw, $params);
        if (!is_array($params)) {
            return $default;
        }

        $allowedKeys = [
            'search',
            'status',
            'payment_page',
        ];

        $safe = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $params)) {
                $safe[$key] = (string) $params[$key];
            }
        }

        $query = http_build_query($safe);
        return '/saas/subscription-payments' . ($query !== '' ? '?' . $query : '');
    }
}
