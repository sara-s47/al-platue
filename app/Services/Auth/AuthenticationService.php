<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthenticationService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected OtpServiceInterface $otpService,
        protected LoyaltyService $loyaltyService,
    ) {
    }

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            if ($this->userRepository->findByPhone($data['phone'])) {
                throw new BusinessException('Phone number is already registered.', 'phone_taken');
            }

            if (! empty($data['email']) && $this->userRepository->findByEmail($data['email'])) {
                throw new BusinessException('Email is already registered.', 'email_taken');
            }

            $user = $this->userRepository->create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'status' => UserStatus::Inactive,
            ]);

            $this->otpService->send($user->phone);

            return $user;
        });
    }

    public function verifyOtpAndActivate(string $phone, string $code): array
    {
        if (! $this->otpService->verify($phone, $code)) {
            throw new BusinessException('Invalid or expired OTP.', 'invalid_otp');
        }

        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw new BusinessException('User not found.', 'user_not_found', 404);
        }

        if ($user->status === UserStatus::Blocked) {
            throw new BusinessException('Account is blocked.', 'account_blocked', 403);
        }

        $wasInactive = $user->status === UserStatus::Inactive
            || $user->status === UserStatus::Inactive->value;

        $user = $this->userRepository->update($user->id, [
            'status' => UserStatus::Active,
        ]);

        if ($wasInactive) {
            $this->loyaltyService->awardWelcomePoints($user->id);
        }

        return $this->issueTokenResponse($user);
    }

    public function login(string $phone, string $password): array
    {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new BusinessException('Invalid credentials.', 'invalid_credentials', 401);
        }

        $this->ensureUserCanAuthenticate($user);

        return $this->issueTokenResponse($user);
    }

    public function loginWithOtp(string $phone, string $code): array
    {
        if (! $this->otpService->verify($phone, $code)) {
            throw new BusinessException('Invalid or expired OTP.', 'invalid_otp');
        }

        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw new BusinessException('User not found.', 'user_not_found', 404);
        }

        $this->ensureUserCanAuthenticate($user);

        return $this->issueTokenResponse($user);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function updateProfile(User $user, array $data): User
    {
        if (! empty($data['email']) && $data['email'] !== $user->email) {
            $existing = $this->userRepository->findByEmail($data['email']);

            if ($existing && $existing->id !== $user->id) {
                throw new BusinessException('Email is already in use.', 'email_taken');
            }
        }

        if (! empty($data['phone']) && $data['phone'] !== $user->phone) {
            $existing = $this->userRepository->findByPhone($data['phone']);

            if ($existing && $existing->id !== $user->id) {
                throw new BusinessException('Phone number is already in use.', 'phone_taken');
            }
        }

        $payload = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], fn ($value) => $value !== null);

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        return $this->userRepository->update($user->id, $payload);
    }

    public function forgotPassword(string $phone): void
    {
        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw new BusinessException('User not found.', 'user_not_found', 404);
        }

        $this->otpService->send($phone);
        Cache::put($this->passwordResetCacheKey($phone), true, (int) config('otp.expires_in', 300));
    }

    public function resetPassword(string $phone, string $code, string $password): void
    {
        if (! Cache::pull($this->passwordResetCacheKey($phone))) {
            throw new BusinessException('Password reset session expired. Request a new code.', 'reset_expired');
        }

        if (! $this->otpService->verify($phone, $code)) {
            throw new BusinessException('Invalid or expired OTP.', 'invalid_otp');
        }

        $user = $this->userRepository->findByPhone($phone);

        if (! $user) {
            throw new BusinessException('User not found.', 'user_not_found', 404);
        }

        $this->userRepository->update($user->id, [
            'password' => $password,
        ]);
    }

    protected function ensureUserCanAuthenticate(User $user): void
    {
        if ($user->status === UserStatus::Blocked) {
            throw new BusinessException('Account is blocked.', 'account_blocked', 403);
        }

        if ($user->status === UserStatus::Inactive) {
            throw new BusinessException('Account is not activated.', 'account_inactive', 403);
        }
    }

    protected function issueTokenResponse(User $user): array
    {
        $this->userRepository->update($user->id, [
            'last_login_at' => now(),
        ]);

        $token = $user->createToken('api');

        return [
            'user' => $user->fresh(),
            'token' => $token->plainTextToken,
        ];
    }

    protected function passwordResetCacheKey(string $phone): string
    {
        return 'password_reset:'.preg_replace('/\D+/', '', $phone);
    }
}
