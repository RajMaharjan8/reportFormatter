<x-mail::message>
# New feedback received

**From:** {{ $feedback->user?->name ?? 'Anonymous' }} ({{ $feedback->user?->email ?? 'no email' }})
**When:** {{ $feedback->created_at->format('d M Y, H:i') }}

@if ($feedback->working)
## What's working
{{ $feedback->working }}
@endif

@if ($feedback->not_working)
## What's not working
{{ $feedback->not_working }}
@endif

@if (!empty($feedback->images))
**Screenshots:** {{ count($feedback->images) }} image(s) attached to this email.
@endif

<x-mail::button :url="route('admin.feedback')">
View in admin
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
