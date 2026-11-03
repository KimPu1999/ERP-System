<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::create([
    'name' => 'MFA Repro Admin',
    'email' => 'mfa-repro@example.test',
    'password' => 'repro-password-1',
    'status' => 'active',
    'email_verified_at' => now(),
    'is_platform_super_admin' => true,
]);
echo "created user id: {$user->id}\n";
