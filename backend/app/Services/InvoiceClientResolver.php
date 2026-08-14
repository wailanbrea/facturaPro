<?php

namespace App\Services;

use App\Models\Client;

class InvoiceClientResolver
{
    /**
     * Build the immutable client snapshot stored on a document.
     *
     * A linked client remains the owner of the master record, but Web and
     * Android may adjust contact data for one specific invoice/quotation.
     * Explicit request values therefore win without modifying the client.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function snapshot(Client $client, array $data): array
    {
        return [
            'client_id' => $client->id,
            'client_name' => filled($data['client_name'] ?? null) ? trim((string) $data['client_name']) : $client->name,
            'client_tax_id' => array_key_exists('client_tax_id', $data) ? $this->nullableString($data['client_tax_id']) : $client->tax_id,
            'client_address' => array_key_exists('client_address', $data) ? $this->nullableString($data['client_address']) : $client->address,
            'client_city' => array_key_exists('client_city', $data) ? $this->nullableString($data['client_city']) : $client->city,
            'client_phone' => array_key_exists('client_phone', $data) ? $this->nullableString($data['client_phone']) : $client->phone,
            'client_email' => array_key_exists('client_email', $data) ? $this->nullableString($data['client_email']) : $client->email,
        ];
    }

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
