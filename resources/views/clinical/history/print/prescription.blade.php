@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Resep Obat</div>
    <p class="doc-number">Nomor: {{ $letterNumber }} · Status: {{ $prescription->status?->label() ?? '-' }}</p>
    @include('clinical.care.print.partials.identity')

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
            @forelse ($prescription->items as $item)
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
                    <td class="num">{{ $item->quantity ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Tidak ada item resep.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p style="margin-top:6px">Penulis resep: {{ $prescription->doctor?->name ?: ($visit->doctor?->name ?: '-') }} · Dibuat {{ $prescription->created_at?->format('d-M-Y H:i') }}</p>
@endsection
