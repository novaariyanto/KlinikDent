@php
    use App\Support\Clinical\CareTabs;
    use App\Support\Clinical\ExaminationQueueRow;
@endphp

<x-table class="ppa-exam">
    <thead>
        <tr>
            <th class="text-nowrap">Aksi</th>
            <th class="text-nowrap">No Antrian</th>
            <th class="text-nowrap">PPA</th>
            <th class="text-nowrap">Registrasi</th>
            <th class="text-nowrap">Poli/Ruang</th>
            <th class="text-nowrap">Dokter &amp; Perawat</th>
            <th class="text-nowrap">Data Pasien</th>
            <th class="text-nowrap">Penjamin</th>
            <th class="text-nowrap">Asesmen</th>
            <th class="text-nowrap">Rekam Medis</th>
            <th class="text-nowrap">Resep</th>
            <th class="text-nowrap">Bridging Pelayanan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($visits as $visit)
            @php
                $row = new ExaminationQueueRow($visit);
                $nurse = $row->nurseStatus();
                $doctor = $row->doctorStatus();
                $discharge = $row->dischargeStatus();
                $pcare = $row->pcareStatus();
                $satuSehat = $row->satuSehatStatus();
                $patient = $visit->patient;
                $invoice = $visit->currentInvoice();
                $prescription = $visit->currentPharmacyPrescription();
            @endphp
            <tr>
                <td>
                    <div class="d-flex flex-column gap-1">
                        @if ($prescription && auth()->user()?->can('prescription.view') && auth()->user()?->can('pharmacy.view'))
                            <a href="{{ route('pharmacy.prescriptions.show', ['prescription' => $prescription, 'from' => 'pelayanan']) }}" class="btn btn-sm btn-primary text-nowrap">
                                <i class="bx bx-package me-1"></i>Obat
                            </a>
                        @endif
                        @if (CareTabs::canAccessCare(auth()->user()))
                            <a href="{{ route('care.show', ['visit' => $visit, 'tab' => 'soap', 'from' => 'pelayanan']) }}" class="btn btn-sm {{ $row->screeningDone() ? 'btn-success' : 'btn-warning' }} text-nowrap">
                                <i class="bx {{ $row->screeningDone() ? 'bx-check-shield' : 'bx-error-circle' }} me-1"></i>
                                Skrining
                            </a>
                        @elseif ($row->screeningDone())
                            <span class="badge bg-success text-nowrap"><i class="bx bx-check-shield me-1"></i>Skrining</span>
                        @else
                            <span class="badge bg-warning text-nowrap"><i class="bx bx-error-circle me-1"></i>Skrining</span>
                        @endif
                        @can('viewAny', App\Models\Invoice::class)
                            @if ($invoice)
                                <a href="{{ route('billing.invoices.show', ['invoice' => $invoice, 'from' => 'pelayanan']) }}" class="btn btn-sm btn-soft-info text-nowrap">
                                    <i class="bx bx-receipt me-1"></i>Tagihan
                                </a>
                            @elseif (auth()->user()?->can('create', App\Models\Invoice::class))
                                <form action="{{ route('billing.invoices.generate') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="visit_id" value="{{ $visit->id }}">
                                    <input type="hidden" name="from" value="pelayanan">
                                    <button class="btn btn-sm btn-soft-info text-nowrap" type="submit">
                                        <i class="bx bx-receipt me-1"></i>Tagihan
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </td>
                <td>
                    <div class="ppa-queue">{{ $row->queueNumber() }}</div>
                    @if ($visit->queue)
                        @can('call', $visit->queue)
                            <form action="{{ route('queue.call', $visit->queue) }}" method="POST" class="mt-1">
                                @csrf
                                <button class="btn btn-sm btn-soft-warning" type="submit">
                                    <i class="bx bx-bell me-1"></i>Panggil
                                </button>
                            </form>
                        @endcan
                    @endif
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <div>
                            <span class="ppa-kicker">Perawat</span>
                            <span class="{{ $nurse['class'] }}">{{ $nurse['label'] }}</span>
                        </div>
                        <div>
                            <span class="ppa-kicker">Dokter</span>
                            <span class="{{ $doctor['class'] }}">{{ $doctor['label'] }}</span>
                        </div>
                        @if ($visit->queue && $visit->status === \App\Enums\VisitStatus::InService)
                            @can('complete', $visit->queue)
                                <form action="{{ route('queue.complete', $visit->queue) }}" method="POST" onsubmit="return confirm('Selesaikan pemeriksaan pasien ini?')">
                                    @csrf
                                    <button class="btn btn-sm btn-soft-success" type="submit">
                                        <i class="bx bx-check me-1"></i>Selesai
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </td>
                <td>
                    <div class="ppa-name">{{ $row->registrationNumber() }}</div>
                    <div class="ppa-meta">{{ $row->registrationDate() }}</div>
                    <span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span>
                </td>
                <td>
                    <div class="ppa-name">{{ $row->visitKind() }}</div>
                    <div class="ppa-meta">Poli: {{ $row->poliName() }}</div>
                    <div class="ppa-meta">Ruang: {{ $row->roomName() }}</div>
                </td>
                <td>
                    <div>
                        <span class="ppa-kicker">Dokter</span>
                        <div class="ppa-name">{{ $row->doctorName() }}</div>
                    </div>
                    <div class="mt-1">
                        <span class="ppa-kicker">Perawat</span>
                        <div class="ppa-meta">Belum ditugaskan</div>
                    </div>
                </td>
                <td>
                    <div class="ppa-name">{{ $patient?->name ?: '-' }}</div>
                    <div class="ppa-meta">RM {{ $patient?->medical_record_number ?: '-' }}</div>
                    <div class="ppa-meta">{{ $patient?->gender?->label() ?: '-' }}</div>
                    <div class="ppa-meta">{{ $patient?->dob?->format('d-m-Y') ?: '-' }}</div>
                </td>
                <td>
                    <div class="ppa-name">{{ $row->payerName() }}</div>
                    @if ($row->payerCard())
                        <div class="ppa-meta">{{ $row->payerCard() }}</div>
                    @endif
                </td>
                <td>
                    @if (CareTabs::canAccessCare(auth()->user()))
                        <a href="{{ route('care.show', ['visit' => $visit, 'tab' => 'soap', 'from' => 'pelayanan']) }}" class="btn btn-sm btn-light text-nowrap">
                            <i class="bx bx-clipboard me-1"></i>Asesmen ulang
                        </a>
                        <div class="mt-2">
                            <span class="ppa-kicker">Renc. pulang</span>
                            <a href="{{ route('care.show', ['visit' => $visit, 'tab' => 'ringkasan']) }}" class="{{ $discharge['class'] }}">{{ $discharge['label'] }}</a>
                        </div>
                    @else
                        <span class="{{ $discharge['class'] }}">{{ $discharge['label'] }}</span>
                    @endif
                </td>
                <td>
                    @if (CareTabs::canAccessCare(auth()->user()))
                        <a href="{{ route('care.discharge.pdf', $visit) }}" class="btn btn-sm btn-primary text-nowrap" target="_blank" rel="noopener">
                            <i class="bx bx-folder-open me-1"></i>Open
                        </a>
                    @else
                        <span class="ppa-meta">-</span>
                    @endif
                </td>
                <td>
                    @if (auth()->user()?->can('prescription.view') && auth()->user()?->can('pharmacy.view'))
                        @if ($prescription)
                            <div class="ppa-name">#{{ $prescription->id }}</div>
                            <span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span>
                            <div class="mt-1">
                                <a href="{{ route('pharmacy.prescriptions.show', ['prescription' => $prescription, 'from' => 'pelayanan']) }}" class="btn btn-sm btn-soft-info text-nowrap">
                                    <i class="bx bx-package me-1"></i>Farmasi
                                </a>
                            </div>
                        @else
                            <span class="ppa-meta">Belum ada</span>
                        @endif
                    @else
                        <span class="ppa-meta">-</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <div>
                            <span class="ppa-kicker">PCare</span>
                            <span class="{{ $pcare['class'] }}">{{ $pcare['label'] }}</span>
                        </div>
                        <div>
                            <span class="ppa-kicker">Satu Sehat</span>
                            <span class="{{ $satuSehat['class'] }}">{{ $satuSehat['label'] }}</span>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="12" class="text-muted">
                    @if ($isToday && blank($filters['status']) && blank($filters['q']))
                        Belum ada kunjungan hari ini. Pasien yang sudah didaftarkan akan muncul di sini untuk dipanggil dan dilayani.
                    @else
                        Tidak ada kunjungan untuk filter yang dipilih.
                    @endif
                </td>
            </tr>
        @endforelse
    </tbody>
</x-table>
