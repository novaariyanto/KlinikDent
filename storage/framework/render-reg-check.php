<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

view()->share('errors', new Illuminate\Support\ViewErrorBag);

$provinces = app(App\Support\Indonesia\WilayahService::class)->provinces();
echo 'service_count='.count($provinces).PHP_EOL;
echo 'first_key='.array_key_first($provinces).PHP_EOL;

$html = view('components.select', [
    'name' => 'province_code',
    'id' => 'province_code',
    'label' => 'Provinsi',
    'options' => $provinces,
    'placeholder' => 'Cari / pilih provinsi',
    'attributes' => new Illuminate\View\ComponentAttributeBag([
        'id' => 'province_code',
        'class' => 'js-wilayah-select',
        'data-placeholder' => 'Cari / pilih provinsi',
    ]),
])->render();

echo 'option_tags='.substr_count($html, '<option').PHP_EOL;
echo substr($html, 0, 500).PHP_EOL;
