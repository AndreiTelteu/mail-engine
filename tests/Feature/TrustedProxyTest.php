<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('recognizes the protocol forwarded by a proxy', function (string $protocol, bool $secure): void {
    Route::get('/proxy-protocol', function (Request $request): array {
        return [
            'secure' => $request->isSecure(),
            'url' => url('/proxy-protocol'),
        ];
    });

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
        ->withHeaders(['X-Forwarded-Proto' => $protocol])
        ->get('http://localhost/proxy-protocol')
        ->assertOk()
        ->assertExactJson([
            'secure' => $secure,
            'url' => $protocol.'://localhost/proxy-protocol',
        ]);
})->with([
    'HTTPS' => ['https', true],
    'HTTP' => ['http', false],
]);
