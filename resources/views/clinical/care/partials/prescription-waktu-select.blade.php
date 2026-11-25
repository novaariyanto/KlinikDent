@php
    $value = trim((string) ($value ?? ''));
    $name = $name ?? 'waktu';
    $legacy = [
        'AC' => 'Sebelum Makan',
        'PC' => 'Sesudah Makan',
        'saat makan' => 'Bersama Makan',
        'bersama makan' => 'Bersama Makan',
        'sebelum makan' => 'Sebelum Makan',
        'sesudah makan' => 'Sesudah Makan',
    ];
    $current = $legacy[$value] ?? $value;
    $options = ['Sebelum Makan', 'Sesudah Makan', 'Bersama Makan'];
@endphp
<select class="form-select form-select-sm" name="{{ $name }}" data-rx-field autocomplete="off" aria-label="Waktu konsumsi">
    <option value="">—</option>
    @foreach ($options as $option)
        <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
    @endforeach
    @if ($current !== '' && ! in_array($current, $options, true))
        <option value="{{ $current }}" selected>{{ $current }}</option>
    @endif
</select>
