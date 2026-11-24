<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tenant\TeamService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/** Public invitation lookup / acceptance (token is the credential). */
class InvitationController extends Controller
{
    public function __construct(private readonly TeamService $team) {}

    public function show(string $token): JsonResponse
    {
        $inv = $this->team->findByToken($token);

        return ApiResponse::ok([
            'email' => $inv->email,
            'role' => CompanyRole::from($inv->role)->label(),
            'company' => $inv->company->name,
            'expires_at' => $inv->expires_at->toIso8601String(),
            'account_exists' => User::where('email', $inv->email)->exists(),
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        $data = $user ? [] : $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $user = $this->team->accept($token, $user, $data);

        if ($request->hasSession() && ! Auth::guard('web')->check()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return ApiResponse::ok(['email' => $user->email], 'Welcome aboard!');
    }
}
