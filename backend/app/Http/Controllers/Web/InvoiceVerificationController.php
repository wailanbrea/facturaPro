<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\InvoiceSignatureService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceVerificationController extends Controller
{
    public function __construct(private readonly InvoiceSignatureService $signature) {}

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
        }

        return view('invoices.verify', [
            'number' => is_string($number) ? $number : '',
            'code' => is_string($code) ? $code : '',
            'result' => $result,
        ]);
    }
}
