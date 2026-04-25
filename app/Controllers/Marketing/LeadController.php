<?php
declare(strict_types=1);

namespace App\Controllers\Marketing;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Marketing\LeadCaptureService;

final class LeadController extends Controller
{
    public function __construct(
        private readonly LeadCaptureService $service = new LeadCaptureService()
    ) {}

    public function store(Request $request): Response
    {
        $redirectTo = '/?landing_form=contact#contato';
        $guard = $this->guardSingleSubmit($request, 'marketing.contact.store', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }
        $rateLimit = $this->guardPublicRateLimit($request, 'marketing.contact.store', 10, 600, $redirectTo);
        if ($rateLimit !== null) {
            return $rateLimit;
        }

        try {
            $this->service->store($request->all(), $request->server);
            return $this->backWithSuccess('Contato comercial recebido. A equipe do MesiMenu pode retornar por e-mail, telefone ou WhatsApp.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }
}
