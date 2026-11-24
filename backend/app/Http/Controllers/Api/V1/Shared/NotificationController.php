<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function query(Request $request)
    {
        $companyId = app(TenantContext::class)->id();

        return $request->user()->notifications()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    public function index(Request $request): JsonResponse
    {
        $q = $this->query($request)->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'));
        $res = $this->paginated($q, NotificationResource::class, $request, 15);
        $payload = $res->getData(true);
        $payload['meta']['unread'] = $this->query($request)->whereNull('read_at')->count();

        return response()->json($payload);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::ok(['unread' => $this->query($request)->whereNull('read_at')->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $this->query($request)->whereKey($id)->firstOrFail()->markAsRead();

        return ApiResponse::ok(null, 'Marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->query($request)->whereNull('read_at')->update(['read_at' => now()]);

        return ApiResponse::ok(null, 'All notifications marked as read.');
    }
}
