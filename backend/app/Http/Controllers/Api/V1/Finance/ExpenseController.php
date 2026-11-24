<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Finance\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Driver;
use App\Models\Expense;
use App\Services\Expense\ExpenseService;
use App\Services\Reports\Exporter;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    private function filtered(Request $request)
    {
        $user = $request->user();
        $q = Expense::with(['vehicle', 'driver', 'creator', 'reviewer']);
        if (! $user->can('expenses.view')) {
            if (! $user->can('expenses.create_own')) {
                throw new AuthorizationException;
            }
            $q->where('created_by', $user->id);
        }

        return $q->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('category'), fn ($q, $c) => $q->whereIn('category', explode(',', $c)))
            ->when($request->query('vehicle_id'), fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->query('driver_id'), fn ($q, $d) => $q->where('driver_id', $d))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->when($request->query('search'), fn ($q, $s) => $q->where('description', 'like', $this->like($s)));
    }

    public function index(Request $request): JsonResponse
    {
        $q = $this->filtered($request);
        $totals = (clone $q)->reorder()->selectRaw('status, sum(amount) s, count(*) c')->groupBy('status')->get()
            ->mapWithKeys(fn ($r) => [($r->status instanceof \BackedEnum ? $r->status->value : $r->status) => ['amount' => Money::fromCents(Money::toCents((string) $r->s)), 'count' => (int) $r->c]]);
        $response = $this->paginated($this->sort($q, $request, ['expense_date', 'amount', 'created_at'], '-expense_date'), ExpenseResource::class, $request);
        $payload = $response->getData(true);
        $payload['meta']['totals'] = $totals;

        return response()->json($payload);
    }

    public function export(Request $request, Exporter $exporter)
    {
        $rows = $this->filtered($request)->orderByDesc('expense_date')->limit(10000)->get()->map(fn (Expense $e) => [
            'date' => $e->expense_date->toDateString(), 'category' => $e->category->value, 'description' => $e->description,
            'amount' => $e->amount, 'status' => $e->status->value, 'vehicle' => $e->vehicle?->registration_number,
            'driver' => $e->driver?->fullName(), 'submitted_by' => $e->creator?->name, 'reviewed_by' => $e->reviewer?->name,
            'rejection_reason' => $e->rejection_reason,
        ])->all();

        return $exporter->download($request->query('format', 'csv'), 'expenses-'.now()->format('Ymd'), $rows);
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = collect($request->validated())->except('receipt')->all();
        if (! $user->can('expenses.manage')) {
            $driver = Driver::where('user_id', $user->id)->first();
            if (! $user->can('expenses.create_own') || ! $driver) {
                throw new AuthorizationException;
            }
            $data['driver_id'] = $driver->id; // drivers can only file for themselves
        }

        return ApiResponse::created(new ExpenseResource($this->service->create($data, $user, $request->file('receipt'))->load('vehicle', 'driver', 'creator')), 'Expense submitted.');
    }

    public function show(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        return ApiResponse::ok(new ExpenseResource($expense->load('vehicle', 'driver', 'creator', 'reviewer')));
    }

    public function update(ExpenseRequest $request, Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);
        $e = $this->service->update($expense, collect($request->validated())->except(['receipt', 'driver_id'])->all(), $request->file('receipt'));

        return ApiResponse::ok(new ExpenseResource($e->load('vehicle', 'driver', 'creator')), 'Expense updated.');
    }

    public function approve(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('review', $expense);

        return ApiResponse::ok(new ExpenseResource($this->service->review($expense, $request->user(), true)->load('vehicle', 'driver', 'creator', 'reviewer')), 'Expense approved.');
    }

    public function reject(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('review', $expense);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return ApiResponse::ok(new ExpenseResource($this->service->review($expense, $request->user(), false, $data['reason'])->load('vehicle', 'driver', 'creator', 'reviewer')), 'Expense rejected.');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('update', $expense);
        $this->service->delete($expense);

        return ApiResponse::ok(null, 'Expense deleted.');
    }
}
