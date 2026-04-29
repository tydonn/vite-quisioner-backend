<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class JwtAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!$token = auth('jwt')->attempt($credentials)) {
            Log::warning('jwt.login.failed', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah',
            ], 401);
        }

        $user = auth('jwt')->user();
        Log::info('jwt.login.success', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('jwt')->factory()->getTTL() * 60,
            'user' => $user,
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth('jwt')->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'email_verified_at' => $user?->email_verified_at,
                'roles' => ['Administrator'],
                'created_at' => $user?->created_at,
                'updated_at' => $user?->updated_at,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        try {
            $user = auth('jwt')->user();
            auth('jwt')->logout();
            Log::info('jwt.logout.success', [
                'user_id' => $user?->id,
                'email' => $user?->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil',
            ]);
        } catch (JWTException $exception) {
            Log::warning('jwt.logout.failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }
    }
}
