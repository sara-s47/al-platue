<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
<<<<<<< HEAD
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'roles' => $this->roles->pluck('name')->values()->all(),
=======
            'status' => $this->status,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
>>>>>>> 7e40dd3b4709e71188f9dfd0849383438f291586
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
