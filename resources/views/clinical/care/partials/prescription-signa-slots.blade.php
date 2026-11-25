@php
    $slots = ['signa_p' => 'Pagi', 'signa_s' => 'Siang', 'signa_sr' => 'Sore', 'signa_m' => 'Malam'];
    $values = $values ?? [];
    $options = [];
    for ($i = 0; $i <= 20; $i++) {
        $value = $i / 4;
        $options[] = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
@endphp
<div class="rx-signa">
    @foreach ($slots as $name => $label)
        @php
            $current = trim((string) ($values[$name] ?? ''));
        @endphp
        <label class="rx-signa__slot">
            <span>{{ $label }}</span>
            <select
                class="form-select form-select-sm"
                name="{{ $name }}"
                data-rx-field
                data-rx-signa
                data-signa-select
                autocomplete="off"
                aria-label="Signa {{ $label }}"
            >
                <option value="">–</option>
                @foreach ($options as $option)
                    <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
                @endforeach
                @if ($current !== '' && ! in_array($current, $options, true))
                    <option value="{{ $current }}" selected>{{ $current }}</option>
                @endif
            </select>
        </label>
    @endforeach
</div>
