<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private string $projectId;

    private string $credentialsPath;

    public function __construct()
    {
        $this->projectId = (string) config('services.fcm.project_id', '');
        $this->credentialsPath = base_path((string) config('services.fcm.credentials_path', ''));
    }

    /**
     * Send a push notification to all devices of a user.
     *
     * @param array<string, mixed> $data
     */
    public function notifyUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->pluck('token')
            ->all();

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * Broadcast to all registered android devices.
     *
     * @param array<int> $userIds
     * @param array<string, mixed> $data
     */
    public function notifyUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->pluck('token')
            ->all();

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendToToken(string $token, string $title, string $body, array $data = []): void
    {
        if ($this->projectId === '' || ! file_exists($this->credentialsPath)) {
            return;
        }

        try {
            $accessToken = $this->getAccessToken();

            Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('FCM send failed', ['token' => substr($token, 0, 10).'…', 'error' => $e->getMessage()]);
        }
    }

    private function getAccessToken(): string
    {
        return Cache::remember('fcm_access_token', 3500, function () {
            $credentials = json_decode(file_get_contents($this->credentialsPath), true);

            $now = time();
            $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = $this->base64url(json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $signingInput = $header.'.'.$payload;
            openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

            $jwt = $signingInput.'.'.$this->base64url($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            return $response->json('access_token');
        });
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
