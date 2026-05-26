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
        $actorEmail = null;

        try {
            $payload = auth('jwt')->payload();
            $roles = Arr::wrap($payload->get('roles'));
            $programCode = $payload->get('program_code');
            $actorEmail = auth('jwt')->user()?->email;
        } catch (JWTException $exception) {
            $roles = [];
            $programCode = null;
            $actorEmail = null;
        }

        $roles = array_map(static fn ($role) => strtolower((string) $role), $roles);
        $adminEmails = array_filter(array_map('trim', explode(',', (string) env('JWT_ADMIN_EMAILS', ''))));
        $tempAdminCreator = (string) env('TEMP_ADMIN_CREATOR_EMAIL', '');
        if ($tempAdminCreator !== '') {
            $adminEmails[] = $tempAdminCreator;
        }
        $adminEmails = array_map('strtolower', array_unique($adminEmails));

        $isAdministrator = in_array('administrator', $roles, true)
            || ($actorEmail !== null && in_array(strtolower((string) $actorEmail), $adminEmails, true));

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
