<?php

namespace App\Kernel\Audit;

use App\Kernel\Contracts\ModuleAuditor;
use InvalidArgumentException;

class ModuleAuditRegistry
{
    /** @var array<string, ModuleAuditor> */
    private array $auditors = [];

    public function register(ModuleAuditor $auditor): void
    {
        $this->auditors[$auditor->module()] = $auditor;
    }

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        return array_keys($this->auditors);
    }

    public function get(string $module): ModuleAuditor
    {
        if (! isset($this->auditors[$module])) {
            throw new InvalidArgumentException("No module auditor registered for [{$module}].");
        }

        return $this->auditors[$module];
    }
}
