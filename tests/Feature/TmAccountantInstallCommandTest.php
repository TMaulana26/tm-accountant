<?php

use App\Models\User;
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

test('tmaccountant safely updates when email already exists on another user record without unique constraint violation', function () {
    User::query()->delete();
    $admin1 = User::factory()->create([
        'name' => 'Admin Default',
        'email' => 'admin@example.com',
    ]);
    $user2 = User::factory()->create([
        'name' => 'Existing Tama',
        'email' => 'mtim0343@gmail.com',
    ]);

    $mockEnv = mock(EnvironmentService::class);
    $mockEnv->shouldReceive('update')->once()->andReturn(true);
    $this->app->instance(EnvironmentService::class, $mockEnv);

    $this->artisan('tmaccountant')
        ->expectsQuestion('Admin / Owner Name', 'Tama')
        ->expectsQuestion('Admin Login Email', 'mtim0343@gmail.com')
        ->expectsQuestion('Admin Login Password [default: password123]', '')
        ->expectsChoice('Jenis Kelamin Pemilik (untuk sapaan AI: Akang / Teteh)', 'Laki-laki (Panggilan: Akang / Kang)', [
            'Laki-laki (Panggilan: Akang / Kang)',
            'Perempuan (Panggilan: Teteh / Teh)',
        ])
        ->expectsConfirmation('Would you like to configure the Telegram Bot now?', 'no')
        ->expectsConfirmation('Would you like to configure the AI Provider now?', 'no')
        ->expectsOutputToContain('Admin / Owner account [Tama (mtim0343@gmail.com)] is ready')
        ->assertSuccessful();

    $user2->refresh();
    expect($user2->name)->toBe('Tama')
        ->and(User::where('email', 'admin@example.com')->count())->toBe(0);
});
