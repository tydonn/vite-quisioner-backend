<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

trait ProgramScope
{
    protected function resolveProgramScope(): array
    {
        $roles = [];
        $programCode = null;

        try {
            $payload = auth('jwt')->payload();
            $roles = Arr::wrap($payload->get('roles'));
            $programCode = $payload->get('program_code');
        } catch (JWTException $exception) {
            $roles = [];
            $programCode = null;
        }

        $roles = array_map(static fn ($role) => strtolower((string) $role), $roles);
        $isAdministrator = in_array('administrator', $roles, true);

        return [
            'is_administrator' => $isAdministrator,
            'program_code' => $programCode !== null ? (string) $programCode : null,
            // Keep legacy username/password login working while SSO rollout is ongoing.
            'is_legacy_token' => !$isAdministrator && $programCode === null && empty($roles),
        ];
    }

    protected function unauthorizedProgramScopeResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Program scope is required for non-administrator user.',
        ], 403);
    }
}
