<?php

namespace Tests\Unit\Services;

use App\Services\InvoicePdfService;
use App\Services\ReportPdfService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class PdfChromeConfigurationTest extends TestCase
{
    #[DataProvider('services')]
    public function test_pdf_services_use_the_cacheable_chrome_path(string $service): void
    {
        config()->set('services.chrome.path', __FILE__);

        $method = new ReflectionMethod($service, 'chromeExecutable');

        $this->assertSame(__FILE__, $method->invoke(app($service)));
    }

    /** @return array<string, array{class-string}> */
    public static function services(): array
    {
        return [
            'invoice' => [InvoicePdfService::class],
            'report' => [ReportPdfService::class],
        ];
    }
}
