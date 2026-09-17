<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('clinic.view'), 403);

        $modules = collect([
            ['title' => 'Layanan', 'route' => 'management.services.index', 'permission' => 'service.view', 'icon' => 'bx bx-list-check', 'description' => 'Jenis layanan klinik.'],
            ['title' => 'Tindakan', 'route' => 'management.procedures.index', 'permission' => 'procedure.view', 'icon' => 'bx bx-first-aid', 'description' => 'Kode dan kategori tindakan medis.'],
            ['title' => 'Tarif', 'route' => 'management.tariffs.index', 'permission' => 'tariff.view', 'icon' => 'bx bx-purchase-tag', 'description' => 'Harga per tindakan, cabang, dan penjamin.'],
            ['title' => 'Obat', 'route' => 'management.medicines.index', 'permission' => 'medicine.view', 'icon' => 'bx bx-plus-medical', 'description' => 'Master obat (tanpa stok).'],
            ['title' => 'Penjamin', 'route' => 'management.payers.index', 'permission' => 'payer.view', 'icon' => 'bx bx-id-card', 'description' => 'Umum, BPJS, asuransi, corporate, membership.'],
            ['title' => 'Ruangan', 'route' => 'management.rooms.index', 'permission' => 'room.view', 'icon' => 'bx bx-door-open', 'description' => 'Poli dan ruangan per cabang.'],
        ])->filter(fn (array $module) => auth()->user()?->can($module['permission']))->values();

        return view('management.clinic.index', compact('modules'));
    }
}
