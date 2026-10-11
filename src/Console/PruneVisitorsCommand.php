<?php

namespace Kajodo\Guard\Console;

use Illuminate\Console\Command;
use Kajodo\Guard\Models\KajodoVisitor;

class PruneVisitorsCommand extends Command
{
    protected $signature = 'kajodo:prune-visitors
        {--days= : Delete visitors not seen for N days (defaults to kajodo-guard.retention_days)}';

    protected $description = 'Delete stored Kajodo visitor rows not seen for the retention period.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('kajodo-guard.retention_days', 90));

        $deleted = KajodoVisitor::query()
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} stored visitor(s) not seen for {$days} day(s).");

        return self::SUCCESS;
    }
}
