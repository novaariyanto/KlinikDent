<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

view()->share('errors', new Illuminate\Support\ViewErrorBag);

$user = App\Models\User::query()->first();
if ($user) {
    auth()->login($user);
}

$html = view('registration.visits.create', [
    'patient' => null,
    'payers' => collect(),
    'branches' => collect(),
    'rooms' => collect(),
    'doctors' => collect(),
    'genders' => [],
    'defaultBranchId' => null,
    'provinces' => ['33' => '33 Jawa Tengah'],
    'cities' => [],
    'districts' => [],
    'villages' => [],
])->render();

$head = substr($html, 0, (int) strpos($html, '</head>'));
echo 'overflowFix='.(str_contains($html, 'overflow: visible !important') ? 'yes' : 'no').PHP_EOL;
$start = strpos($html, 'id="province_code"');
echo substr($html, max(0, $start - 200), 900).PHP_EOL;
