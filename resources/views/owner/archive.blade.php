@extends('layouts.app')

@section('title', 'Arsip Praktik')

@section('content')
    <x-page-header title="Arsip Praktik" :breadcrumb="['Pengaturan' => route('settings.clinic'), 'Arsip' => null]" />
    <x-alert />
    @include('settings.partials.nav')
    <x-card title="Unduh seluruh data praktik">
        <p>Berkas ZIP berisi pasien, kunjungan, rekam medis, odontogram, resep, tagihan, stok, dan pesanan lab. Tautan kedaluwarsa dalam 24 jam.</p>
        <form method="POST" action="{{ route('archive.store') }}">
            @csrf
            <x-button type="submit">Buat arsip</x-button>
        </form>
        <x-table class="mt-3">
            <thead><tr><th>Waktu</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($archives as $archive)
                    <tr>
                        <td>{{ $archive->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $archive->status }} @if ($archive->error) — {{ $archive->error }} @endif</td>
                        <td>
                            @if ($archive->isReady())
                                <a href="{{ route('archive.download', $archive) }}">Unduh</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Belum ada arsip.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
