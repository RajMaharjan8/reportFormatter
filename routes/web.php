<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\Report;
use App\Support\ReportCompiler;
use App\Support\ReportWord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/login', 'auth.login')->name('login');
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages::reports-index')->name('reports.index');

    Route::livewire('/check', 'pages::report-check')->name('reports.check');

    Route::livewire('/reports/create', 'pages::report-form')->name('reports.create');

    Route::livewire('/reports/{report}/edit', 'pages::report-form')
        ->name('reports.edit')
        ->can('update', 'report');

    Route::livewire('/reports/{report}/sections', 'pages::report-sections')
        ->name('reports.sections')
        ->can('update', 'report');

    Route::get('/reports/{report}/cover', function (Report $report) {
        return view('reports.cover', ['report' => $report]);
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
            'page_number_align' => 'nullable|in:left,center,right',
            'margin_top' => 'nullable|numeric|min:0.25|max:3',
            'margin_right' => 'nullable|numeric|min:0.25|max:3',
            'margin_bottom' => 'nullable|numeric|min:0.25|max:3',
            'margin_left' => 'nullable|numeric|min:0.25|max:3',
        ]);

        $update = [];

        foreach (['abstract', 'section_label', 'page_number_align'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = trim((string) $request->input($field));
            $update[$field] = $value === ''
                ? ($field === 'page_number_align' ? 'right' : null)
                : $value;
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
});
