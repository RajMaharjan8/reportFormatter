{{--
    A dismissible, fixed-position popup that summarises all current validation
    errors. Renders only when the component has errors. The wire:key is derived
    from the error messages so a fresh set of errors re-shows the popup even if
    the user previously dismissed it.
--}}
@if ($errors->any())
    <div
        wire:key="validation-popup-{{ md5(implode('|', $errors->all())) }}"
        x-data="{ show: true }"
        x-show="show"
        x-cloak
        x-transition.opacity
        class="fixed inset-x-0 top-4 z-[60] flex justify-center px-4"
    >
        <div class="w-full max-w-md rounded-lg bg-red-50 p-4 shadow-lg ring-1 ring-red-200">
            <div class="flex items-start gap-3">
                <span aria-hidden="true" class="mt-0.5 text-lg leading-none text-red-500">&#9888;</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-red-800">
                        Please fix {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('issue', $errors->count()) }} below:
                    </p>
                    <ul class="mt-1.5 list-disc space-y-0.5 pl-5 text-xs text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" x-on:click="show = false" class="-mr-1 -mt-1 shrink-0 rounded p-1 text-red-400 hover:text-red-700" title="Dismiss">&times;</button>
            </div>
        </div>
    </div>
@endif
