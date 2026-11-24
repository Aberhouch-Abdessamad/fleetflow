<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AccountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $account) {}

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $this->account->sendResetLink($data['email']);

        return ApiResponse::ok(null, 'If an account exists for this email, a reset link has been sent.');
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $this->account->reset($data['email'], $data['token'], $data['password']);

        return ApiResponse::ok(null, 'Password reset. You can now sign in.');
    }

    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        abort_unless($request->hasValidSignature(false), 403, 'This verification link is invalid or has expired.');
        $this->account->verify(User::findOrFail($id), $hash);

        return ApiResponse::ok(null, 'Email verified.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $this->account->sendVerification($request->user());

        return ApiResponse::ok(null, 'Verification email sent.');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users')->ignore($request->user()->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['sometimes', Rule::in(['en', 'fr', 'ar'])],
        ]);

        return ApiResponse::ok(new UserResource($this->account->updateProfile($request->user(), $data)->load('activeCompanies')), 'Profile updated.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);
        $token = $request->user()->currentAccessToken();
        $this->account->updatePassword($request->user(), $data['current_password'], $data['password'], $token instanceof PersonalAccessToken ? $token->id : null);

        return ApiResponse::ok(null, 'Password updated.');
    }
}
