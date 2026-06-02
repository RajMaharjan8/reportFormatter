<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\CoverTemplate;
use App\Models\Report;
use App\Support\ReportCompiler;
use App\Support\ReportWord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/login', 'auth.login')->name('login');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

// Admin sign-in is password based (regular users use Google), so it lives
// outside the auth group and the admin gate.
Route::livewire('/admin/login', 'pages::admin.login')->name('admin.login');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::livewire('/admin', 'pages::admin.dashboard')->name('admin.dashboard');
    Route::livewire('/admin/users', 'pages::admin.users')->name('admin.users');
    Route::livewire('/admin/feedback', 'pages::admin.feedback')->name('admin.feedback');
    Route::livewire('/admin/mail', 'pages::admin.mail')->name('admin.mail');
    Route::livewire('/admin/password', 'pages::admin.password')->name('admin.password');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages::reports-index')->name('reports.index');

    Route::livewire('/check', 'pages::report-check')->name('reports.check');

    Route::livewire('/reports/{report}/format-check', 'pages::report-live-check')
        ->name('reports.live-check')
        ->can('view', 'report');

    Route::livewire('/reports/create', 'pages::report-form')->name('reports.create');

    Route::livewire('/cover-templates', 'pages::cover-templates')->name('cover.templates');

    Route::livewire('/reports/{report}/edit', 'pages::report-form')
        ->name('reports.edit')
        ->can('update', 'report');

    Route::livewire('/reports/{report}/sections', 'pages::report-sections')
        ->name('reports.sections')
        ->can('update', 'report');

    Route::get('/reports/{report}/cover', function (Report $report, Request $request) {
        return view('reports.cover', [
            'report' => $report,
            'coverTemplates' => CoverTemplate::where('user_id', $request->user()->id)->latest()->get(),
        ]);
    })->name('reports.cover')->can('update', 'report');

    Route::get('/reports/{report}/output', function (Report $report) {
        return view('reports.output', [
            'report' => $report,
            'compiler' => ReportCompiler::for($report->load('sections')),
        ]);
    })->name('reports.output')->can('view', 'report');

    Route::get('/reports/{report}/docx', function (Report $report) {
        return ReportWord::download($report);
    })->name('reports.docx')->can('view', 'report');

    Route::post('/reports/{report}/cover/settings', function (Report $report, Request $request) {
        $request->validate([
            'abstract' => 'nullable|string|max:5000',
            'section_label' => 'nullable|string|max:30',
            'heading_align' => 'nullable|in:left,center,right',
            'heading_uppercase' => 'nullable|boolean',
            'page_number_align' => 'nullable|in:left,center,right',
            'margin_top' => 'nullable|numeric|min:0.25|max:3',
            'margin_right' => 'nullable|numeric|min:0.25|max:3',
            'margin_bottom' => 'nullable|numeric|min:0.25|max:3',
            'margin_left' => 'nullable|numeric|min:0.25|max:3',
        ]);

        $update = [];

        // Fields whose empty value falls back to a default rather than null.
        $defaults = ['page_number_align' => 'right', 'heading_align' => 'center'];

        foreach (['abstract', 'section_label', 'page_number_align', 'heading_align'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = trim((string) $request->input($field));
            $update[$field] = $value === '' ? ($defaults[$field] ?? null) : $value;
        }

        // The capitalize checkbox only submits when ticked, so resolve it from
        // the heading form's presence (signalled by the always-present select).
        if ($request->has('heading_align')) {
            $update['heading_uppercase'] = $request->boolean('heading_uppercase');
        }

        foreach (['margin_top', 'margin_right', 'margin_bottom', 'margin_left'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $update[$field] = (float) $request->input($field);
        }

        $report->update($update);

        return redirect()
            ->route('reports.cover', ['report' => $report])
            ->with('cover-saved', 'Saved.');
    })->name('reports.cover.settings')->can('update', 'report');

    // Persist hand-edited front pages (cover, declaration, recommendation,
    // certificate) from the preview's "Edit pages" mode.
    Route::post('/reports/{report}/front-overrides', function (Report $report, Request $request) {
        // No length cap: section/cover HTML can embed large base64 images, and
        // a tight limit silently rejected saves (the edit appeared to revert).
        $validated = $request->validate([
            'blocks' => 'required|array',
            'blocks.*' => 'nullable|string',
            'sections' => 'nullable|array',
            'sections.*' => 'nullable|string',
        ]);

        $allowed = $report->editableFrontBlocks();
        $overrides = $report->front_overrides ?? [];

        foreach ($validated['blocks'] as $key => $html) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $html = trim((string) $html);

            if ($html === '') {
                unset($overrides[$key]);
            } else {
                $overrides[$key] = $html;
            }
        }

        $report->update(['front_overrides' => $overrides === [] ? null : $overrides]);

        // Inline body / front-page edits save straight to the section source so
        // the compiler can re-apply heading numbers and citations on render.
        foreach ($validated['sections'] ?? [] as $id => $html) {
            $report->sections()->whereKey((int) $id)->first()?->update(['content' => (string) $html]);
        }

        return redirect()
            ->route('reports.output', ['report' => $report])
            ->with('cover-saved', 'Your edits were saved.');
    })->name('reports.front-overrides.save')->can('update', 'report');

    // Discard all hand-edits and fall back to the generated templates.
    Route::post('/reports/{report}/front-overrides/reset', function (Report $report) {
        $report->update(['front_overrides' => null]);

        return redirect()
            ->route('reports.output', ['report' => $report])
            ->with('cover-saved', 'Pages reset to the generated template.');
    })->name('reports.front-overrides.reset')->can('update', 'report');

    // Apply one of the user's saved custom covers to this report.
    Route::post('/reports/{report}/cover/use-template', function (Report $report, Request $request) {
        $validated = $request->validate(['template_id' => 'required|integer']);

        $template = CoverTemplate::where('user_id', $request->user()->id)
            ->findOrFail($validated['template_id']);

        // Wrap the designer's content in a self-contained A4 sheet so it
        // renders the same everywhere the cover override is shown.
        $overrides = $report->front_overrides ?? [];
        $overrides['cover'] = '<div class="cover-sheet-custom cover-custom">'.$template->html.'</div>';
        $report->update(['front_overrides' => $overrides]);

        return redirect()
            ->route('reports.cover', ['report' => $report])
            ->with('cover-saved', 'Applied your custom cover “'.$template->name.'”.');
    })->name('reports.cover.use-template')->can('update', 'report');
});
