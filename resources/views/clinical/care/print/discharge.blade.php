@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Resume Pulang</div>
    <p class="doc-number">No. registrasi {{ $registrationNumber }} · {{ $registrationDate }}</p>
    @include('clinical.care.print.partials.identity')

    <table class="soap">
        @foreach ($groups as $group => $items)
            @foreach ($items as $label => $value)
                <tr>
                    @if ($loop->first)
                        <th class="group" rowspan="{{ count($items) }}">{{ $group }}</th>
                    @endif
                    <th class="label">{{ $label }}</th>
                    <td>
                        @if ($value === 'vitals')
                            @if ($vitalItems === [])
                                —
                            @else
                                @foreach ($vitalItems as $vitalLabel => $vitalValue)
                                    <div>{{ $vitalLabel }}: {{ $vitalValue }}</div>
                                @endforeach
                            @endif
                        @elseif ($value === 'mode')
                            {{ $dischargeMode }}
                        @elseif ($value === [])
                            —
                        @elseif (count($value) === 1)
                            {{ $value[0] }}
                        @else
                            @foreach ($value as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        @endif
                    </td>
                </tr>
            @endforeach
        @endforeach
    </table>
@endsection
