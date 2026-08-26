<?php

use Illuminate\Support\Facades\Artisan;

test('tmaccountant command is registered and shows in artisan list', function () {
    $commands = Artisan::all();

    expect(isset($commands['tmaccountant']))->toBeTrue()
        ->and(isset($commands['tmaccountant:install']))->toBeTrue();
});
