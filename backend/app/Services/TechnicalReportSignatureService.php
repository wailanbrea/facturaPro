<?php

namespace App\Services;

use App\Models\TechnicalReport;
use RuntimeException;

class TechnicalReportSignatureService
{
    public const GENESIS = 'FACTURAPRO-REPORT-GENESIS';

    public function signOnIssue(TechnicalReport $report): TechnicalReport
    {
        if ($report->verification_hash !== null) {
            return $report;
        }

        if (! is_string($report->report_number) || trim($report->report_number) === '') {
            throw new RuntimeException('A report must have a number before it can be signed.');
        }

        $previousHash = $this->latestChainHash($report);
        $hash = $this->computeHash($report, $previousHash);

        $report->forceFill([
            'previous_hash' => $previousHash,
            'verification_hash' => $hash,
            'verification_code' => $this->codeFromHash($hash),
            'signed_at' => $report->signed_at ?? now(),
        ])->save();

        return $report;
    }

    public function matches(TechnicalReport $report): bool
    {
        if ($report->verification_hash === null) {
            return false;
        }

        return hash_equals(
            $report->verification_hash,
            $this->computeHash($report, $report->previous_hash ?? self::GENESIS),
        );
    }

    public function computeHash(TechnicalReport $report, ?string $previousHash): string
    {
        return hash_hmac('sha256', $this->canonicalString($report, $previousHash), $this->key());
    }

    public function canonicalString(TechnicalReport $report, ?string $previousHash): string
    {
        $sections = collect([1, 2, 3, 4])
            ->map(static fn (int $section): string => implode('|', [
                trim((string) $report->{'section_'.$section.'_title'}),
                trim((string) $report->{'section_'.$section.'_content'}),
            ]))
            ->implode('||');

        return implode("\n", [
            'v1-report',
            (string) $report->report_number,
            (string) ($report->seller_tax_id ?? ''),
            (string) ($report->recipient_tax_id ?? ''),
            (string) ($report->recipient_name ?? ''),
            $report->report_date?->toDateString() ?? '',
            hash('sha256', $sections),
            $previousHash ?? self::GENESIS,
        ]);
    }

    /**
     * @return array{status: string, report: ?TechnicalReport}
     */
    public function verifyByCode(?string $number, ?string $code): array
    {
        $number = is_string($number) ? trim($number) : '';
        $code = is_string($code) ? strtoupper(trim($code)) : '';

        if ($number === '') {
            return ['status' => 'not_found', 'report' => null];
        }

        $report = TechnicalReport::query()
            ->whereNotNull('verification_hash')
            ->where('report_number', $number)
            ->first();

        if ($report === null) {
            return ['status' => 'not_found', 'report' => null];
        }

        if ($code === '' || ! hash_equals((string) $report->verification_code, $code)) {
            return ['status' => 'code_mismatch', 'report' => null];
        }

        return [
            'status' => $this->matches($report) ? 'authentic' : 'altered',
            'report' => $report,
        ];
    }

    public function verificationUrl(TechnicalReport $report): string
    {
        $base = rtrim((string) config('app.url'), '/').'/invoices/verify';

        return $base.'?'.http_build_query([
            'number' => $report->report_number,
            'code' => $report->verification_code,
        ]);
    }

    public function codeFromHash(string $hash): string
    {
        $segment = strtoupper(substr($hash, 0, 16));

        return implode('-', str_split($segment, 4));
    }

    private function latestChainHash(TechnicalReport $exclude): string
    {
        $previous = TechnicalReport::query()
            ->whereNotNull('verification_hash')
            ->where('id', '!=', $exclude->getKey())
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->first();

        return $previous?->verification_hash ?? self::GENESIS;
    }

    private function key(): string
    {
        $appKey = (string) config('app.key');

        if ($appKey === '') {
            throw new RuntimeException('Cannot sign reports: APP_KEY is not set.');
        }

        return hash_hmac('sha256', 'facturapro:technical-report-signing', $appKey);
    }
}
