<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\UserRepository;
use RuntimeException;

final class LoginService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {}

    public function attempt(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->users->findByEmail($normalizedEmail);

        if ($user === null) {
            throw new RuntimeException('E-mail ou senha invalidos.');
        }

        if (($user['status'] ?? '') !== 'ativo') {
            throw new RuntimeException('Usuario inativo ou bloqueado.');
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            throw new RuntimeException('E-mail ou senha invalidos.');
        }

        unset($user['password_hash']);

        return $user;
    }
}
