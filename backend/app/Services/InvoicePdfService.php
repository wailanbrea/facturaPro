<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class InvoicePdfService
{
    public function relativePath(Invoice $invoice): string
    {
        $number = $invoice->invoice_number;

        if (! is_string($number) || trim($number) === '') {
            throw new RuntimeException('Cannot build a PDF path for an invoice without number.');
        }

        $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', $number);

        return "invoices/{$safeNumber}.pdf";
    }

    public function generate(Invoice $invoice): string
    {
        $relativePath = $this->relativePath($invoice);
        $chromePath = $this->chromeExecutable();
        $tempDirectory = storage_path('app/private/pdf-temp');
        $targetPath = Storage::disk('public')->path($relativePath);

        File::ensureDirectoryExists($tempDirectory);
        File::ensureDirectoryExists(dirname($targetPath));

        $htmlPath = $tempDirectory.DIRECTORY_SEPARATOR.'invoice-'.$invoice->getKey().'-'.bin2hex(random_bytes(6)).'.html';
        $profileDirectory = $tempDirectory.DIRECTORY_SEPARATOR.'chrome-profile-'.bin2hex(random_bytes(6));
        $html = view('pdf.invoice', [
            'invoice' => $invoice->load(['items', 'paymentTerm', 'bankAccount.currency', 'fiscalProfile']),
            'legalText' => $invoice->legal_text,
        ])->render();

        File::ensureDirectoryExists($profileDirectory);
        file_put_contents($htmlPath, $html);

        try {
            $process = new Process([
                $chromePath,
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-crash-reporter',
                '--disable-crashpad',
                '--no-first-run',
                '--no-default-browser-check',
                '--user-data-dir='.$profileDirectory,
                '--run-all-compositor-stages-before-draw',
                '--print-to-pdf-no-header',
                '--print-to-pdf='.$targetPath,
                $this->fileUrl($htmlPath),
            ]);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Chrome could not render the invoice PDF.');
            }

            clearstatcache(true, $targetPath);

            if (! is_file($targetPath) || filesize($targetPath) === 0) {
                throw new RuntimeException('Chrome finished without producing a valid PDF file.');
            }
        } finally {
            File::delete($htmlPath);
            File::deleteDirectory($profileDirectory);
        }

        return $relativePath;
    }

    private function chromeExecutable(): string
    {
        $configured = env('CHROME_PATH');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Chrome/Chromium executable was not found. Configure CHROME_PATH in the environment.');
    }

    private function fileUrl(string $path): string
    {
        return 'file:///'.str_replace('\\', '/', realpath($path) ?: $path);
    }
}
