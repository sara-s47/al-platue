<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient_id' => $this->recipient_id ?? null,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'deep_link' => $this->deep_link,
            'read_at' => $this->read_at ?? null,
            'created_at' => $this->created_at,
        ];
    }
}