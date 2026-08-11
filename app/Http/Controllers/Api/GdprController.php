<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gdpr\GdprService;
use Illuminate\Http\Request;

class GdprController extends Controller
{
    public function __construct(protected GdprService $gdprService) {}

    /**
     * Customer GDPR data export.
     */
    public function export(Request $request)
    {
        $user = $request->user('sanctum');

        $data = $this->gdprService->exportUserData($user);

        return response()->json([
            'message' => 'GDPR Data Export generated.',
            'data' => $data,
        ], 200);
    }

    /**
     * Customer account anonymization & deletion.
     */
    public function delete(Request $request)
    {
        $user = $request->user('sanctum');

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $this->gdprService->anonymizeAccount($user, $validated['password']);

        return response()->json([
            'message' => 'Account PII anonymized and sessions revoked successfully.',
        ], 200);
    }
}
