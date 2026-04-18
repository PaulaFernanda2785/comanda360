<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Saas\SupportService;

final class SupportController extends Controller
{
    public function __construct(
        private readonly SupportService $service = new SupportService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('saas/support/index', [
            'title' => 'Atendimento SaaS',
            'user' => $user,
            'supportPanel' => $this->service->panel($request->query),
        ], 'layouts/saas');
    }

    public function reply(Request $request): Response
    {
        $ticketId = (int) ($request->input('ticket_id', 0));
        $redirectTo = $this->resolveSupportRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.support.reply.' . $ticketId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->replyToTicket($userId, $request->all(), $request->files);
            return $this->backWithSuccess('Resposta registrada no historico do chamado.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    private function resolveSupportRedirect(Request $request): string
    {
        $default = '/saas/support';
        $queryRaw = trim((string) ($request->input('return_query', '')));
        if ($queryRaw === '') {
            return $default;
        }

        parse_str($queryRaw, $params);
        if (!is_array($params)) {
            return $default;
        }

        $allowedKeys = [
            'support_search',
            'support_company_search',
            'support_status',
            'support_priority',
            'support_assignment',
            'support_page',
        ];

        $safe = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $params)) {
                $safe[$key] = (string) $params[$key];
            }
        }

        $query = http_build_query($safe);
        return '/saas/support' . ($query !== '' ? '?' . $query : '');
    }
}
