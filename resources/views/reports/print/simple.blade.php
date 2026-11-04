@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">{{ $title }}</div>
    <p class="doc-number">Periode {{ $from }} s.d. {{ $to }}</p>
    <table class="grid">
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($headers), 1) }}">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection

@section('signature')
@endsection
