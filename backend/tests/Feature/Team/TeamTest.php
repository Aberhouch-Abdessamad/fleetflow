<?php

namespace Tests\Feature\Team;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamTest extends TestCase
{
    public function test_invite_and_accept_as_new_user_linking_a_driver_profile(): void
    {
        Notification::fake();
        [$owner, $company] = $this->companyOwner();
        $driver = $this->driver($company);
        Sanctum::actingAs($owner);

        $url = $this->postJson('/api/v1/team/invitations', ['email' => 'New.Driver@Example.com', 'role' => 'driver', 'driver_id' => $driver->id])
            ->assertCreated()->json('data.invitation_url');
        $token = basename($url);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/invitations/{$token}")->assertOk()->assertJsonPath('data.email', 'new.driver@example.com')->assertJsonPath('data.role', 'Driver');
        $this->postJson("/api/v1/invitations/{$token}/accept", ['name' => 'New Driver', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertOk();

        $user = User::where('email', 'new.driver@example.com')->first();
        $this->assertTrue($user->isMemberOf($company));
        $this->assertSame($user->id, Driver::withoutGlobalScopes()->find($driver->id)->user_id);
        $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.roles', ['driver']);
        $this->getJson('/api/v1/driver/dashboard')->assertOk();
    }

    public function test_invitation_email_must_match_existing_account(): void
    {
        [$owner, $company] = $this->companyOwner();
        $someone = User::factory()->create();
        Sanctum::actingAs($owner);
        $token = basename($this->postJson('/api/v1/team/invitations', ['email' => 'invitee@example.com', 'role' => 'dispatcher'])->json('data.invitation_url'));

        Sanctum::actingAs($someone);
        $this->postJson("/api/v1/invitations/{$token}/accept")->assertForbidden()->assertJsonPath('code', 'invitation_email_mismatch');
    }

    public function test_role_management_protects_owner_and_last_admin(): void
    {
        [$owner, $company] = $this->companyOwner();
        [$dispatcher] = $this->member('dispatcher', $company);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/team/members/{$owner->id}", ['role' => 'dispatcher'])->assertStatus(422);
        $this->patchJson("/api/v1/team/members/{$dispatcher->id}", ['role' => 'fleet_manager'])->assertOk();
        $this->patchJson("/api/v1/team/members/{$dispatcher->id}", ['status' => 'disabled'])->assertOk();

        Sanctum::actingAs($dispatcher);
        $this->getJson('/api/v1/companies/current')->assertForbidden();

        Sanctum::actingAs($owner);
        $members = collect($this->getJson('/api/v1/team')->assertOk()->json('data.members'));
        $this->assertSame(['fleet_manager'], $members->firstWhere('id', $dispatcher->id)['roles']);
    }

    public function test_non_admin_cannot_manage_team(): void
    {
        [$dispatcher] = $this->member('dispatcher');
        Sanctum::actingAs($dispatcher);
        $this->postJson('/api/v1/team/invitations', ['email' => 'x@y.com', 'role' => 'company_admin'])->assertForbidden();
    }

    public function test_password_reset_and_email_verification_flows(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])->assertOk();
        $token = Password::broker()->createToken($user);
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'reset@example.com', 'token' => 'bad', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertStatus(422);
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'reset@example.com', 'token' => $token, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => 'reset@example.com', 'password' => 'newpass123', 'device_name' => 't'])->assertOk();

        $url = URL::temporarySignedRoute('api.v1.auth.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)], absolute: false);
        $this->getJson($url)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->getJson("/api/v1/auth/verify/{$user->id}/".sha1($user->email))->assertForbidden();
    }

    public function test_profile_and_password_update(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/auth/profile', ['name' => 'Renamed', 'phone' => '+212600'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->putJson('/api/v1/auth/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertStatus(422);
        $this->putJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();
    }
}
