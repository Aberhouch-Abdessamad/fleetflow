<?php

namespace App\Notifications;

class ExpenseNotification extends TenantNotification
{
    public function __construct(?int $companyId, public int $expenseId, public string $action, public string $amount, public string $description, public ?string $reason = null)
    {
        parent::__construct($companyId);
    }

    protected function payload(): array
    {
        return [
            'title' => match ($this->action) {
                'submitted' => "Expense submitted: {$this->amount}",
                'approved' => "Your expense of {$this->amount} was approved",
                'rejected' => "Your expense of {$this->amount} was rejected",
            },
            'body' => $this->reason ?? $this->description,
            'category' => 'expense',
            'tone' => match ($this->action) {
                'approved' => 'success', 'rejected' => 'danger', default => 'info'
            },
            'link' => '/app/expenses',
        ];
    }
}
