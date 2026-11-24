<?php

namespace App\Notifications;

class DocumentExpiringNotification extends TenantNotification
{
    public function __construct(?int $companyId, public string $title, public string $body, public string $link)
    {
        parent::__construct($companyId);
    }

    protected function payload(): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'category' => 'document', 'tone' => 'warning', 'link' => $this->link];
    }
}
