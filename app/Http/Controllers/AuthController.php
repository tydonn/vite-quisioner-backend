<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //
    public function login(Request $request)
    {
        // 1. Validasi
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // 2. Cari user
        $user = User::where('email', $request->email)->first();

        // 3. Cek user & password
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        // 4. Generate token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. Response
        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user
        ]);
    }
}
