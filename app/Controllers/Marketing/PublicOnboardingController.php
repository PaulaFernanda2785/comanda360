<?php
declare(strict_types=1);

namespace App\Controllers\Marketing;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Marketing\PublicOnboardingService;

final class PublicOnboardingController extends Controller
{
    public function __construct(
        private readonly PublicOnboardingService $service = new PublicOnboardingService()
    ) {}

    public function showCompanyForm(Request $request): Response
    {
        try {
            $payload = $this->service->signupPage($request->all());
            return $this->view('marketing/signup_company', $payload, 'layouts/public');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/#planos');
        }
    }

    public function storeCompany(Request $request): Response
    {
        $guard = validate_form_submission($request->all(), 'marketing.public.signup', 5);
        if (($guard['ok'] ?? false) !== true) {
            try {
                $payload = $this->service->signupPage($request->all(), $request->all());
                $payload['error'] = (string) ($guard['message'] ?? 'Não foi possível validar o envio do cadastro.');
                return $this->view('marketing/signup_company', $payload, 'layouts/public');
            } catch (ValidationException $e) {
                return $this->backWithError($e->getMessage(), '/#planos');
            }
        }
        $rateLimit = $this->guardPublicRateLimit($request, 'marketing.public.signup', 6, 3600, '/#planos');
        if ($rateLimit !== null) {
            return $rateLimit;
        }

        try {
            $this->service->registerCompany($request->all());
            return $this->redirect('/cadastro/pagamento');
        } catch (ValidationException $e) {
            try {
                $payload = $this->service->signupPage($request->all(), $request->all());
                $payload['error'] = $e->getMessage();
                return $this->view('marketing/signup_company', $payload, 'layouts/public');
            } catch (ValidationException) {
                return $this->backWithError($e->getMessage(), '/#planos');
            }
        }
    }

    public function showPaymentPage(Request $request): Response
    {
        try {
            $payload = $this->service->paymentPage();
            if (!empty($payload['completed'])) {
                return $this->redirect((string) ($payload['redirect_url'] ?? '/login'));
            }

            return $this->view('marketing/signup_payment', $payload, 'layouts/public');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/#planos');
        }
    }

    public function generatePix(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'marketing.public.payment.pix', '/cadastro/pagamento');
        if ($guard !== null) {
            return $guard;
        }
        $rateLimit = $this->guardPublicRateLimit($request, 'marketing.public.payment.pix', 12, 600, '/cadastro/pagamento');
        if ($rateLimit !== null) {
            return $rateLimit;
        }

        try {
            $this->service->generatePixCharge();
            return $this->redirect('/cadastro/pagamento');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/cadastro/pagamento');
        }
    }

    public function startCardCheckout(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'marketing.public.payment.card', '/cadastro/pagamento');
        if ($guard !== null) {
            return $guard;
        }
        $rateLimit = $this->guardPublicRateLimit($request, 'marketing.public.payment.card', 12, 600, '/cadastro/pagamento');
        if ($rateLimit !== null) {
            return $rateLimit;
        }

        try {
            return $this->redirect($this->service->startCardCheckout());
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/cadastro/pagamento');
        }
    }

    public function handleGatewayReturn(Request $request): Response
    {
        try {
            $status = $this->service->refreshPaymentStatus();
            if (!empty($status['access_granted'])) {
                return $this->redirect((string) ($status['redirect_url'] ?? '/cadastro/confirmado'));
            }

            return $this->redirect('/cadastro/pagamento');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/#planos');
        }
    }

    public function pollStatus(Request $request): Response
    {
        try {
            $payload = $this->service->refreshPaymentStatus();
            return Response::make(
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                200,
                ['Content-Type' => 'application/json']
            );
        } catch (ValidationException $e) {
            return Response::make(
                json_encode([
                    'ok' => false,
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_SLASHES),
                422,
                ['Content-Type' => 'application/json']
            );
        }
    }

    public function showConfirmationPage(Request $request): Response
    {
        try {
            $payload = $this->service->confirmationPage();
            return $this->view('marketing/signup_confirmation', $payload, 'layouts/public');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/login');
        }
    }
}
