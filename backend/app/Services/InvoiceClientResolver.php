<?php

namespace App\Services;

use App\Models\Client;

class InvoiceClientResolver
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function resolve(array $data, bool $persist = true): Client
    {
        if (! empty($data['client_id'])) {
            return Client::query()->findOrFail($data['client_id']);
        }

        $payload = $this->payload($data);

        if (! $persist) {
            return new Client($payload);
        }

        $client = $this->findExisting($payload);

        if ($client === null) {
            return Client::query()->create($payload);
        }

        $client->forceFill($this->mergePayload($client, $payload))->save();

        return $client;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return [
            'name' => trim((string) ($data['client_name'] ?? '')),
            'tax_id' => $this->nullableString($data['client_tax_id'] ?? null),
            'address' => $this->nullableString($data['client_address'] ?? null),
            'city' => $this->nullableString($data['client_city'] ?? null),
            'phone' => $this->nullableString($data['client_phone'] ?? null),
            'email' => $this->nullableString($data['client_email'] ?? null),
            'is_active' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findExisting(array $payload): ?Client
    {
        if (! empty($payload['tax_id'])) {
            $client = Client::query()->where('tax_id', $payload['tax_id'])->first();

            if ($client !== null) {
                return $client;
            }
        }

        if (! empty($payload['email'])) {
            $client = Client::query()->where('email', $payload['email'])->first();

            if ($client !== null) {
                return $client;
            }
        }

        return Client::query()->where('name', $payload['name'])->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergePayload(Client $client, array $payload): array
    {
        return collect($payload)
            ->map(fn (mixed $value, string $key): mixed => $key === 'is_active'
                ? true
                : ($value ?: $client->{$key}))
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
