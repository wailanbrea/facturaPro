<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppReleaseController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->resolveReleaseMetadata(),
        ]);
    }

    public function download(): BinaryFileResponse|RedirectResponse|JsonResponse
    {
        $uploaded = $this->uploadedRelease();
        $manifest = $this->manifestRelease();

        if ($uploaded !== null && $uploaded['version_code'] >= ($manifest['version_code'] ?? 0)) {
            $fullPath = Storage::disk('public')->path($uploaded['apk_path']);
            $fileName = 'FacturaPro-v'.($uploaded['version_name'] ?: $uploaded['version_code']).'.apk';

            return response()->download($fullPath, $fileName, [
                'Content-Type' => 'application/vnd.android.package-archive',
            ]);
        }

        if (! empty($manifest['download_url'])) {
            return redirect()->away((string) $manifest['download_url']);
        }

        return response()->json([
            'message' => 'No hay paquete APK publicado en este momento.',
        ], 404);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveReleaseMetadata(): array
    {
        $uploaded = $this->uploadedRelease();
        $manifest = $this->manifestRelease();

        if ($uploaded !== null && $uploaded['version_code'] >= ($manifest['version_code'] ?? 0)) {
            return [
                'version_code' => (int) $uploaded['version_code'],
                'version_name' => (string) $uploaded['version_name'],
                'download_url' => url('/api/app-version/download'),
                'release_notes' => (string) ($uploaded['release_notes'] ?? ''),
                'published_at' => (string) ($uploaded['published_at'] ?? ''),
                'source' => 'server',
            ];
        }

        return [
            'version_code' => (int) ($manifest['version_code'] ?? 1),
            'version_name' => (string) ($manifest['version_name'] ?? '1.0'),
            'download_url' => (string) ($manifest['download_url'] ?? ''),
            'release_notes' => (string) ($manifest['release_notes'] ?? ''),
            'published_at' => (string) ($manifest['published_at'] ?? ''),
            'source' => 'manifest',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function uploadedRelease(): ?array
    {
        $value = Setting::query()->where('key', 'android.app_release')->value('value');
        if (! is_array($value) || empty($value['apk_path'])) {
            return null;
        }

        $apkPath = (string) $value['apk_path'];
        if (! Storage::disk('public')->exists($apkPath)) {
            return null;
        }

        return [
            'version_code' => (int) ($value['version_code'] ?? 0),
            'version_name' => (string) ($value['version_name'] ?? ''),
            'apk_path' => $apkPath,
            'release_notes' => (string) ($value['release_notes'] ?? ''),
            'published_at' => (string) ($value['published_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestRelease(): array
    {
        $manifestPath = base_path('app_release.json');
        if (! is_file($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }
}
