<?php

use App\Livewire\Dashboard;
use App\Livewire\Imports;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Dashboard::class)->name('dashboard');
Route::livewire('/imports', Imports\Index::class)->name('imports.index');
Route::livewire('/imports/create', Imports\Create::class)->name('imports.create');
Route::livewire('/imports/{batch}/review', Imports\Review::class)->name('imports.review');
