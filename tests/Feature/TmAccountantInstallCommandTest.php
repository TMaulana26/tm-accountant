<?php

use App\Services\System\EnvironmentService;
use Illuminate\Support\Facades\Artisan;

test('tmaccountant command is registered and shows in artisan list', function () {
    $commands = Artisan::all();

    expect(isset($commands['tmaccountant']))->toBeTrue()
        ->and(isset($commands['tmaccountant:install']))->toBeTrue();
});

test('tmaccountant asks for gender and sets APP_OWNER_GENDER', function () {
    $mockEnv = mock(EnvironmentService::class);
    $mockEnv->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($values) {
            return ($values['APP_OWNER_GENDER'] ?? null) === 'perempuan';
        }))
        ->andReturn(true);

    $this->app->instance(EnvironmentService::class, $mockEnv);

    $this->artisan('tmaccountant')
        ->expectsQuestion('Admin / Owner Name', 'Teh Rina')
        ->expectsQuestion('Admin Login Email', 'rina@example.com')
        ->expectsQuestion('Admin Login Password [default: password123]', 'secret123')
        ->expectsChoice('Jenis Kelamin Pemilik (untuk sapaan AI: Akang / Teteh)', 'Perempuan (Panggilan: Teteh / Teh)', [
            'Laki-laki (Panggilan: Akang / Kang)',
            'Perempuan (Panggilan: Teteh / Teh)',
        ])
        ->expectsConfirmation('Would you like to configure the Telegram Bot now?', 'no')
        ->expectsConfirmation('Would you like to configure the AI Provider now?', 'no')
        ->expectsOutputToContain('Panggilan AI: Teteh')
        ->assertSuccessful();
});
