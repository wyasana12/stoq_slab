<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreAndUpdateUser;
use App\Http\Resources\User\UserDetailResource;
use App\Http\Resources\User\UserListResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;

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
            $allUsers = $this->userService->getAllUsers(10);

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

    public function show(User $user): JsonResponse {
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
