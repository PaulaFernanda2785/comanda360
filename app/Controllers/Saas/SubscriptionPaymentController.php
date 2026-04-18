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
        $filters = $this->service->filters($request->query);

        return $this->view('saas/subscription_payments/index', [
            'title' => 'Cobrancas SaaS',
            'user' => $user,
            'summary' => $this->service->summary(),
            'filters' => $filters,
            'subscriptionPayments' => $this->service->list($filters),
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
            return $this->backWithSuccess('Cobranca criada com sucesso.', '/saas/subscription-payments');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/saas/subscription-payments/create');
        }
    }

    public function markPaid(Request $request): Response
    {
        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.mark_paid.' . $paymentId, '/saas/subscription-payments');
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->markPaid($request->all());
            return $this->backWithSuccess('Cobranca marcada como paga.', '/saas/subscription-payments');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/saas/subscription-payments');
        }
    }

    public function markOverdue(Request $request): Response
    {
        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.mark_overdue.' . $paymentId, '/saas/subscription-payments');
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->markOverdue($request->all());
            return $this->backWithSuccess('Cobranca marcada como vencida.', '/saas/subscription-payments');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/saas/subscription-payments');
        }
    }

    public function cancel(Request $request): Response
    {
        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $guard = $this->guardSingleSubmit($request, 'saas.subscription_payments.cancel.' . $paymentId, '/saas/subscription-payments');
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->cancel($request->all());
            return $this->backWithSuccess('Cobranca cancelada com sucesso.', '/saas/subscription-payments');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/saas/subscription-payments');
        }
    }
}
