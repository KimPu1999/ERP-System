<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "db: ".DB::connection()->getDatabaseName()."\n";
echo "users before: ".App\Models\User::count()."\n";
$user = App\Models\User::create([
    'name' => 'MFA Repro Admin',
    'email' => 'mfa-repro@example.test',
    'password' => 'repro-password-1',
    'status' => 'active',
    'email_verified_at' => now(),
    'is_platform_super_admin' => true,
]);
echo "created id: {$user->id}\n";
echo "users after: ".App\Models\User::count()."\n";
echo "raw count: ".DB::table('users')->count()."\n";
echo "rls: ".DB::select("SELECT relname, relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname='users'")[0]->relrowsecurity."\n";
