@php $supplier = $supplier ?? null; @endphp
@extends('layouts.app')

@section('title', $supplier ? 'Ubah Supplier' : 'Tambah Supplier')

@section('content')
    <x-page-header
        :title="$supplier ? 'Ubah Supplier' : 'Tambah Supplier'"
        :breadcrumb="['Supplier' => route('pharmacy.purchases.suppliers'), $supplier ? 'Ubah' : 'Tambah' => null]"
    />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card :title="$supplier ? 'Ubah Supplier' : 'Supplier Baru'">
                <form
                    method="POST"
                    action="{{ $supplier ? route('pharmacy.suppliers.update', $supplier) : route('pharmacy.suppliers.store') }}"
                >
                    @csrf
                    @if ($supplier)
                        @method('PUT')
                    @endif
                    <x-input name="name" label="Nama" :value="$supplier?->name" required />
                    <x-input name="contact" label="Kontak" :value="$supplier?->contact" />
                    <x-input name="address" label="Alamat" :value="$supplier?->address" />
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                        <x-button href="{{ route('pharmacy.purchases.suppliers') }}" variant="secondary">Batal</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
