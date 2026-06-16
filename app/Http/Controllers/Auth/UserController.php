<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreAndUpdateUser;
use App\Http\Resources\User\UserDetailResource;
use App\Http\Resources\User\UserListResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(): JsonResponse
    {
        try {
            $allUsers = $this->userService->getAllUsers();

            return response()->json([
                'success' => true,
                'data' => UserListResource::collection($allUsers),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve all users.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function store(StoreAndUpdateUser $request): JsonResponse
    {
        try {
            $createUser = $this->userService->createUser($request->validated());

            return response()->json([
                'success' => true,
                'messages' => 'User created successful.',
                'data' => new UserDetailResource($createUser),
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to create user.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function show(User $user): JsonResponse
    {
        try {
            $userDetail = $this->userService->getUserDetail($user);

            return response()->json([
                'success' => true,
                'data' => new UserDetailResource($userDetail),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve user details.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function update(StoreAndUpdateUser $request, User $user): JsonResponse
    {
        try {
            $updateUser = $this->userService->updateUser($user, $request->validated());

            return response()->json([
                'success' => true,
                'messages' => 'User updated successful.',
                'data' => new UserDetailResource($updateUser),
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to updated user.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function status(User $user, Request $request): JsonResponse
    {
        $statusInput = $request->input('status');
        $newStatus = filter_var($statusInput, FILTER_VALIDATE_BOOLEAN);

        try {
            $this->userService->toggleUserStatus($user, $newStatus);

            return response()->json([
                'success' => true,
                'messages' => 'User status updated successful.'
            ], 201);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to updated user status.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function destroy(User $user): JsonResponse
    {
        try {
            $this->userService->removeUsers($user);

            return response()->json([
                'success' => true,
                'messages' => 'Soft delete user successful.'
            ], 201);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to soft delete user.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function dropdown(): JsonResponse
    {
        $user = Auth::user();

        $users = User::select('id', 'name')->where('warehouse_id', $user->warehouse_id)
            ->role('admin')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
}
