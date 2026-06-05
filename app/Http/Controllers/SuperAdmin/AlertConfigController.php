<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlertConfig\StoreandUpdateAlertConfigRequest;
use App\Models\AlertConfig;
use Illuminate\Http\JsonResponse;

class AlertConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = AlertConfig::orderBy('days_before', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    public function store(StoreandUpdateAlertConfigRequest $request): JsonResponse
    {
        $config = AlertConfig::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Alert config created successful.',
            'data' => $config
        ]);
    }

    public function update(StoreandUpdateAlertConfigRequest $request, AlertConfig $alert): JsonResponse {
        $alert->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Alert config updated successful.',
            'data' => $alert,
        ]);
    }

    public function destroy(AlertConfig $alert): JsonResponse {
        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert config deleted succesful.',
        ]);
    }
}
