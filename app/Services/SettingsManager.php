<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsManager
{
    /**
     * Request-level in-memory cache to avoid repeated Redis/Database hits in the same request lifecycle.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $memoizedSettings = null;

    /**
     * Retrieve a setting by its dot-notation key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->loadAll();

        return Arr::get($all, $key, $default);
    }

    /**
     * Update or create a single setting.
     */
    public function set(string $key, mixed $value): void
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => $value]);
            $this->invalidate($setting->group);
        }
    }

    /**
     * Update multiple settings in a single transaction with batch cache invalidation.
     *
     * @param  array<string, mixed>  $settings  Array of key-value pairs.
    /**
     * Update multiple settings in a single transaction with batch cache invalidation.
     *
     * @param  array<string, mixed>  $settings  Array of key-value pairs.
     */
    public function setMany(array $settings): void
    {
        DB::transaction(function () use ($settings) {
            $affectedGroups = [];

            foreach ($settings as $key => $value) {
                $shortKey = str_contains($key, '.') ? last(explode('.', $key)) : $key;

                $setting = Setting::where('key', $key)
                    ->orWhere('key', $shortKey)
                    ->orWhere('key', 'like', "%." . $shortKey)
                    ->first();

                if ($setting) {
                    $setting->update(['value' => $value]);
                    $affectedGroups[$setting->group] = true;
                } else {
                    $parts = explode('.', $key);
                    $group = count($parts) > 1 ? $parts[0] : 'general';
                    Setting::create([
                        'group' => $group,
                        'key' => $key,
                        'value' => $value,
                        'is_public' => true,
                    ]);
                    $affectedGroups[$group] = true;
                }
            }

            $this->clearCache();
            $this->invalidateMultiple(array_keys($affectedGroups));
        });
    }

    /**
     * Get all settings in a group.
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array
    {
        return Cache::remember("settings:group:{$group}", 86400, function () use ($group) {
            return Setting::where('group', $group)->get(['key', 'value'])->pluck('value', 'key')->toArray();
        });
    }

    /**
     * Get all public settings for the storefront.
     *
     * @return array<string, mixed>
     */
    public function getPublic(): array
    {
        return Cache::remember('settings:public', 86400, function () {
            $raw = Setting::where('is_public', true)->get(['key', 'value'])->pluck('value', 'key')->toArray();
            $formatted = [];
            foreach ($raw as $k => $v) {
                $formatted[$k] = $v;
                // Stripped short key name (e.g. general.site_name -> site_name)
                $parts = explode('.', $k);
                $shortKey = end($parts);
                $formatted[$shortKey] = $v;

                // Also unpack nested arrays if any
                if (is_array($v)) {
                    foreach ($v as $subKey => $subVal) {
                        $formatted["{$shortKey}_{$subKey}"] = $subVal;
                        $formatted[$subKey] = $subVal;
                    }
                }
            }
            return $formatted;
        });
    }

    /**
     * Load all settings, utilizing memoization and Redis.
     *
     * @return array<string, mixed>
     */
    protected function loadAll(): array
    {
        if ($this->memoizedSettings !== null) {
            return $this->memoizedSettings;
        }

        $this->memoizedSettings = Cache::remember('settings:all', 86400, function () {
            return Setting::get(['key', 'value'])->pluck('value', 'key')->toArray();
        });

        return $this->memoizedSettings;
    }

    /**
     * Invalidate cache for all settings.
     */
    public function clearCache(): void
    {
        $this->memoizedSettings = null;
        Cache::forget('settings:all');
        Cache::forget('settings:public');
    }

    /**
     * Invalidate cache for a single group.
     */
    protected function invalidate(string $group): void
    {
        $this->clearCache();
        Cache::forget("settings:group:{$group}");
    }

    /**
     * Invalidate cache for multiple groups.
     *
     * @param  array<string>  $groups
     */
    protected function invalidateMultiple(array $groups): void
    {
        $this->memoizedSettings = null;
        Cache::forget('settings:all');
        Cache::forget('settings:public');
        foreach ($groups as $group) {
            Cache::forget("settings:group:{$group}");
        }
    }
}
