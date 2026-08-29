<?php

namespace App\Support;

use App\Models\Invoice;
use App\Services\InvoiceSignatureService;
use App\Services\QrCodeService;
use Illuminate\Support\Collection;

/**
 * Everything the PDF templates need prepared before rendering: currency
 * formatting options, the embedded logo, the authenticity QR, the watermark
 * and — most importantly — how the line items are split across sheets.
 *
 * Lives in PHP rather than in a Blade `@php` block so both templates share one
 * implementation and the pagination can be unit tested.
 */
class PdfDocumentContext
{
    /** @param  array<int, Collection<int, mixed>>  $pages */
    private function __construct(
        public readonly Invoice $invoice,
        public readonly array $currency,
        public readonly ?string $logoSrc,
        public readonly string $sellerInitial,
        public readonly ?string $qrSrc,
        public readonly ?string $verificationCode,
        public readonly ?string $watermark,
        public readonly array $pages,
        public readonly int $totalPages,
        public readonly bool $hasDedicatedBottomSheet,
        public readonly bool $hasDedicatedLegalSheet,
        public readonly string $taxLabel,
    ) {}

    /**
     * @param  int  $firstPageRows  Row budget for the first sheet, where the table
     *                              shares its width with the side column.
     * @param  int  $nextPageRows   Row budget for full-width continuation sheets.
     */
    public static function for(Invoice $invoice, int $firstPageRows = 11, int $nextPageRows = 24): self
    {
        $signature = app(InvoiceSignatureService::class);
        $qr = app(QrCodeService::class);

        $isSigned = filled($invoice->verification_code) && filled($invoice->verification_hash);
        $qrSrc = $isSigned ? $qr->svgDataUri($signature->verificationUrl($invoice)) : null;

        $pages = self::paginate($invoice->items, $firstPageRows, $nextPageRows);
        if ($invoice->isQuotation()) {
            $pages = self::reserveQuotationEnding($pages);
        } else {
            $pages = self::reserveBottomBoxesOnLastPage($pages);
        }

        $lastPageCost = collect($pages)->last()?->sum(
            static fn ($item): int => self::itemRowCost($item),
        ) ?? 0;

        $hasDedicatedBottomSheet = ! $invoice->isQuotation() && $lastPageCost > 22;
        $hasDedicatedLegalSheet = count($pages) === 1 || $hasDedicatedBottomSheet
            || ($invoice->isQuotation() && $lastPageCost > 9);
        return new self(
            invoice: $invoice,
            currency: [
                'symbol' => $invoice->currency_symbol,
                'decimal_separator' => $invoice->currency_decimal_separator,
                'thousand_separator' => $invoice->currency_thousand_separator,
                'decimal_places' => $invoice->currency_decimal_places,
                'symbol_position' => $invoice->currency_symbol_position,
            ],
            logoSrc: self::embeddedLogo($invoice),
            sellerInitial: mb_strtoupper(mb_substr((string) ($invoice->seller_name ?: 'F'), 0, 1)),
            qrSrc: $qrSrc,
            verificationCode: $isSigned ? $invoice->verification_code : null,
            watermark: self::watermarkFor($invoice),
            pages: $pages,
            totalPages: count($pages) + ($hasDedicatedBottomSheet ? 1 : 0) + ($hasDedicatedLegalSheet ? 1 : 0),
            hasDedicatedBottomSheet: $hasDedicatedBottomSheet,
            hasDedicatedLegalSheet: $hasDedicatedLegalSheet,
            taxLabel: self::taxLabelFor($invoice),
        );
    }

    /**
     * "IVA (21%)" when every line shares a rate, plain "IVA" when they differ.
     */
    private static function taxLabelFor(Invoice $invoice): string
    {
        $rates = $invoice->items
            ->map(static fn ($item): float => (float) $item->tax_rate)
            ->unique()
            ->values();

        if ($rates->count() !== 1) {
            return 'IVA';
        }

        return 'IVA ('.rtrim(rtrim(number_format($rates->first(), 2, ',', '.'), '0'), ',').'%)';
    }

    /**
     * Index of the first row of a given sheet, 1-based, so continuation pages
     * keep numbering where the previous one stopped.
     */
    public function startIndexFor(int $pageIndex): int
    {
        $start = 1;

        for ($i = 0; $i < $pageIndex; $i++) {
            $start += $this->pages[$i]->count();
        }

        return $start;
    }

