<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_user_can_register(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Samira Haddad',
            'email' => 'Samira@Example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'samira@example.com')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', ['email' => 'samira@example.com', 'is_super_admin' => false]);
    }

    public function test_registration_ignores_privileged_fields(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'is_super_admin' => true,
            'status' => 'active',
        ])->assertCreated();

        $this->assertFalse(User::where('email', 'mallory@example.com')->first()->is_super_admin);
    }

    public function test_registration_is_validated(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_token_client_can_log_in_and_call_me(): void
    {
        User::factory()->create(['email' => 'driver@example.com']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@example.com',
            'password' => 'password',
            'device_name' => 'driver-pwa',
        ])->assertOk()->json('data.token');

        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'driver@example.com')
            ->assertJsonPath('data.current_company', null);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'x@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_suspended_users_cannot_log_in(): void
    {
        User::factory()->suspended()->create(['email' => 's@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 's@example.com', 'password' => 'password', 'device_name' => 't'])
            ->assertForbidden()->assertJsonPath('code', 'account_suspended');
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->getJson('/api/v1/companies/current')->assertUnauthorized();
    }
}
