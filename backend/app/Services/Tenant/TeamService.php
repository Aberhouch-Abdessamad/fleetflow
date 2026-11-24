<?php

namespace App\Services\Tenant;

use App\Enums\CompanyRole;
use App\Enums\MembershipStatus;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\MailMessageNotification;
use App\Services\ActivityLogger;
use App\Services\Subscription\SubscriptionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TeamService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SubscriptionService $subscriptions,
        private readonly ActivityLogger $logger,
    ) {}

    /** @return array{0: Invitation, 1: string} invitation + plain token (emailed; returned once for dev/testing) */
    public function invite(Company $company, string $email, CompanyRole $role, User $by, ?Driver $driver = null): array
    {
        $email = strtolower($email);
        if ($company->users()->where('email', $email)->wherePivot('status', 'active')->exists()) {
            throw new DomainException('This person is already a member of the company.', 'already_member', 409);
        }
        if ($role === CompanyRole::CompanyAdmin && ! $by->hasRole(CompanyRole::CompanyAdmin->value)) {
            throw new DomainException('Only company admins can invite admins.', 'forbidden', 403);
        }
        if ($driver && $driver->user_id) {
            throw new DomainException('This driver already has an account.', 'driver_linked', 409);
        }
        $this->subscriptions->assertCanAdd($company, 'users');

        Invitation::where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $token = Str::random(64);
        $invitation = Invitation::create([
            'email' => $email,
            'role' => $role->value,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $by->id,
            'driver_id' => $driver?->id,
            'expires_at' => now()->addDays(7),
        ]);

        $url = rtrim((string) config('app.frontend_url'), '/')."/invitations/{$token}";
        Notification::route('mail', $email)->notify(new MailMessageNotification(
            "You're invited to {$company->name} on FleetFlow",
            'Hello,',
            ["{$by->name} invited you to join {$company->name} as {$role->label()}."],
            'Accept invitation', $url, ['This invitation expires in 7 days.'],
        ));
        $this->logger->log('team.invited', $invitation, ['email' => $email, 'role' => $role->value]);

        return [$invitation, $token];
    }

    public function findByToken(string $token): Invitation
    {
        $inv = Invitation::withoutGlobalScope('company')->with('company')->where('token_hash', hash('sha256', $token))->first();
        if (! $inv || ! $inv->isPending()) {
            throw new DomainException('This invitation is invalid or has expired.', 'invitation_invalid', 404);
        }

        return $inv;
    }

    /** Accept as an existing (authenticated) user or by creating an account. */
    public function accept(string $token, ?User $user, array $registration = []): User
    {
        $inv = $this->findByToken($token);

        return DB::transaction(function () use ($inv, $user, $registration) {
            if ($user) {
                if (strtolower($user->email) !== $inv->email) {
                    throw new DomainException('This invitation was sent to a different email address.', 'invitation_email_mismatch', 403);
                }
            } else {
                if (User::where('email', $inv->email)->exists()) {
                    throw new DomainException('An account with this email exists. Sign in to accept the invitation.', 'account_exists', 409);
                }
                $user = User::create(['name' => $registration['name'], 'email' => $inv->email, 'password' => $registration['password'], 'phone' => $registration['phone'] ?? null]);
                $user->forceFill(['email_verified_at' => now()])->save(); // the invitation link proves the address
            }

            $company = $inv->company;
            $company->users()->syncWithoutDetaching([$user->id => ['status' => MembershipStatus::Active->value, 'joined_at' => now(), 'invited_by' => $inv->invited_by]]);
            $company->users()->updateExistingPivot($user->id, ['status' => MembershipStatus::Active->value]);

            $this->tenant->runAs($company, function () use ($user, $inv) {
                $user->unsetRelation('roles')->syncRoles([$inv->role]);
                if ($inv->driver_id) {
                    Driver::whereKey($inv->driver_id)->whereNull('user_id')->update(['user_id' => $user->id]);
                } elseif ($inv->role === CompanyRole::Driver->value && ! Driver::where('user_id', $user->id)->exists()) {
                    // Driver profile is completed later by the fleet manager (licence etc.)
                }
            });

            $user->forceFill(['current_company_id' => $company->id])->save();
            $inv->forceFill(['accepted_at' => now()])->save();
            $this->logger->log('team.joined', $user, ['role' => $inv->role], $company->id);

            return $user;
        });
    }

    public function changeRole(Company $company, User $member, CompanyRole $role, User $by): void
    {
        $this->assertNotLastAdmin($company, $member, $role);
        if ($member->id === $company->owner_id && $role !== CompanyRole::CompanyAdmin) {
            throw new DomainException('The company owner must remain a Company Admin.', 'owner_role', 422);
        }
        $this->tenant->runAs($company, fn () => $member->unsetRelation('roles')->syncRoles([$role->value]));
        $this->logger->log('team.role_changed', $member, ['role' => $role->value, 'by' => $by->id], $company->id);
    }

    public function setStatus(Company $company, User $member, MembershipStatus $status, User $by): void
    {
        if ($member->id === $by->id) {
            throw new DomainException('You cannot change your own access.', 'self_change', 422);
        }
        if ($member->id === $company->owner_id) {
            throw new DomainException('The company owner cannot be disabled.', 'owner_protected', 422);
        }
        if ($status === MembershipStatus::Active) {
            $this->subscriptions->assertCanAdd($company, 'users');
        }
        $company->users()->updateExistingPivot($member->id, ['status' => $status->value]);
        if ($status === MembershipStatus::Disabled) {
            // Revoke their API tokens so the driver app / sessions stop working for this workspace.
            $member->tokens()->delete();
        }
        $this->logger->log('team.member_'.$status->value, $member, ['by' => $by->id], $company->id);
    }

    private function assertNotLastAdmin(Company $company, User $member, CompanyRole $newRole): void
    {
        if ($newRole === CompanyRole::CompanyAdmin) {
            return;
        }
        $admin = Role::where('name', CompanyRole::CompanyAdmin->value)->whereNull('company_id')->first();
        $admins = DB::table('model_has_roles')->where('company_id', $company->id)->where('role_id', $admin?->id)->where('model_type', 'user')->pluck('model_id');
        if ($admins->count() <= 1 && $admins->contains($member->id)) {
            throw new DomainException('A company needs at least one Company Admin.', 'last_admin');
        }
    }
}
