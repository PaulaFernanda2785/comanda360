<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Repositories\CompanyRepository;

final class CompanyService
{
    public function __construct(
        private readonly CompanyRepository $companies = new CompanyRepository()
    ) {}

    public function list(): array
    {
        return $this->companies->allForSaas();
    }
}
