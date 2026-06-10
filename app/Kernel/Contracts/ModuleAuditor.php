<?php

namespace App\Kernel\Contracts;

use App\Kernel\Audit\ModuleAuditResult;

interface ModuleAuditor
{
    public function module(): string;

    public function audit(): ModuleAuditResult;
}
