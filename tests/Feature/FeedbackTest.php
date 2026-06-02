<?php

use App\Mail\FeedbackSubmitted;
use App\Models\Feedback;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('stores feedback and emails the admin notification address', function () {
    Mail::fake();
    Setting::set('admin_notification_email', 'inbox@example.com');

    $user = loginAsTestUser();

    Livewire::test('pages::reports-index')
        ->set('fb_working', 'The cover generator is great.')
        ->set('fb_not_working', 'Image upload is slow.')
        ->call('sendFeedback')
        ->assertHasNoErrors();

    $feedback = Feedback::first();

    expect($feedback)->not->toBeNull()
        ->and($feedback->user_id)->toBe($user->id)
        ->and($feedback->working)->toBe('The cover generator is great.');

    Mail::assertSent(FeedbackSubmitted::class, function ($mail) {
        return $mail->hasTo('inbox@example.com');
    });
});

it('requires at least one feedback field', function () {
    loginAsTestUser();

    Livewire::test('pages::reports-index')
        ->call('sendFeedback')
        ->assertHasErrors('fb_working');

    expect(Feedback::count())->toBe(0);
});
