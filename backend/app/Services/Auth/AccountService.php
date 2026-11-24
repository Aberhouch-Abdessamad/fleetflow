<?php

namespace App\Services\Auth;

use App\Exceptions\DomainException;
use App\Models\User;
use App\Notifications\MailMessageNotification;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Email verification, password reset and profile — links point to the SPA. */
class AccountService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function sendVerification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }
        $signed = URL::temporarySignedRoute('api.v1.auth.verify', now()->addHours(24), ['id' => $user->id, 'hash' => sha1($user->email)], absolute: false);
        $query = parse_url($signed, PHP_URL_QUERY);
        $url = rtrim((string) config('app.frontend_url'), '/')."/verify-email?id={$user->id}&hash=".sha1($user->email).'&'.$query;

        $user->notify(new MailMessageNotification(
            'Verify your FleetFlow email', "Hello {$user->name},",
            ['Confirm your email address to secure your account.'],
            'Verify email', $url, ['This link expires in 24 hours.'],
        ));
    }

    public function verify(User $user, string $hash): void
    {
        if (! hash_equals(sha1($user->email), $hash)) {
            throw new DomainException('This verification link is invalid.', 'invalid_link', 403);
        }
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->logger->log('auth.email_verified', $user, companyId: null);
        }
    }

    public function sendResetLink(string $email): void
    {
        // Always behaves the same whether or not the account exists (no user enumeration).
        $user = User::where('email', strtolower($email))->first();
        if (! $user) {
            return;
        }
        $token = Password::broker()->createToken($user);
        $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->email);
        $user->notify(new MailMessageNotification(
            'Reset your FleetFlow password', "Hello {$user->name},",
            ['We received a request to reset your password.'],
            'Reset password', $url, ['This link expires in 60 minutes. If you did not request it, ignore this email.'],
        ));
    }

    public function reset(string $email, string $token, string $password): void
    {
        $status = Password::broker()->reset(
            ['email' => strtolower($email), 'token' => $token, 'password' => $password, 'password_confirmation' => $password],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete(); // revoke all API tokens
                event(new PasswordReset($user));
                $this->logger->log('auth.password_reset', $user, companyId: null);
            }
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw new DomainException('This reset link is invalid or has expired.', 'invalid_reset_token');
        }
    }

    public function updateProfile(User $user, array $data): User
    {
        $emailChanged = isset($data['email']) && strtolower($data['email']) !== $user->email;
        $user->fill(array_intersect_key($data, array_flip(['name', 'phone', 'locale'])));
        if ($emailChanged) {
            $user->email = strtolower($data['email']);
            $user->email_verified_at = null;
        }
        $user->save();
        if ($emailChanged) {
            $this->sendVerification($user);
        }

        return $user;
    }

    public function updatePassword(User $user, string $current, string $new, ?int $keepTokenId = null): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        $user->forceFill(['password' => $new])->save();
        $user->tokens()->when($keepTokenId, fn ($q) => $q->whereKeyNot($keepTokenId))->delete();
        $this->logger->log('auth.password_changed', $user, companyId: null);
    }
}
