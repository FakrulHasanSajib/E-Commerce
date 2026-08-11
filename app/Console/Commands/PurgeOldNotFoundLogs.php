<?php

namespace App\Console\Commands;

use App\Models\NotFoundLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeOldNotFoundLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '404:purge-old-logs {--days=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge 404 not found logs older than specified days.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $count = NotFoundLog::where('last_occurred_at', '<', $cutoff)->delete();

        $this->info("Purged {$count} old 404 log entries older than {$days} days.");

        return Command::SUCCESS;
    }
}
