@extends('layouts.app')

@section('title', 'Surat Keterangan Medis')

@section('content')
    <x-page-header title="Surat Keterangan Medis" :breadcrumb="['Pemeriksaan' => route('examinations.initial'), 'Surat Keterangan' => null]" />
    <x-alert />
    <x-card title="Daftar surat">
        <form method="GET" action="{{ route('medical-letters.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="filter-from">Dari</label>
                <input id="filter-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="filter-to">Sampai</label>
                <input id="filter-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="filter-type">Jenis</label>
                <select id="filter-type" name="type" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="filter-doctor">Dokter</label>
                <select id="filter-doctor" name="doctor_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($doctors as $doctor)
                        <option value="{{ $doctor->id }}" @selected((string) ($filters['doctor_id'] ?? '') === (string) $doctor->id)>{{ $doctor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="filter-branch">Cabang</label>
                <select id="filter-branch" name="branch_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label" for="filter-status">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if (! empty($filters['patient_id']))
                <input type="hidden" name="patient_id" value="{{ $filters['patient_id'] }}">
            @endif
            <div class="col-12">
                <x-button type="submit" icon="bx bx-filter-alt">Filter</x-button>
                <x-button href="{{ route('medical-letters.index') }}" variant="light">Reset</x-button>
            </div>
        </form>
        <x-data-table
            id="medical-letters-table"
            :ajax="route('medical-letters.data', request()->only(['date_from','date_to','type','doctor_id','branch_id','status','patient_id']))"
            :columns="$columns"
            :order="[[1, 'desc']]"
        />
    </x-card>
@endsection
