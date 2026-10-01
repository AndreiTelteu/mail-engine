<?php

test('the app templates link the favicon and installable app manifest', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('href="/favicon.svg"', false)
        ->assertSee('rel="manifest" href="/manifest.json"', false);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="/favicon.svg"', false)
        ->assertSee('rel="manifest" href="/manifest.json"', false);
});

test('the app manifest references valid, correctly sized icons', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['start_url'])->toBe('/emails')
        ->and($manifest['scope'])->toBe('/')
        ->and($manifest['display'])->toBe('standalone');

    foreach ($manifest['icons'] as $icon) {
        $path = public_path(ltrim($icon['src'], '/'));

        expect($path)->toBeFile();

        if ($icon['type'] === 'image/png') {
            [$width, $height] = getimagesize($path);
            expect("{$width}x{$height}")->toBe($icon['sizes']);
        }
    }

    expect(getimagesize(public_path('favicon.ico')))->not->toBeFalse();
});