    /**
     * Split the items into sheets.
     *
     * A long description wraps to a second visual line, so it costs two row
     * slots. Nothing is ever dropped: every item ends up in some sheet.
     *
     * @param  Collection<int, mixed>  $items
     * @return array<int, Collection<int, mixed>>
     */
    private static function paginate(Collection $items, int $firstPageRows, int $nextPageRows): array
    {
        $pages = [];
        $current = [];
        $used = 0;
        $budget = $firstPageRows;

        foreach ($items as $item) {
            $cost = self::itemRowCost($item);

            if ($current !== [] && $used + $cost > $budget) {
                $pages[] = collect($current);
                $current = [];
                $used = 0;
                $budget = $nextPageRows;
            }

            $current[] = $item;
            $used += $cost;
        }

        // Always at least one sheet, even for a document with no lines yet.
        $pages[] = collect($current);

        return $pages;
    }

    private static function itemRowCost(mixed $item): int
    {
        return max(1, (int) ceil(mb_strlen((string) $item->description) / 78));
    }

    /**
     * Keep the three summary boxes on the final items sheet. When a full
     * continuation sheet leaves no room, move its final item down with them.
     *
     * @param  array<int, Collection<int, mixed>>  $pages
     * @return array<int, Collection<int, mixed>>
     */
    private static function reserveBottomBoxesOnLastPage(array $pages): array
    {
        $lastIndex = array_key_last($pages);
        $lastPage = $pages[$lastIndex];
        $lastPageCost = $lastPage->sum(
            static fn ($item): int => self::itemRowCost($item),
        );

        // The first sheet also contains the document cards and side panels, so
        // its ending must move sooner than a continuation sheet.
        $limit = count($pages) === 1 ? 12 : 22;

        if ($lastPageCost <= $limit || $lastPage->count() <= 1) {
            return $pages;
        }

        $lastItem = $lastPage->pop();
        $pages[$lastIndex] = $lastPage;
        $pages[] = collect([$lastItem]);

        return $pages;
    }

    /**
     * The enlarged acceptance block has room for nine row slots. Reserve the
     * tenth line for a continuation so it never crowds the final boxes.
     *
     * @param  array<int, Collection<int, mixed>>  $pages
     * @return array<int, Collection<int, mixed>>
     */
    private static function reserveQuotationEnding(array $pages): array
    {
        $lastIndex = array_key_last($pages);
        $lastPage = $pages[$lastIndex];
        $lastPageCost = $lastPage->sum(
            static fn ($item): int => self::itemRowCost($item),
        );
        $limit = 9;

        if ($lastPageCost <= $limit || $lastPage->count() <= 1) {
            return $pages;
        }

        $lastItem = $lastPage->pop();
        $pages[$lastIndex] = $lastPage;
        $pages[] = collect([$lastItem]);

        return $pages;
    }
    /**
     * The logo as a data URI. Remote URLs would make the PDF depend on the
     * network at render time, so the file is read from disk and inlined.
     */
    private static function embeddedLogo(Invoice $invoice): ?string
    {
        $path = $invoice->logo_path
            ?: $invoice->fiscalProfile?->logo_path
            ?: 'logos/logo_tu_tecnico_autorizado.png';

        if (! $path) {
            return null;
        }

        $absolute = storage_path('app/public/'.$path);

        if (! is_file($absolute) && $path === 'logos/logo_tu_tecnico_autorizado.png') {
            $absolute = public_path('images/logo_tu_tecnico_autorizado.png');
        }

        if (! is_file($absolute)) {
            return null;
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($absolute) : null;

        return 'data:'.($mime ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($absolute));
    }

    private static function watermarkFor(Invoice $invoice): ?string
    {
        $status = strtolower((string) $invoice->status);
        $isQuotation = $invoice->isQuotation();

        return match (true) {
            in_array($status, ['cancelled', 'anulada'], true) => 'ANULADA',
            in_array($status, ['draft', 'borrador'], true) => 'BORRADOR',
            $isQuotation && $status === 'converted' => 'CONVERTIDO',
            $isQuotation => null,
            in_array($status, ['paid', 'pagada'], true) => 'COBRAT',
            default => null,
        };
    }
}
