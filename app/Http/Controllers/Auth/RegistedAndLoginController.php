<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegistedAndLoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($loginType, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Login Failed. Please check your credentials.'
            ], 401);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token', [$user->role])->plainTextToken;

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
}
