<?php

namespace App\Console\Commands;

use App\Models\LandingPage;
use App\Services\LandingPage\LandingPageService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PublishScheduledLandingPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landing-pages:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transition scheduled landing pages past their published_at time to published status and invalidate Redis caches.';

    /**
     * Execute the console command.
     */
    public function handle(LandingPageService $landingPageService): int
    {
        $now = Carbon::now();

        $scheduledPages = LandingPage::where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($scheduledPages as $page) {
            $page->update(['status' => 'published']);
            $landingPageService->clearCache($page->slug);
            $count++;
        }

        $this->info("Published {$count} scheduled landing pages.");

        return Command::SUCCESS;
    }
}
