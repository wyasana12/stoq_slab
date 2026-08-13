<?php

namespace App\Http\Controllers;

use App\Models\ScanHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScanHistoryController extends Controller
{
    public function index(Request $request)
    {
        $histories = ScanHistory::where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $histories
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'activity' => 'required|string',
                'batch_code' => 'nullable',
                'product_name' => 'nullable',
                'qty' => 'nullable',
                'status' => 'nullable',
                'raw_result' => 'nullable',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::error('Validation failed: ' . json_encode($e->errors()));
            throw $e;
        }

        try {
            $history = ScanHistory::create([
                'user_id' => $request->user()->id,
                'activity' => $request->activity,
                'batch_code' => $request->batch_code,
                'product_name' => $request->product_name,
                'qty' => $request->qty,
                'status' => $request->status,
                'raw_result' => $request->raw_result,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('DB Insert failed: ' . $e->getMessage());
            throw $e;
        }

        return response()->json([
            'success' => true,
            'data' => $history,
            'message' => 'Scan history saved.'
        ], 201);
    }
}
