<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Admin\DeliveryZoneService;

final class DeliveryZoneController extends Controller
{
    public function __construct(
        private readonly DeliveryZoneService $service = new DeliveryZoneService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/delivery_zones/index', [
            'title' => 'Zonas de Entrega',
            'user' => $user,
            'zones' => $this->service->listAll($companyId),
        ]);
    }

    public function store(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'delivery_zones.store', '/admin/delivery-zones');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->create($companyId, $request->all());
            return $this->backWithSuccess('Zona de entrega criada com sucesso.', '/admin/delivery-zones');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/delivery-zones');
        }
    }

    public function update(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'delivery_zones.update', '/admin/delivery-zones');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $zoneId = (int) ($request->input('zone_id', 0));

        try {
            $this->service->update($companyId, $zoneId, $request->all());
            return $this->backWithSuccess('Zona de entrega atualizada com sucesso.', '/admin/delivery-zones');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/delivery-zones');
        }
    }

    public function delete(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'delivery_zones.delete', '/admin/delivery-zones');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $zoneId = (int) ($request->input('zone_id', 0));

        try {
            $this->service->delete($companyId, $zoneId);
            return $this->backWithSuccess('Zona de entrega removida com sucesso.', '/admin/delivery-zones');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/delivery-zones');
        }
    }
}

