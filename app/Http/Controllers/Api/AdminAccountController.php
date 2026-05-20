<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

class AdminAccountController extends Controller
{
    /**
     * Temporary endpoint to create additional admin login accounts.
     */
    public function store(Request $request)
    {
        $actor = auth('jwt')->user();
        $allowedCreatorEmail = (string) env('TEMP_ADMIN_CREATOR_EMAIL', 'admin@quisioner.super');

        if (!$actor || strtolower((string) $actor->email) !== strtolower($allowedCreatorEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already exists.',
            ], 409);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        ActivityLogger::log(
            $request,
            'admin-user',
            'create',
            User::class,
            $user->id,
            $actor,
            [
                'new_data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Admin account created.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }
}
