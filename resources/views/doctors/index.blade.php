@extends('layouts.app')

@section('title', 'Dokter & Tenaga Medis')

@section('content')
    @php
        $hasFilter = $filters['q'] !== '' || $filters['role'] !== '' || $filters['status'] !== '' || $filters['branch'] !== '';
        $tones = ['primary', 'info', 'success', 'warning'];
    @endphp

    <x-page-header title="Dokter & Tenaga Medis" :breadcrumb="['Tenaga Medis' => route('doctors.index')]" />
    <x-alert />

    <div class="row">
        @foreach ([
            ['label' => 'Semua', 'value' => $stats['total'], 'icon' => 'bx bx-group', 'color' => 'primary', 'query' => []],
            ['label' => 'Aktif praktik', 'value' => $stats['active'], 'icon' => 'bx bx-check-circle', 'color' => 'success', 'query' => ['status' => 'active']],
            ['label' => 'Dokter gigi', 'value' => $stats['dentists'], 'icon' => 'bx bx-plus-medical', 'color' => 'info', 'query' => ['role' => 'dentist']],
            ['label' => 'Nonaktif', 'value' => $stats['inactive'], 'icon' => 'bx bx-user-x', 'color' => 'warning', 'query' => ['status' => 'inactive']],
        ] as $stat)
            <div class="col-md-6 col-xl-3">
                <a href="{{ route('doctors.index', $stat['query']) }}" class="text-reset">
                    <div class="card mini-stats-wid">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <p class="text-muted fw-medium mb-1">{{ $stat['label'] }}</p>
                                    <h4 class="mb-0">{{ $stat['value'] }}</h4>
                                </div>
                                <div class="avatar-sm rounded-circle bg-{{ $stat['color'] }} align-self-center mini-stat-icon">
                                    <span class="avatar-title rounded-circle bg-{{ $stat['color'] }}">
                                        <i class="{{ $stat['icon'] }} font-size-24"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <x-card title="Daftar Tenaga Medis">
        <x-slot:header-actions>
            @can('schedule.view')
                <x-button href="{{ route('doctors.schedule') }}" variant="secondary" icon="bx bx-calendar">Jadwal</x-button>
            @endcan
            @can('create', App\Models\Doctor::class)
                <x-button href="{{ route('doctors.create') }}" icon="bx bx-plus">Tambah Tenaga Medis</x-button>
            @endcan
        </x-slot:header-actions>

        <form method="GET" action="{{ route('doctors.index') }}" class="row g-2 align-items-end mb-4">
            <div class="col-md-4">
                <label for="doctor-q" class="form-label">Cari</label>
                <input id="doctor-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Nama, email, SIP, atau NIK">
            </div>
            <div class="col-md-3">
                <label for="doctor-role" class="form-label">Peran</label>
                <select id="doctor-role" name="role" class="form-select">
                    <option value="">Semua peran</option>
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="doctor-status" class="form-label">Status</label>
                <select id="doctor-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="doctor-branch" class="form-label">Cabang</label>
                <select id="doctor-branch" name="branch" class="form-select">
                    <option value="">Semua cabang</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) $filters['branch'] === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <x-button type="submit" icon="bx bx-search">Terapkan</x-button>
                @if ($hasFilter)
                    <x-button href="{{ route('doctors.index') }}" variant="secondary">Reset</x-button>
                @endif
            </div>
        </form>

        @if ($doctors->isEmpty())
            <div class="text-center py-5">
                <div class="avatar-md mx-auto mb-3">
                    <span class="avatar-title rounded-circle bg-primary-subtle text-primary font-size-24">
                        <i class="bx bx-user-voice"></i>
                    </span>
                </div>
                <h5 class="mb-1">{{ $hasFilter ? 'Tidak ada yang cocok' : 'Belum ada tenaga medis' }}</h5>
                <p class="text-muted mb-0">
                    {{ $hasFilter ? 'Ubah kata kunci atau filter, lalu coba lagi.' : 'Tambahkan dokter, asisten, atau perawat untuk mulai mengatur jadwal praktik.' }}
                </p>
            </div>
        @else
            <div class="row">
                @foreach ($doctors as $doctor)
                    @php
                        $parts = preg_split('/\s+/', trim($doctor->displayName())) ?: [];
                        $initials = mb_strtoupper(mb_substr((string) ($parts[0] ?? 'T'), 0, 1).mb_substr((string) ($parts[1] ?? ''), 0, 1));
                        $tone = $tones[$doctor->id % count($tones)];
                        $branchNames = $doctor->user?->branches->pluck('name') ?? collect();
                        if ($branchNames->isEmpty() && $doctor->user?->branch) {
                            $branchNames = collect([$doctor->user->branch->name]);
                        }
                    @endphp
                    <div class="col-md-6 col-xl-4 mb-3">
                        <div class="border rounded h-100 d-flex flex-column p-3">
                            <div class="d-flex align-items-start mb-3">
                                <div class="avatar-sm me-3 flex-shrink-0">
                                    <span class="avatar-title rounded-circle bg-{{ $tone }}-subtle text-{{ $tone }}">{{ $initials }}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">{{ $doctor->displayName() }}</h5>
                                    <div class="text-muted small">{{ $doctor->user?->email ?: 'Tanpa email' }}</div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <span class="badge badge-soft-primary">{{ $doctor->roleLabel() }}</span>
                                <span class="badge {{ $doctor->is_active ? 'bg-success' : 'bg-danger' }}">
                                    {{ $doctor->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>

                            <ul class="list-unstyled text-muted mb-3 flex-grow-1">
                                <li class="mb-1"><i class="bx bx-briefcase me-1"></i>{{ $doctor->specialization ?: 'Belum ada spesialisasi' }}</li>
                                <li class="mb-1"><i class="bx bx-id-card me-1"></i>SIP {{ $doctor->sip ?: '-' }}</li>
                                <li class="mb-1"><i class="bx bx-buildings me-1"></i>{{ $branchNames->implode(', ') ?: 'Belum ada cabang' }}</li>
                                <li>
                                    <i class="bx bx-time-five me-1"></i>{{ $today->label() }}:
                                    @if ($doctor->schedules->isEmpty())
                                        tidak ada jadwal
                                    @else
                                        {{ $doctor->schedules->map(fn ($schedule) => $schedule->timeRange().($schedule->room?->name ? ' · '.$schedule->room->name : ''))->implode(', ') }}
                                    @endif
                                </li>
                            </ul>

                            @include('doctors.partials.actions', ['doctor' => $doctor])
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($doctors->hasPages())
                <div class="d-flex justify-content-center mt-2">
                    {{ $doctors->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </x-card>

    <x-modal id="doctor-view-modal" title="Detail tenaga medis" size="modal-lg">
        <table class="table table-bordered align-middle mb-0">
            <tbody>
                <tr><th width="160">Nama</th><td id="doctor-view-name">-</td></tr>
                <tr><th>Email</th><td id="doctor-view-email">-</td></tr>
                <tr><th>Peran</th><td id="doctor-view-role">-</td></tr>
                <tr><th>Spesialisasi</th><td id="doctor-view-specialization">-</td></tr>
                <tr><th>SIP</th><td id="doctor-view-sip">-</td></tr>
                <tr><th>STR</th><td id="doctor-view-str">-</td></tr>
                <tr><th>NIK</th><td id="doctor-view-nik">-</td></tr>
                <tr><th>Telepon</th><td id="doctor-view-phone">-</td></tr>
                <tr><th>Cabang</th><td id="doctor-view-branch">-</td></tr>
                <tr>
                    <th>Status</th>
                    <td><span id="doctor-view-status" class="badge bg-secondary">-</span></td>
                </tr>
                <tr id="doctor-view-notes-row" class="d-none"><th>Catatan</th><td id="doctor-view-notes">-</td></tr>
            </tbody>
        </table>
        <div id="doctor-view-signature-wrap" class="mt-3 d-none">
            <div class="text-muted small mb-1">Tanda tangan</div>
            <img id="doctor-view-signature" alt="Tanda tangan" style="height:64px">
        </div>
        <x-slot:footer>
            <a id="doctor-view-account" href="#" class="btn btn-secondary waves-effect waves-light d-none">
                <i class="bx bx-user me-1"></i> Akun
            </a>
            <x-button type="button" variant="secondary" data-bs-dismiss="modal">Tutup</x-button>
        </x-slot:footer>
    </x-modal>
@endsection

@push('scripts')
    <script>
        (function () {
            const viewModal = document.getElementById('doctor-view-modal');
            if (!viewModal) {
                return;
            }

            const textFields = ['name', 'email', 'specialization', 'sip', 'str', 'nik', 'phone', 'branch'];

            viewModal.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                if (!trigger || !trigger.dataset.doctor) {
                    return;
                }

                const data = JSON.parse(trigger.dataset.doctor);

                textFields.forEach(function (key) {
                    const cell = document.getElementById('doctor-view-' + key);
                    if (cell) {
                        cell.textContent = data[key] || '-';
                    }
                });

                const role = document.getElementById('doctor-view-role');
                if (role) {
                    role.textContent = data.role || '-';
                }

                const status = document.getElementById('doctor-view-status');
                if (status) {
                    status.textContent = data.is_active ? 'Aktif' : 'Nonaktif';
                    status.className = data.is_active ? 'badge bg-success' : 'badge bg-danger';
                }

                const notesRow = document.getElementById('doctor-view-notes-row');
                const notes = document.getElementById('doctor-view-notes');
                if (notesRow && notes) {
                    if (data.notes) {
                        notes.textContent = data.notes;
                        notesRow.classList.remove('d-none');
                    } else {
                        notes.textContent = '';
                        notesRow.classList.add('d-none');
                    }
                }

                const signatureWrap = document.getElementById('doctor-view-signature-wrap');
                const signature = document.getElementById('doctor-view-signature');
                if (signatureWrap && signature) {
                    if (data.signature) {
                        signature.src = data.signature;
                        signatureWrap.classList.remove('d-none');
                    } else {
                        signature.removeAttribute('src');
                        signatureWrap.classList.add('d-none');
                    }
                }

                const account = document.getElementById('doctor-view-account');
                if (account) {
                    if (data.account_url) {
                        account.href = data.account_url;
                        account.classList.remove('d-none');
                    } else {
                        account.href = '#';
                        account.classList.add('d-none');
                    }
                }

                const title = document.getElementById('doctor-view-modalLabel');
                if (title) {
                    title.textContent = data.name || 'Detail tenaga medis';
                }
            });
        })();
    </script>
@endpush
