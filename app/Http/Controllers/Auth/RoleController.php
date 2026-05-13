<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AssignPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', Rule::unique('roles', 'name'), 'min:3', 'max:10'],
        ], [
            'name.required' => 'Role name is required.',
            'name.unique' => 'This role name has already been taken.',
            'name.min' => 'Role name must be at least 3 characters and max 10 characters.',
            'name.max' => 'Role name must be at least 3 characters and max 10 characters.',
        ]);

        $role = Role::create([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successful.',
            'data' => $role
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', Rule::unique('roles', 'name')->ignore($role), 'min:3', 'max:10'],
        ], [
            'name.required' => 'Role name is required.',
            'name.unique' => 'This role name has already been taken.',
            'name.min' => 'Role name must be at least 3 characters and max 10 characters.',
            'name.max' => 'Role name must be at least 3 characters and max 10 characters.',
        ]);

        $role->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successful.',
            'data' => $role,
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->permissions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Role cannot be deleted because it is used by permission.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successful.',
        ]);
    }

    public function assignPermissions(AssignPermissions $request, Role $role): JsonResponse
    {
        try {
            $role->syncPermissions($request->permissions);

            $role->load('permissions');

            $formattedData = [
                'id' => $role->id,
                'role' => [
                    'name' => $role->name
                ],
                'permissions' => $role->permissions->pluck('name'),
            ];

            return response()->json([
                'succcess' => true,
                'messages' => 'Permissions have been successfully synced to the role.',
                'data' => $formattedData,
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to sync permissions. Please try again.',
                'error' => $err->getMessage(),
            ]);
        }
    }
}
