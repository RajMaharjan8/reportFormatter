<?php

use Livewire\Livewire;

it('shows the error summary popup when the cover form fails validation', function () {
    loginAsTestUser();

    Livewire::test('pages::report-form')
        ->set('cover_format', 'tu')
        ->call('save')
        ->assertHasErrors(['tu_college_name', 'title'])
        ->assertSee('Please fix')
        ->assertSee('The campus name field is required.');
});

it('shows the error summary popup on the admin login form', function () {
    Livewire::test('pages::admin.login')
        ->call('authenticate')
        ->assertHasErrors(['email', 'password'])
        ->assertSee('Please fix');
});
