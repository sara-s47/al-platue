<?php

namespace App\Services\Notification;

use App\Repositories\Contracts\FirebaseDeviceRepositoryInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    public function __construct(
        protected FirebaseDeviceRepositoryInterface $firebaseDeviceRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $tokens = $this->firebaseDeviceRepository->getActiveTokensForUser($userId);

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $tokens = $this->firebaseDeviceRepository->getActiveTokensForUsers($userIds);

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return ['success' => 0, 'failed' => 0, 'mode' => 'none'];
        }

        if ($this->usesHttpV1()) {
            return $this->sendViaHttpV1($tokens, $title, $body, $data);
        }

        if ($this->usesLegacy()) {
            return $this->sendViaLegacy($tokens, $title, $body, $data);
        }

        Log::info('FCM push (log mode)', [
            'tokens' => $tokens,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return ['success' => count($tokens), 'failed' => 0, 'mode' => 'log'];
    }

    protected function usesHttpV1(): bool
    {
        return (bool) config('services.firebase.project_id')
            && $this->resolveCredentialsPath() !== null;
    }

    protected function usesLegacy(): bool
    {
        return (bool) config('services.firebase.server_key');
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sendViaHttpV1(array $tokens, string $title, string $body, array $data): array
    {
        $accessToken = $this->getAccessToken();

        if (! $accessToken) {
            Log::warning('FCM HTTP v1 credentials present but access token could not be obtained.');

            Log::info('FCM push (log mode)', [
                'tokens' => $tokens,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);

            return ['success' => count($tokens), 'failed' => 0, 'mode' => 'log'];
        }

        $projectId = config('services.firebase.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $success = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $this->stringifyData($data),
                ],
            ];

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($url, $payload);

            if ($response->successful()) {
                $success++;
            } else {
                $failed++;
                Log::warning('FCM HTTP v1 send failed', [
                    'token' => $token,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }

        return ['success' => $success, 'failed' => $failed, 'mode' => 'http_v1'];
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sendViaLegacy(array $tokens, string $title, string $body, array $data): array
    {
        $success = 0;
        $failed = 0;

        foreach (array_chunk($tokens, 500) as $chunk) {
            $response = Http::withHeaders([
                'Authorization' => 'key='.config('services.firebase.server_key'),
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $chunk,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->stringifyData($data),
            ]);

            if ($response->successful()) {
                $success += (int) ($response->json('success') ?? count($chunk));
                $failed += (int) ($response->json('failure') ?? 0);
            } else {
                $failed += count($chunk);
                Log::warning('FCM legacy send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }

        return ['success' => $success, 'failed' => $failed, 'mode' => 'legacy'];
    }

    protected function getAccessToken(): ?string
    {
        $path = $this->resolveCredentialsPath();

        if (! $path) {
            return null;
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials)) {
            return null;
        }

        $jwt = $this->createJwt($credentials);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            Log::warning('FCM OAuth token request failed', ['body' => $response->body()]);

            return null;
        }

        return $response->json('access_token');
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function createJwt(array $credentials): string
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claimSet = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsignedToken = "{$header}.{$claimSet}";
        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $unsignedToken.'.'.$this->base64UrlEncode($signature);
    }

    protected function resolveCredentialsPath(): ?string
    {
        $credentialsPath = config('services.firebase.credentials');

        if (! $credentialsPath) {
            return null;
        }

        $path = $credentialsPath;

        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        return is_readable($path) ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    protected function stringifyData(array $data): array
    {
        return collect($data)
            ->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value))
            ->all();
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
