<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Admin\CashRegisterService;

final class CashRegisterController extends Controller
{
    public function __construct(
        private readonly CashRegisterService $service = new CashRegisterService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/cash_registers/index', [
            'title' => 'Caixa',
            'user' => $user,
            'openCashRegister' => $this->service->currentOpen($companyId),
            'cashRegisters' => $this->service->list($companyId),
        ]);
    }

    public function open(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'cash_registers.open', '/admin/cash-registers');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->open($companyId, $userId, $request->all());
            return $this->backWithSuccess('Caixa aberto com sucesso.', '/admin/cash-registers');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/cash-registers');
        }
    }

    public function close(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'cash_registers.close', '/admin/cash-registers');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->close($companyId, $userId, $request->all());
            return $this->backWithSuccess('Caixa fechado com sucesso.', '/admin/cash-registers');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/cash-registers');
        }
    }

    public function printTicket(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $cashRegisterId = (int) $request->input('cash_register_id', 0);

        try {
            return $this->view('admin/cash_registers/print_ticket', [
                'title' => 'Ticket de Caixa',
                'user' => $user,
                'context' => $this->service->ticketPrintContext($companyId, $cashRegisterId),
            ]);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/cash-registers');
        }
    }
}
