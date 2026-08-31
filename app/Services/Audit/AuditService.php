<?php

namespace App\Services\Audit;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function __construct(
        protected AuditLogRepositoryInterface $auditLogRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $ip = null,
    ): Model {
        return $this->auditLogRepository->create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }
}
