<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Admin\DeliveryService;

final class DeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryService $service = new DeliveryService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $roleSlug = strtolower(trim((string) ($user['role_slug'] ?? '')));
        $deliveryUserId = $roleSlug === 'delivery' ? (int) ($user['id'] ?? 0) : null;
        if ($deliveryUserId !== null && $deliveryUserId <= 0) {
            $deliveryUserId = null;
        }

        $panel = $this->service->panel($companyId, $deliveryUserId);

        return $this->view('admin/deliveries/index', [
            'title' => 'Painel de Entregas',
            'user' => $user,
            'deliveries' => $panel['rows'] ?? [],
            'deliveriesGrouped' => $panel['grouped'] ?? [],
            'deliveriesSummary' => $panel['summary'] ?? [],
            'deliveryUsers' => $this->service->deliveryUsers($companyId),
            'isDeliveryRole' => $roleSlug === 'delivery',
            'currentUserId' => (int) ($user['id'] ?? 0),
        ]);
    }

    public function update(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'deliveries.update', '/admin/deliveries');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        $roleSlug = strtolower(trim((string) ($user['role_slug'] ?? '')));
        $payload = $request->all();
        if ($roleSlug === 'delivery') {
            $requestedDeliveryUserId = (int) ($payload['delivery_user_id'] ?? 0);
            if ($requestedDeliveryUserId > 0 && $requestedDeliveryUserId !== $userId) {
                return $this->backWithError('Motoboy pode alterar apenas atribuicoes da propria entrega.', '/admin/deliveries');
            }
            if ($requestedDeliveryUserId <= 0) {
                $payload['delivery_user_id'] = $userId;
            }
        }

        try {
            $this->service->updateProgress($companyId, $userId, $payload);
            return $this->backWithSuccess('Entrega atualizada com sucesso.', '/admin/deliveries');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/deliveries');
        }
    }
}
