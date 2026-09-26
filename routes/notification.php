<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Actions\PusherAuthAction;
use AndyDefer\LaravelNotification\Http\Requests\PusherAuthRequest;
use Illuminate\Support\Facades\Route;

Route::post('/pusher/auth', action_route(PusherAuthRequest::class, PusherAuthAction::class))
    ->name('laravel-notification.pusher.auth')
    ->middleware('nemesis.token');
