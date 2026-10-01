<?php

// routes/notification.php

use AndyDefer\LaravelNotification\Actions\PingFcmDeviceAction;
use AndyDefer\LaravelNotification\Actions\PingWebPushSubscriptionAction;
use AndyDefer\LaravelNotification\Actions\PusherAuthAction;
use AndyDefer\LaravelNotification\Actions\RegisterFcmDeviceAction;
use AndyDefer\LaravelNotification\Actions\RegisterWebPushSubscriptionAction;
use AndyDefer\LaravelNotification\Http\Requests\PingFcmDeviceRequest;
use AndyDefer\LaravelNotification\Http\Requests\PingWebPushSubscriptionRequest;
use AndyDefer\LaravelNotification\Http\Requests\PusherAuthRequest;
use AndyDefer\LaravelNotification\Http\Requests\RegisterFcmDeviceRequest;
use AndyDefer\LaravelNotification\Http\Requests\RegisterWebPushSubscriptionRequest;
use Illuminate\Support\Facades\Route;

Route::middleware('nemesis.token')
    ->prefix('notification')
    ->name('notification.')
    ->group(function (): void {
        Route::post('/ping-fcm-device', action_route(
            PingFcmDeviceRequest::class,
            PingFcmDeviceAction::class,
        ))->name('ping-fcm-device');

        Route::post('/ping-webpush-subscription', action_route(
            PingWebPushSubscriptionRequest::class,
            PingWebPushSubscriptionAction::class,
        ))->name('ping-webpush-subscription');

        Route::post('/pusher-auth', action_route(PusherAuthRequest::class, PusherAuthAction::class))
            ->name('pusher-auth');

        Route::post('/register-fcm-device', action_route(RegisterFcmDeviceRequest::class, RegisterFcmDeviceAction::class))
            ->name('register-fcm-device');

        Route::post('/register-webpush-subscription', action_route(
            RegisterWebPushSubscriptionRequest::class,
            RegisterWebPushSubscriptionAction::class,
        ))->name('register-webpush-subscription');
    });
