<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());

        // Start the session for the SPA (stateful) — token clients log in separately.
        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return ApiResponse::created(new UserResource($user->load('activeCompanies')), 'Account created.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->login(
            $request, (string) $request->string('email'), (string) $request->string('password'), $request->input('device_name')
        );

        return ApiResponse::ok(array_filter([
            'user' => (new UserResource($user->load('activeCompanies')))->resolve($request),
            'token' => $token,
        ], fn ($v) => $v !== null), 'Signed in.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return ApiResponse::ok(null, 'Signed out.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::ok(new UserResource($request->user()->load('activeCompanies')));
    }
}
