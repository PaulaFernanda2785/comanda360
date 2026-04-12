<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Admin\KitchenService;

final class KitchenController extends Controller
{
    public function __construct(
        private readonly KitchenService $service = new KitchenService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/kitchen/index', [
            'title' => 'Producao / Cozinha',
            'user' => $user,
            'queue' => $this->service->queue($companyId),
            'recentPrints' => $this->service->recentPrints($companyId),
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->updateQueueStatus($companyId, $userId, $request->all());
            return $this->backWithSuccess('Status atualizado no painel de cozinha.', '/admin/kitchen');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/kitchen');
        }
    }

    public function emitTicket(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->emitKitchenTicket($companyId, $userId, $request->all());
            return $this->backWithSuccess('Ticket de cozinha registrado com sucesso.', '/admin/kitchen');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/kitchen');
        }
    }
}

