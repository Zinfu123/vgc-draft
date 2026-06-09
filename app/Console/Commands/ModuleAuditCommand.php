<?php

namespace App\Console\Commands;

use App\Kernel\Audit\ModuleAuditRegistry;
use Illuminate\Console\Command;

class ModuleAuditCommand extends Command
{
    protected $signature = 'module:audit
                            {module? : Module name to audit (e.g. Pokedex)}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Run data integrity audits for v2 module migrations';

    public function handle(ModuleAuditRegistry $registry): int
    {
        $module = $this->argument('module');

        if ($module === null) {
            $modules = $registry->modules();

            if ($modules === []) {
                $this->info('No module auditors registered yet.');

                return self::SUCCESS;
            }

            $this->info('Registered module auditors: '.implode(', ', $modules));

            return self::SUCCESS;
        }

        try {
            $result = $registry->get($module)->audit();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result->passed() ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Module audit: {$result->module}");

        foreach ($result->rowCounts as $table => $count) {
            $this->line("  {$table}: {$count}");
        }

        if ($result->issues === []) {
            $this->info('All checks passed.');

            return self::SUCCESS;
        }

        foreach ($result->issues as $issue) {
            $this->error("[{$issue['check']}] {$issue['message']}");
        }

        return self::FAILURE;
    }
}
