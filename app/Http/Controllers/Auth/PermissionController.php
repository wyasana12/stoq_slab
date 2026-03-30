<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', Rule::unique('permissions', 'name'), 'min:5',],
        ], [
            'name.required' => 'Permission name is required.',
            'name.unique' => 'This Permission name has already been taken.',
            'name.min' => 'Permission name must be at least 5 characters.',
        ]);

        $permission = Permission::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successful',
            'data' => $permission
        ]);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', Rule::unique('permissions', 'name')->ignore($permission), 'min:5',],
        ], [
            'name.required' => 'Permission name is required.',
            'name.unique' => 'This Permission name has already been taken.',
            'name.min' => 'Permission name must be at least 5 characters.',
        ]);

        $permission->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successful.',
            'data' => $permission,
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        if ($permission->roles()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission cannot be deleted because it is used by roles.'
            ]);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successful',
        ]);
    }
}
