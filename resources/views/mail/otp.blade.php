<x-mail::message>
# {{ $isReset ? 'Reset your password' : 'Confirm your email' }}

{{ $isReset
    ? 'Use the code below to reset your password. If you did not request this, you can safely ignore this email.'
    : 'Thanks for signing up! Enter the code below to verify your email and activate your account.' }}

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

This code expires in {{ $minutes }} minutes.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
