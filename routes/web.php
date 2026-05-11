<?php

use App\Http\Controllers\DownloadController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Auth/Login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::controller(FileController::class)->group(function () {
        Route::get('/my-files/{folder?}', 'index')->where('folder', '(.*)')->name('myFiles');
        Route::get('/shared-with-me/{folder?}', 'sharedWithMe')->where('folder', '(.*)')->name('sharedWithMe');
        Route::get('/shared-by-me/{folder?}', 'sharedByMe')->where('folder', '(.*)')->name('sharedByMe');
        Route::get('/trash', 'trash')->name('trash');

        Route::post('/folder/create', 'createFolder')->name('folder.create');
    });

    Route::name('files')->prefix('files')->controller(FileController::class)->group(function () {
        Route::post('/', 'storeFiles')->name('.store');
        Route::delete('/', 'destroy')->name('.destroy');
        Route::post('/restore', 'restore')->name('.restore');
        Route::delete('/delete-forever', 'deleteForever')->name('.deleteForever');

        Route::post('share', 'share')->name('.share');
        Route::post('/toggle-favourite', 'toggleFavourite')->name('.toggleFavourite');
        Route::post('/rename', 'rename')->name('.rename');
        Route::post('/move', 'move')->name('.move');
    });

    Route::name('files')->prefix('files')->controller(DownloadController::class)->group(function () {
        Route::get('/download', 'fromMyFiles')->name('.download')->middleware('throttle:60,1');
        Route::get('/download/shared-with-me', 'sharedWithMe')->name('.downloadSharedWithMe')->middleware('throttle:60,1');
        Route::get('/download/shared-by-me', 'sharedByMe')->name('.downloadSharedByMe')->middleware('throttle:60,1');
        Route::get('/preview/{file}', 'preview')->name('.preview')->middleware('throttle:120,1');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
