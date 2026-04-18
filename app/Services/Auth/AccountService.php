<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ValidationException;
use App\Repositories\UserRepository;

final class AccountService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {}

    public function passwordViewData(array $sessionUser): array
    {
        $userId = (int) ($sessionUser['id'] ?? 0);
        $user = $this->users->findByIdForPasswordChange($userId);
        if ($user === null) {
            throw new ValidationException('Usuário autenticado não encontrado.');
        }

        return $user;
    }

    public function changePassword(array $sessionUser, array $input): array
    {
        $userId = (int) ($sessionUser['id'] ?? 0);
        if ($userId <= 0) {
            throw new ValidationException('Usuário autenticado inválido para alterar a senha.');
        }

        $user = $this->users->findByIdForPasswordChange($userId);
        if ($user === null) {
            throw new ValidationException('Usuário autenticado não encontrado.');
        }

        $currentPassword = (string) ($input['current_password'] ?? '');
        $newPassword = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['password_confirm'] ?? '');

        if ($currentPassword === '') {
            throw new ValidationException('Informe a senha atual.');
        }
        if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            throw new ValidationException('A senha atual informada não confere.');
        }
        if ($newPassword === '') {
            throw new ValidationException('Informe a nova senha.');
        }
        if (strlen($newPassword) < 6) {
            throw new ValidationException('A nova senha deve ter no mínimo 6 caracteres.');
        }
        if ($confirmPassword === '') {
            throw new ValidationException('Confirme a nova senha.');
        }
        if (!hash_equals($newPassword, $confirmPassword)) {
            throw new ValidationException('Nova senha e confirmação não conferem.');
        }
        if (hash_equals($currentPassword, $newPassword)) {
            throw new ValidationException('A nova senha deve ser diferente da senha atual.');
        }

        $this->users->updatePasswordHash($userId, password_hash($newPassword, PASSWORD_DEFAULT));

        $updated = $sessionUser;
        $updated['name'] = (string) ($user['name'] ?? ($sessionUser['name'] ?? ''));
        $updated['email'] = (string) ($user['email'] ?? ($sessionUser['email'] ?? ''));
        $updated['status'] = (string) ($user['status'] ?? ($sessionUser['status'] ?? ''));
        $updated['company_id'] = (int) ($user['company_id'] ?? ($sessionUser['company_id'] ?? 0));
        $updated['is_saas_user'] = (int) ($user['is_saas_user'] ?? ($sessionUser['is_saas_user'] ?? 0));
        $updated['role_context'] = (string) ($user['role_context'] ?? ($sessionUser['role_context'] ?? 'company'));
        $updated['role_name'] = (string) ($user['role_name'] ?? ($sessionUser['role_name'] ?? ''));

        return $updated;
    }
}
