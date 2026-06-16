<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::all();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        if ($permission->roles()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission cannot be deleted because it is used by roles.'
            ], 400);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successful',
        ]);
    }
}
