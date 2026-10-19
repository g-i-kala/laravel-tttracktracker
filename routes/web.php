<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\TrackListController;
use App\Livewire\Dashboard;
use App\Livewire\Exports;
use App\Livewire\Imports;
use App\Livewire\Library;
use App\Livewire\Settings;
use App\Services\TrackListExportService;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Dashboard::class)->name('dashboard');
Route::livewire('/library', Library\Index::class)->name('library.index');
Route::livewire('/imports', Imports\Index::class)->name('imports.index');
Route::livewire('/imports/create', Imports\Create::class)->name('imports.create');
Route::livewire('/imports/{batch}', Imports\Show::class)->whereNumber('batch')->name('imports.show');
Route::livewire('/imports/{batch}/review', Imports\Review::class)->name('imports.review');
Route::get('/imports/{batch}/track-list/{format}', [TrackListController::class, 'download'])
    ->whereNumber('batch')
    ->whereIn('format', TrackListExportService::FORMATS)
    ->name('imports.track-list');
Route::livewire('/settings/ai', Settings\Ai::class)->name('settings.ai');
Route::livewire('/settings/youtube', Settings\Youtube::class)->name('settings.youtube');
Route::livewire('/exports', Exports\Index::class)->name('exports.index');
Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
