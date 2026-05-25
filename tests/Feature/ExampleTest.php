<?php

test('returns a successful response', function () {
    loginAsTestUser();

    $response = $this->get('/');

    $response->assertOk();
});

test('the dashboard redirects to login for guests', function () {
    $this->get('/')->assertRedirect(route('login'));
});
