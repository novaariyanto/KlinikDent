@extends('layouts.app')

@section('title', 'Integrasi Platform')

@section('content')
    <x-page-header title="Integrasi" :breadcrumb="['System' => null, 'Integrasi' => route('saas.system.integrations')]" />
    <x-alert />

    <div class="row">
        <div class="col-lg-6">
            <x-card title="URL default platform">
                <p class="text-muted small">Kredensial SATUSEHAT dan BPJS milik masing-masing klinik. Nilai di bawah hanya URL cadangan jika owner belum mengisi URL sendiri.</p>
                <p class="mb-1">SATUSEHAT: <code>{{ $satusehatBaseUrl }}</code></p>
                <p class="mb-0">BPJS: <code>{{ $bpjsBaseUrl }}</code></p>
            </x-card>
        </div>
    </div>

    <x-card title="Modul per klinik">
        <x-table>
            <thead>
                <tr>
                    <th>Klinik</th>
                    <th>SATUSEHAT</th>
                    <th>BPJS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $row)
                    <tr>
                        <td>{{ $row['tenant']->name }}</td>
                        <td>
                            {{ $row['satusehat']->statusLabel() }}
                            @if (! $row['satusehat']->exists)
                                <span class="text-muted small">(belum disimpan owner)</span>
                            @endif
                        </td>
                        <td>
                            {{ $row['bpjs']->statusLabel() }}
                            @if (! $row['bpjs']->exists)
                                <span class="text-muted small">(belum disimpan owner)</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Belum ada klinik.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <x-card title="Log integrasi">
        @include('saas.partials.integration-logs', ['logs' => $logs, 'showTenant' => true])
        {{ $logs->links() }}
    </x-card>
@endsection
