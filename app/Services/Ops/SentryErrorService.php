<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Log;
use Throwable;

class SentryErrorService
{
    /**
     * Report unhandled exception to Sentry/Bugsnag error tracking.
     */
    public function captureException(Throwable $exception, array $context = []): void
    {
        Log::error("Sentry Tracking Captured Exception: {$exception->getMessage()}", array_merge([
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], $context));

        if (app()->bound('sentry')) {
            app('sentry')->captureException($exception);
        }
    }
}
