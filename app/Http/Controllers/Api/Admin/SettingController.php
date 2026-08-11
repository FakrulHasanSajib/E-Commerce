<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Http\Resources\Admin\SettingResource;
use App\Models\Setting;
use App\Services\Admin\AuditLogService;
use App\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettingController extends Controller
{
    public function __construct(
        protected SettingsManager $settings,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Display all settings.
     */
    public function index(): AnonymousResourceCollection
    {
        $settings = Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        return SettingResource::collection($settings);
    }

    /**
     * Display settings for a specific group.
     */
    public function show(string $group): AnonymousResourceCollection
    {
        $settings = Setting::query()
            ->where('group', $group)
            ->orderBy('key')
            ->get();

        return SettingResource::collection($settings);
    }

    /**
     * Update multiple settings.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated('settings');
        $this->settings->setMany($validated);

        $this->auditLog->log('settings.updated', null, ['keys' => array_keys($validated)], 'Updated system settings');

        return response()->json([
            'message' => 'Settings updated successfully.',
        ]);
    }
}
