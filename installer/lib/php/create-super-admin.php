<?php

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = (string) env('ADMIN_EMAIL', 'admin@example.com');
$password = (string) env('ADMIN_PASSWORD', 'password');

if ($email === '') {
    fwrite(STDERR, "ADMIN_EMAIL kosong.\n");
    exit(1);
}

$user = App\Models\User::query()->updateOrCreate(
    ['email' => $email],
    [
        'name' => 'Super Admin SaaS',
        'password' => $password,
        'status' => App\Enums\UserStatus::Active,
        'email_verified_at' => now(),
        'tenant_id' => null,
        'branch_id' => null,
    ]
);

$user->syncRoles([App\Enums\RoleName::SuperAdminSaas->value]);

echo $user->email."\n";
