<?php

namespace App\Services\Seo;

use App\Models\NotFoundLog;
use App\Models\UrlRedirect;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RedirectService
{
    /**
     * Resolve 301/302 redirects using Redis memory cache (zero DB queries).
     */
    public function resolveRedirect(string $path): ?array
    {
        $redirects = Cache::remember('redirects:all', 86400, function () {
            return UrlRedirect::where('is_active', true)
                ->get()
                ->keyBy('source_url')
                ->map(fn ($r) => [
                    'target_url' => $r->target_url,
                    'status_code' => $r->status_code,
                ])
                ->toArray();
        });

        $cleanPath = '/'.ltrim($path, '/');

        return $redirects[$cleanPath] ?? $redirects[$path] ?? null;
    }

    /**
     * Log 404 occurrences using atomic SQL increments.
     */
    public function log404NotFound(string $url, ?string $ip = null, ?string $userAgent = null): void
    {
        $log = NotFoundLog::where('url', $url)->first();

        if ($log) {
            $log->increment('hits_count');
            $log->update(['last_occurred_at' => Carbon::now()]);
        } else {
            NotFoundLog::create([
                'url' => $url,
                'user_agent' => $userAgent ? Str::limit($userAgent, 255) : null,
                'ip_address' => $ip,
                'hits_count' => 1,
                'last_occurred_at' => Carbon::now(),
            ]);
        }
    }
}
