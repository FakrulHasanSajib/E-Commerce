<?php

use App\Services\SettingsManager;

if (! function_exists('setting')) {
    /**
     * Get or set settings.
     *
     * @return mixed|SettingsManager
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $manager = app(SettingsManager::class);

        if ($key === null) {
            return $manager;
        }

        return $manager->get($key, $default);
    }
}
