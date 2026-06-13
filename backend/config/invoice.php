<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice signing key
    |--------------------------------------------------------------------------
    |
    | Secret key used to compute the HMAC-SHA256 signature that proves an
    | invoice was issued by this system. It MUST stay on the server and out of
    | the database: a database dump alone must not be enough to forge invoices.
    |
    | Set INVOICE_SIGNING_KEY in the environment (recommended, so it can be
    | rotated independently of APP_KEY). If it is absent we derive a stable
    | internal key from APP_KEY so the feature works out of the box.
    |
    */

    'signing_key' => env('INVOICE_SIGNING_KEY'),

    /*
    | Verification base URL printed inside the QR code. Defaults to the app URL
    | plus the internal verification route.
    */

    'verification_url' => env('INVOICE_VERIFICATION_URL'),

];
