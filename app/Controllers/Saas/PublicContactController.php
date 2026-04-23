<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Saas\PublicContactService;

final class PublicContactController extends Controller
{
    public function __construct(
        private readonly PublicContactService $service = new PublicContactService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('saas/public_contacts/index', [
            'title' => 'Contatos Comerciais',
            'user' => $user,
            'contactPanel' => $this->service->panel($request->query),
        ], 'layouts/saas');
    }

    public function update(Request $request): Response
    {
        $contactId = (int) ($request->input('contact_id', 0));
        $redirectTo = $this->resolveRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.public_contacts.update.' . $contactId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->update($userId, $request->all());
            return $this->backWithSuccess('Contato comercial atualizado com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function delete(Request $request): Response
    {
        $contactId = (int) ($request->input('contact_id', 0));
        $redirectTo = $this->resolveRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.public_contacts.delete.' . $contactId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->delete($contactId);
            return $this->backWithSuccess('Contato comercial excluido com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    private function resolveRedirect(Request $request): string
    {
        $default = '/saas/public-contacts';
        $queryRaw = trim((string) ($request->input('return_query', '')));
        if ($queryRaw === '') {
            return $default;
        }

        parse_str($queryRaw, $params);
        if (!is_array($params)) {
            return $default;
        }

        $allowedKeys = [
            'contact_search',
            'contact_status',
            'contact_channel',
            'contact_page',
        ];

        $safe = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $params)) {
                $safe[$key] = (string) $params[$key];
            }
        }

        $query = http_build_query($safe);
        return '/saas/public-contacts' . ($query !== '' ? '?' . $query : '');
    }
}
