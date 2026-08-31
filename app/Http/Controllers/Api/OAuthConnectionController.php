<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

class OAuthConnectionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canUseSemphony(), 403, 'This BASIS account may not access Semphony samples.');
        abort_unless($user->tokenCan('profile:read'), 403, 'The BASIS connection does not grant profile access.');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => [
                'samples' => [
                    'view' => $user->tokenCan('samples:read'),
                    'attach' => $user->tokenCan('samples:attach'),
                ],
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()?->token();
        if ($token instanceof Token) {
            $token->refreshToken()->first()?->revoke();
            $token->revoke();
        }

        return response()->json(['ok' => true]);
    }
}
