<?php

namespace App\Services\Expense;

use App\Enums\ExpenseStatus;
use App\Exceptions\DomainException;
use App\Models\Expense;
use App\Models\User;
use App\Notifications\ExpenseNotification;
use App\Services\ActivityLogger;
use App\Services\Notify;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function create(array $data, User $by, ?UploadedFile $receipt = null): Expense
    {
        $expense = new Expense(fillable(Expense::class, $data));
        $expense->amount = $this->amount($data['amount']);
        $expense->created_by = $by->id;
        $expense->status = ExpenseStatus::Pending;
        if ($receipt) {
            $expense->receipt_path = Uploads::store($receipt, app(TenantContext::class)->id(), 'receipts');
        }
        $expense->save();
        $this->logger->log('expense.submitted', $expense, ['amount' => $expense->amount, 'category' => $expense->category->value]);

        $company = $expense->company;
        Notify::companyUsersWith($company, 'expenses.approve',
            new ExpenseNotification($company->id, $expense->id, 'submitted', $expense->amount.' '.$company->currency, $expense->description), $by->id);

        return $expense;
    }

    /** System-generated expenses (fuel, maintenance) — one per source record. */
    public function createFromSource(string $sourceType, int $sourceId, array $data, User $by): Expense
    {
        $expense = Expense::firstOrNew(['source_type' => $sourceType, 'source_id' => $sourceId]);
        $expense->fill(fillable(Expense::class, $data));
        $expense->amount = $this->amount($data['amount']);
        $expense->created_by ??= $by->id;
        $expense->status ??= ExpenseStatus::Pending;
        $expense->save();
        $this->logger->log('expense.recorded', $expense, ['source' => $sourceType, 'amount' => $expense->amount]);

        return $expense;
    }

    public function update(Expense $expense, array $data, ?UploadedFile $receipt = null): Expense
    {
        if ($expense->status !== ExpenseStatus::Pending) {
            throw new DomainException('Only pending expenses can be edited.', 'expense_locked');
        }
        if ($expense->source_type && isset($data['amount'])) {
            throw new DomainException('This expense was generated from a '.str_replace('_', ' ', $expense->source_type).'; edit that record instead.', 'expense_generated');
        }
        $expense->fill(fillable(Expense::class, $data));
        if (isset($data['amount'])) {
            $expense->amount = $this->amount($data['amount']);
        }
        if ($receipt) {
            $expense->receipt_path = Uploads::store($receipt, $expense->company_id, 'receipts');
        }
        $expense->save();
        $this->logger->log('expense.updated', $expense, ['fields' => array_keys($expense->getChanges())]);

        return $expense;
    }

    public function review(Expense $expense, User $reviewer, bool $approve, ?string $reason = null): Expense
    {
        if ($expense->status !== ExpenseStatus::Pending) {
            throw new DomainException('This expense has already been reviewed.', 'expense_reviewed', 409);
        }
        if ($expense->created_by === $reviewer->id && ! $expense->source_type) {
            throw new DomainException('You cannot review an expense you submitted.', 'self_review', 403);
        }
        if (! $approve && blank($reason)) {
            throw new DomainException('A rejection reason is required.', 'reason_required');
        }

        DB::transaction(function () use ($expense, $reviewer, $approve, $reason) {
            $expense->forceFill([
                'status' => $approve ? ExpenseStatus::Approved : ExpenseStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $approve ? null : $reason,
            ])->save();
            $this->logger->log($approve ? 'expense.approved' : 'expense.rejected', $expense, ['amount' => $expense->amount, 'reason' => $reason]);
        });

        $expense->creator?->notify(new ExpenseNotification(
            $expense->company_id, $expense->id, $approve ? 'approved' : 'rejected',
            $expense->amount.' '.$expense->company->currency, $expense->description, $reason
        ));

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        if ($expense->status === ExpenseStatus::Approved) {
            throw new DomainException('Approved expenses cannot be deleted.', 'expense_locked');
        }
        if ($expense->source_type) {
            throw new DomainException('Generated expenses are removed with their source record.', 'expense_generated');
        }
        $expense->delete();
        $this->logger->log('expense.deleted', $expense, ['amount' => $expense->amount]);
    }

    private function amount(mixed $v): string
    {
        $cents = Money::toCents((string) $v);
        if ($cents <= 0) {
            throw new DomainException('The amount must be greater than zero.', 'invalid_amount');
        }

        return Money::fromCents($cents);
    }
}
