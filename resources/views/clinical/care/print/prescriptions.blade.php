@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Resep Obat</div>
    <p class="doc-number">Nomor: {{ $letterNumber }} · {{ $prescriptions->count() }} resep</p>
    @include('clinical.care.print.partials.identity')

    @foreach ($prescriptions as $prescription)
        <table class="section-head" style="margin-top:{{ $loop->first ? '0' : '10px' }}">
            <tr>
                <td>Resep #{{ $prescription->id }} · {{ $prescription->status?->label() ?? '-' }} · {{ $prescription->doctor?->name ?: ($visit->doctor?->name ?: '-') }}</td>
            </tr>
        </table>
        <table class="items">
            <thead>
                <tr>
                    <th style="width:18px">No</th>
                    <th>Obat</th>
                    <th>Dosis</th>
                    <th>Frekuensi</th>
                    <th>Aturan pakai</th>
                    <th class="num">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prescription->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $item->label() }}
                            @if ($item->isCompound() && $item->ingredients->isNotEmpty())
                                <br><span style="font-size:8.5px">
                                    {{ $item->ingredients->map(fn ($i) => trim($i->name.' '.($i->strength_value ? $i->strength_value.' '.$i->strength_unit : '').' × '.$i->quantity.' '.$i->unit))->implode('; ') }}
                                </span>
                            @endif
                        </td>
                        <td>{{ $item->dosage ?: '-' }}</td>
                        <td>{{ $item->frequency ?: '-' }}</td>
                        <td>
                            {{ $item->duration ?: '-' }}
                            @if ($item->course)
                                <br><span style="font-size:8.5px">Durasi {{ $item->course }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $item->quantity ?? '-' }}{{ $item->isCompound() ? ' bks' : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endsection
