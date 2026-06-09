<?php

namespace App\Kernel\Audit;

readonly class ModuleAuditResult
{
    /**
     * @param  array<string, int>  $rowCounts
     * @param  list<array{check: string, message: string}>  $issues
     */
    public function __construct(
        public string $module,
        public array $rowCounts,
        public array $issues,
    ) {}

    public function passed(): bool
    {
        return $this->issues === [];
    }

    /**
     * @return array{module: string, passed: bool, row_counts: array<string, int>, issues: list<array{check: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'passed' => $this->passed(),
            'row_counts' => $this->rowCounts,
            'issues' => $this->issues,
        ];
    }
}
