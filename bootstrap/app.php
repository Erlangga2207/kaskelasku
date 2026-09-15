<?php

use App\Http\Middleware\ResolveClassroomFromToken;
use App\Http\Middleware\SetCurrentClassroom;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'kelas' => SetCurrentClassroom::class,
            'kelas.token' => ResolveClassroomFromToken::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create()
    // Susunan deploy di Hostinger tidak standar: isi folder public/ dipindah ke
    // document root (public_html), jadi root project == document root. Tanpa baris
    // ini public_path() masih menunjuk ke base_path('public') yang sudah tidak ada,
    // dan Vite gagal menemukan build/manifest.json.
    ->usePublicPath(dirname(__DIR__));
