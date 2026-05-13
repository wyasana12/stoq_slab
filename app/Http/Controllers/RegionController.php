<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    /**
     * Get all provinces (Length: 2 chars, e.g., "11")
     */
    public function provinces(): JsonResponse
    {
        try {
            // Asumsi kolom kode wilayah Anda bernama 'id' dan namanya 'name'
            $provinces = DB::table('region')
                ->select('id', 'name')
                ->whereRaw('LENGTH(id) = 2')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $provinces
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve provinces',
                'error' => $err->getMessage()
            ], 500);
        }
    }

    /**
     * Get regencies by Province ID (Length: 5 chars, e.g., "11.01")
     */
    public function regencies($provinceId): JsonResponse
    {
        try {
            $regencies = DB::table('region')
                ->select('id', 'name')
                ->where('id', 'like', $provinceId . '.%')
                ->whereRaw('LENGTH(id) = 5')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $regencies
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve regencies',
                'error' => $err->getMessage()
            ], 500);
        }
    }

    /**
     * Get districts by Regency ID (Length: 8 chars, e.g., "11.01.01")
     */
    public function districts($regencyId): JsonResponse
    {
        try {
            $districts = DB::table('region')
                ->select('id', 'name')
                ->where('id', 'like', $regencyId . '.%')
                ->whereRaw('LENGTH(id) = 8')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $districts
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve districts',
                'error' => $err->getMessage()
            ], 500);
        }
    }

    /**
     * Get villages by District ID (Length: 13 chars, e.g., "11.01.01.2001")
     */
    public function villages($districtId): JsonResponse
    {
        try {
            $villages = DB::table('region')
                ->select('id', 'name')
                ->where('id', 'like', $districtId . '.%')
                ->whereRaw('LENGTH(id) = 13')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $villages
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve villages',
                'error' => $err->getMessage()
            ], 500);
        }
    }
}
