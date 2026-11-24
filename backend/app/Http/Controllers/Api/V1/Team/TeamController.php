<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Enums\CompanyRole;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Team\InviteRequest;
use App\Models\Driver;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Tenant\TeamService;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $team, private readonly TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->tenant->companyOrFail();
        $roles = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.company_id', $company->id)->where('model_type', 'user')
            ->select('model_id', 'roles.name')->get()->groupBy('model_id');

        $members = $company->users()->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
            'roles' => $roles->get($u->id, collect())->pluck('name')->values(),
            'status' => $u->pivot->status instanceof \BackedEnum ? $u->pivot->status->value : $u->pivot->status,
            'is_owner' => $u->id === $company->owner_id,
            'joined_at' => $u->pivot->joined_at ? Carbon::parse($u->pivot->joined_at)->toIso8601String() : null,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'driver_id' => Driver::where('user_id', $u->id)->value('id'),
        ]);

        $invitations = Invitation::with('inviter:id,name')->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->latest()->get()
            ->map(fn ($i) => ['id' => $i->id, 'email' => $i->email, 'role' => $i->role, 'invited_by' => $i->inviter?->name, 'expires_at' => $i->expires_at->toIso8601String(), 'driver_id' => $i->driver_id]);

        return ApiResponse::ok([
            'members' => $members,
            'invitations' => $invitations,
            'roles' => collect(CompanyRole::cases())->reject(fn ($r) => $r === CompanyRole::Customer)->map(fn ($r) => [
                'value' => $r->value, 'label' => $r->label(), 'permissions' => config("fleetflow.roles.{$r->value}"),
            ])->values(),
        ]);
    }

    public function invite(InviteRequest $request): JsonResponse
    {
        $driver = $request->validated('driver_id') ? Driver::findOrFail($request->validated('driver_id')) : null;
        [$inv, $token] = $this->team->invite($this->tenant->companyOrFail(), $request->validated('email'), CompanyRole::from($request->validated('role')), $request->user(), $driver);

        return ApiResponse::created([
            'id' => $inv->id, 'email' => $inv->email, 'role' => $inv->role, 'expires_at' => $inv->expires_at->toIso8601String(),
            // The link is emailed; it is also returned to the inviter so they can share it directly.
            'invitation_url' => rtrim((string) config('app.frontend_url'), '/')."/invitations/{$token}",
        ], 'Invitation sent.');
    }

    public function revoke(Invitation $invitation): JsonResponse
    {
        $invitation->forceFill(['revoked_at' => now()])->save();

        return ApiResponse::ok(null, 'Invitation revoked.');
    }

    public function updateMember(Request $request, int $user): JsonResponse
    {
        $company = $this->tenant->companyOrFail();
        $member = $company->users()->whereKey($user)->firstOrFail();
        $data = $request->validate([
            'role' => ['sometimes', Rule::enum(CompanyRole::class)->except([CompanyRole::Customer])],
            'status' => ['sometimes', Rule::enum(MembershipStatus::class)],
        ]);
        if (isset($data['role'])) {
            if (! $request->user()->can('roles.manage')) {
                abort(403);
            }
            $this->team->changeRole($company, $member, CompanyRole::from($data['role']), $request->user());
        }
        if (isset($data['status'])) {
            $this->team->setStatus($company, $member, MembershipStatus::from($data['status']), $request->user());
        }

        return ApiResponse::ok(null, 'Member updated.');
    }
}
