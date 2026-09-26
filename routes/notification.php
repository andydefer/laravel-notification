<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Actions\PusherAuthAction;
use AndyDefer\LaravelNotification\Actions\RegisterFcmDeviceAction;
use AndyDefer\LaravelNotification\Http\Requests\PusherAuthRequest;
use AndyDefer\LaravelNotification\Http\Requests\RegisterFcmDeviceRequest;
use Illuminate\Support\Facades\Route;

Route::post('/pusher/auth', action_route(PusherAuthRequest::class, PusherAuthAction::class))
    ->name('laravel-notification.pusher.auth')
    ->middleware('nemesis.token');

Route::post('/fcm/devices', action_route(RegisterFcmDeviceRequest::class, RegisterFcmDeviceAction::class))
    ->name('laravel-notification.fcm.devices.register')
    ->middleware('nemesis.token');
