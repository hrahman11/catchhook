<?php

use App\Models\CapturedRequest;

it('stores UTF-8 bodies as readable text', function () {
    $request = (new CapturedRequest)->setRawBody('{"name":"Zoë 🚀"}');

    expect($request)
        ->body->toBe('{"name":"Zoë 🚀"}')
        ->body_encoding->toBe(CapturedRequest::ENCODING_UTF8)
        ->and($request->isBinary())->toBeFalse();
});

it('base64-encodes bodies that are not valid text', function (string $bytes) {
    $request = (new CapturedRequest)->setRawBody($bytes);

    expect($request->body_encoding)->toBe(CapturedRequest::ENCODING_BASE64)
        ->and($request->body)->toBe(base64_encode($bytes))
        ->and($request->rawBody())->toBe($bytes);
})->with([
    'invalid utf-8' => "\xC3\x28",
    'null byte' => "abc\0def",
]);

it('treats an empty body as null', function () {
    $request = (new CapturedRequest)->setRawBody('');

    expect($request)
        ->body->toBeNull()
        ->size->toBe(0)
        ->and($request->rawBody())->toBe('');
});
