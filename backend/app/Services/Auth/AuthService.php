<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
            ]);

            $this->logger->log('auth.registered', $user, companyId: null);

            return $user;
        });
    }

    /**
     * Authenticates the user. Returns [user, token]: a bearer token for
     * non-browser clients, or null when a stateful (cookie) session was started for the SPA.
     *
     * @return array{0: User, 1: ?string}
     */
    public function login(Request $request, string $email, string $password, ?string $deviceName): array
    {
        $user = User::where('email', strtolower($email))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if ($user->status !== UserStatus::Active) {
            throw new DomainException('Your account is suspended.', 'account_suspended', 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->logger->log('auth.login', $user, companyId: null);

        if ($deviceName !== null || ! $request->hasSession()) {
            return [$user, $user->createToken($deviceName ?: 'api')->plainTextToken];
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return [$user, null];
    }

    public function logout(Request $request): void
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
