<?php

it('ships the PWA manifest, service worker, offline page and icons', function () {
    expect(public_path('manifest.webmanifest'))->toBeReadableFile()
        ->and(public_path('sw.js'))->toBeReadableFile()
        ->and(public_path('offline.html'))->toBeReadableFile()
        ->and(public_path('icons/icon-192.png'))->toBeReadableFile()
        ->and(public_path('icons/icon-512.png'))->toBeReadableFile()
        ->and(public_path('icons/icon-512-maskable.png'))->toBeReadableFile();
});

it('has a valid web app manifest', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest)->toBeArray()
        ->and($manifest['name'])->not->toBeEmpty()
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['icons'])->toHaveCount(3);

    $purposes = array_column($manifest['icons'], 'purpose');
    expect($purposes)->toContain('any')->toContain('maskable');
});

it('links the manifest and theme colour on the login page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('apple-touch-icon', false);
});

it('links the manifest on the authenticated dashboard', function () {
    loginAsTestUser();

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee('manifest.webmanifest', false);
});
