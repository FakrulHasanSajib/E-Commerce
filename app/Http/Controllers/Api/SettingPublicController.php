<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsManager;
use Illuminate\Http\JsonResponse;

class SettingPublicController extends Controller
{
    public function __construct(protected SettingsManager $settings) {}

    /**
     * Display a flat key-value object of all public settings.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            $this->settings->getPublic()
        );
    }
}
