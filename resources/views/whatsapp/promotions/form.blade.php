@extends('layouts.app')

@section('title', $promotion->exists ? 'Edit Promo' : 'Promo Baru')

@section('content')
    <x-page-header :title="$promotion->exists ? 'Edit Promo' : 'Promo Baru'" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Promosi' => route('whatsapp.promotions.index'), 'Form' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    <x-card>
        <form method="POST" action="{{ $promotion->exists ? route('whatsapp.promotions.update', $promotion) : route('whatsapp.promotions.store') }}">
            @csrf
            @if ($promotion->exists)
                @method('PUT')
            @endif

            <x-input name="title" label="Judul Promo" :value="old('title', $promotion->title)" required />
            <div class="mb-3">
                <label for="message" class="form-label">Isi Promo</label>
                <textarea name="message" id="message" rows="5" class="form-control" required>{{ old('message', $promotion->message) }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <x-input name="starts_at" type="date" label="Tanggal Mulai" :value="old('starts_at', optional($promotion->starts_at)->format('Y-m-d'))" required />
                </div>
                <div class="col-md-4">
                    <x-input name="ends_at" type="date" label="Tanggal Berakhir" :value="old('ends_at', optional($promotion->ends_at)->format('Y-m-d'))" required />
                </div>
                <div class="col-md-4">
                    <x-select
                        name="branch_id"
                        label="Cabang"
                        :options="collect($branches)->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all()"
                        :selected="old('branch_id', $promotion->branch_id)"
                        placeholder="Semua pasien eligible"
                    />
                </div>
            </div>
            <x-input name="whatsapp_template_name" label="Nama Template WhatsApp" :value="old('whatsapp_template_name', $promotion->whatsapp_template_name ?: 'promotion_general')" />
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="is_enabled" @checked(old('is_enabled', $promotion->is_enabled))>
                <label class="form-check-label" for="is_enabled">Aktif</label>
            </div>
            <x-button type="submit" icon="bx bx-save">Simpan</x-button>
        </form>
    </x-card>
@endsection
