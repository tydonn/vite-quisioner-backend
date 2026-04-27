<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthSSOController extends Controller
{
    public function init(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'issuer' => ['required', 'string', 'max:100'],
            'request_id' => ['required', 'string', 'max:100'],
            'timestamp' => ['required', 'date'],
            'nonce' => ['required', 'string', 'max:200'],
            'username' => ['required', 'string', 'max:100'],
            'fullname' => ['required', 'string', 'max:200'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'max:100'],
            'program_code' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'signature' => ['required', 'string'],
        ]);

        $secret = (string) config('services.web_a_sso.secret');
        if ($secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'SSO secret is not configured.',
            ], 500);
        }

        $expectedIssuer = (string) config('services.web_a_sso.issuer');
        if ($expectedIssuer !== '' && $payload['issuer'] !== $expectedIssuer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid issuer.',
            ], 401);
        }

        $timestamp = strtotime($payload['timestamp']);
        $tolerance = (int) config('services.web_a_sso.timestamp_tolerance', 60);
        if (!$timestamp || abs(time() - $timestamp) > $tolerance) {
            return response()->json([
                'success' => false,
                'message' => 'Timestamp expired.',
            ], 401);
        }

        $expectedSignature = hash_hmac('sha256', $this->buildSignaturePayload($payload), $secret);
        if (!hash_equals($expectedSignature, (string) $payload['signature'])) {
            Log::warning('sso.init.failed.signature', [
                'issuer' => $payload['issuer'],
                'request_id' => $payload['request_id'],
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 401);
        }

        $nonceKey = 'sso:web_a:nonce:' . sha1($payload['issuer'] . '|' . $payload['nonce']);
        $nonceTtl = (int) config('services.web_a_sso.nonce_ttl', 300);
        if (!Cache::add($nonceKey, 1, now()->addSeconds($nonceTtl))) {
            return response()->json([
                'success' => false,
                'message' => 'Replay detected.',
            ], 409);
        }

        $username = trim((string) $payload['username']);
        $fullName = trim((string) $payload['fullname']);
        $email = !empty($payload['email']) ? (string) $payload['email'] : $username . '@sso.local';

        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name' => $fullName,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);
        } else {
            $user->name = $fullName;
            $user->save();
        }

        $ssoCode = Str::random(64);
        $codeTtl = (int) config('services.web_a_sso.code_ttl', 60);
        Cache::put(
            'sso:web_a:code:' . $ssoCode,
            [
                'user_id' => $user->id,
                'issuer' => $payload['issuer'],
                'request_id' => $payload['request_id'],
                'username' => $username,
                'roles' => $payload['roles'],
                'program_code' => $payload['program_code'],
            ],
            now()->addSeconds($codeTtl)
        );

        Log::info('sso.init.success', [
            'issuer' => $payload['issuer'],
            'request_id' => $payload['request_id'],
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'sso_code' => $ssoCode,
            'expires_in' => $codeTtl,
        ]);
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $codeKey = 'sso:web_a:code:' . $validated['code'];
        $ssoSession = Cache::get($codeKey);
        if (!$ssoSession) {
            Log::warning('sso.exchange.failed', [
                'reason' => 'invalid_or_expired_code',
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired code.',
            ], 401);
        }

        Cache::forget($codeKey);

        $user = User::find($ssoSession['user_id'] ?? null);
        if (!$user) {
            Log::warning('sso.exchange.failed', [
                'reason' => 'user_not_found',
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 401);
        }

        $token = auth('jwt')->login($user);

        Log::info('sso.exchange.success', [
            'issuer' => $ssoSession['issuer'] ?? null,
            'request_id' => $ssoSession['request_id'] ?? null,
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('jwt')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    private function buildSignaturePayload(array $payload): string
    {
        return implode('|', [
            (string) ($payload['issuer'] ?? ''),
            (string) ($payload['request_id'] ?? ''),
            (string) ($payload['timestamp'] ?? ''),
            (string) ($payload['nonce'] ?? ''),
            (string) ($payload['username'] ?? ''),
            (string) ($payload['fullname'] ?? ''),
            implode(',', (array) ($payload['roles'] ?? [])),
            (string) ($payload['program_code'] ?? ''),
            (string) ($payload['email'] ?? ''),
        ]);
    }
}
