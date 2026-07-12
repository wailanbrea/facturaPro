<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\InvoiceSignatureService;
use App\Services\TechnicalReportSignatureService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceVerificationController extends Controller
{
    public function __construct(
        private readonly InvoiceSignatureService $signature,
        private readonly TechnicalReportSignatureService $reportSignature,
    ) {}

    public function show(Request $request): View
    {
        $number = $request->query('number');
        $code = $request->query('code');

        $result = null;

        if (filled($number) || filled($code)) {
            $result = $this->signature->verifyByCode(
                is_string($number) ? $number : null,
                is_string($code) ? $code : null,
            );
            $result['type'] = 'invoice';

            if ($result['status'] === 'not_found') {
                $reportResult = $this->reportSignature->verifyByCode(
                    is_string($number) ? $number : null,
                    is_string($code) ? $code : null,
                );
                $reportResult['type'] = 'report';
                $result = $reportResult['status'] === 'not_found' ? $result : $reportResult;
            }
        }

        return view('invoices.verify', [
            'number' => is_string($number) ? $number : '',
            'code' => is_string($code) ? $code : '',
            'result' => $result,
        ]);
    }
}
