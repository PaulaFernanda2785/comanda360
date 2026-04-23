<?php
declare(strict_types=1);

namespace App\Controllers\Marketing;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Marketing\PublicInteractionService;

final class PublicInteractionController extends Controller
{
    public function __construct(
        private readonly PublicInteractionService $service = new PublicInteractionService()
    ) {}

    public function store(Request $request): Response
    {
        $redirectTo = '/?landing_form=feedback#blog';
        $guard = $this->guardSingleSubmit($request, 'marketing.public_interactions.store', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->store($request->all(), $request->server);
            return $this->backWithSuccess('Mensagem recebida. Obrigado por contribuir com o feedback da Comanda360.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }
}
