<?php

namespace App\Services\Auth;

use App\Models\FirebaseDevice;
use App\Models\User;
use App\Repositories\Contracts\FirebaseDeviceRepositoryInterface;
use Illuminate\Support\Facades\DB;

class FirebaseDeviceService
{
    public function __construct(
        protected FirebaseDeviceRepositoryInterface $firebaseDeviceRepository,
    ) {
    }

    public function registerDevice(User $user, array $data): FirebaseDevice
    {
        return DB::transaction(function () use ($user, $data) {
            $existing = $this->firebaseDeviceRepository->findByUserAndToken(
                $user->id,
                $data['device_token'],
            );

            if ($existing) {
                return $this->firebaseDeviceRepository->update($existing->id, [
                    'platform' => $data['platform'],
                    'last_seen_at' => now(),
                    'is_active' => true,
                ]);
            }

            return $this->firebaseDeviceRepository->create([
                'user_id' => $user->id,
                'device_token' => $data['device_token'],
                'platform' => $data['platform'],
                'last_seen_at' => now(),
                'is_active' => true,
            ]);
        });
    }

    public function deactivateDevice(User $user, string $deviceToken): void
    {
        $device = $this->firebaseDeviceRepository->findByUserAndToken($user->id, $deviceToken);

        if ($device) {
            $this->firebaseDeviceRepository->update($device->id, [
                'is_active' => false,
            ]);
        }
    }
}
