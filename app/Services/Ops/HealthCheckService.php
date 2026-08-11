<?php

namespace App\Services\Ops;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckService
{
    /**
     * Inspect system health (Hard fail on DB/Disk, Soft fail degraded mode on Redis).
     */
    public function checkHealth(): array
    {
        $dbStatus = 'ok';
        $diskStatus = 'ok';
        $redisStatus = 'ok';
        $isHealthy = true;

        // 1. Database Check (Hard Fail)
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbStatus = 'error: '.$e->getMessage();
            $isHealthy = false;
        }

        // 2. Storage Disk Space Check (Hard Fail if < 50MB)
        try {
            $freeSpace = disk_free_space(storage_path());
            if ($freeSpace !== false && $freeSpace < 52428800) { // 50MB
                $diskStatus = 'warning: low disk space';
                $isHealthy = false;
            }
        } catch (Throwable $e) {
            $diskStatus = 'error: '.$e->getMessage();
            $isHealthy = false;
        }

        // 3. Redis Ping Check (Soft Fail Degraded Mode - does not block site availability)
        try {
            Cache::put('health_ping', 'ok', 10);
            if (Cache::get('health_ping') !== 'ok') {
                $redisStatus = 'degraded';
            }
        } catch (Throwable $e) {
            $redisStatus = 'degraded: '.$e->getMessage();
        }

        return [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => Carbon::now()->toIso8601String(),
            'services' => [
                'database' => $dbStatus,
                'disk' => $diskStatus,
                'redis' => $redisStatus,
            ],
            'is_healthy' => $isHealthy,
        ];
    }
}
