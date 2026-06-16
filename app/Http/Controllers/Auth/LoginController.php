<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\Region;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = $request->login;

        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            $loginType = 'email';
        } elseif (preg_match('/^\+?[0-9]{7,15}$/', $loginInput)) {
            $loginType = 'phone_number';
        } else {
            $loginType = 'username';
        }

        $user = User::with('warehouse')->where($loginType, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Login gagal. Cek kembali email, username, no telepon atau password.'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan. Silakan hubungi admin.'
            ], 403);
        }

        if ($user->warehouse && !$user->warehouse->status) {
            return response()->json([
                'success' => false,
                'message' => 'Gudang Anda saat ini tidak aktif. Silakan hubungi admin.'
            ], 403);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token', [$user->getRoleNames()->toArray()])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login Successfully.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->getRoleNames(),
                ]
            ]
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout Successfully.',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->loadMissing('warehouse');

        $auth = Cache::remember(
            "auth_user_{$user->id}",
            now()->addHours(24),
            function () use ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'region' => [
                        'id' => $user->region_id,
                        'full_address' => Region::getAddress($user->region_id),
                        'levels' => Region::getRegionData($user->region_id),
                    ],
                    'address' => $user->street ? "{$user->street}, {$user->postal_code}" : null,
                    'birth_date' => $user->birth_date?->format('l, d F Y'),
                    'raw_birth_date' => $user->birth_date?->format('Y-m-d'),
                    'street' => $user->street,
                    'postal_code' => $user->postal_code,
                    'warehouse' => $user->warehouse ? [
                        'id' => $user->warehouse->id,
                        'name' => $user->warehouse->name,
                    ] : null,
                    'roles' => $user->getRoleNames()->toArray(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                ];
            }
        );

        return response()->json([
            'success' => true,
            'data' => $auth
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $this->userService->updateUser($user, $request->validated());

            Cache::forget("auth_user_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile.',
                'error' => $err->getMessage()
            ], 500);
        }
    }
}
