<?php

namespace App\Services\Booking;

use App\Repositories\Contracts\CancellationPolicyRepositoryInterface;
use App\Repositories\Contracts\ReschedulePolicyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class PolicyService
{
    public function __construct(
        protected CancellationPolicyRepositoryInterface $cancellationPolicyRepository,
        protected ReschedulePolicyRepositoryInterface $reschedulePolicyRepository,
    ) {
    }

    public function paginateCancellation(int $perPage = 15): LengthAwarePaginator
    {
        return $this->cancellationPolicyRepository->paginate($perPage);
    }

    public function createCancellation(array $data): Model
    {
        return $this->cancellationPolicyRepository->create($data);
    }

    public function updateCancellation(int $id, array $data): Model
    {
        return $this->cancellationPolicyRepository->update($id, $data);
    }

    public function deleteCancellation(int $id): bool
    {
        return $this->cancellationPolicyRepository->delete($id);
    }

    public function paginateReschedule(int $perPage = 15): LengthAwarePaginator
    {
        return $this->reschedulePolicyRepository->paginate($perPage);
    }

    public function createReschedule(array $data): Model
    {
        return $this->reschedulePolicyRepository->create($this->normalizeReschedulePayload($data));
    }

    public function updateReschedule(int $id, array $data): Model
    {
        return $this->reschedulePolicyRepository->update($id, $this->normalizeReschedulePayload($data));
    }

    public function deleteReschedule(int $id): bool
    {
        return $this->reschedulePolicyRepository->delete($id);
    }

    /**
     * Map API alias reschedule_fee → fee.
     */
    protected function normalizeReschedulePayload(array $data): array
    {
        if (array_key_exists('reschedule_fee', $data)) {
            $data['fee'] = $data['reschedule_fee'];
            unset($data['reschedule_fee']);
        }

        return $data;
    }
}