<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'data' => $this->data, 'read' => $this->read_at !== null, 'created_at' => Concerns::dt($this->created_at)];
    }
}
