<x-card title="Tanda Vital">
    @php($vitals = old() ?: ($record?->vital_signs ?? []))
    @can('vital_sign.manage')
        @if ($writable)
            <form action="{{ route('care.vitals.update', $visit) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6">
                        <x-input name="blood_pressure" label="Tekanan Darah" :value="$vitals['blood_pressure'] ?? null" placeholder="120/80" />
                    </div>
                    <div class="col-md-6">
                        <x-input name="pulse" label="Nadi" :value="$vitals['pulse'] ?? null" />
                    </div>
                    <div class="col-md-6">
                        <x-input name="temperature" label="Suhu (°C)" :value="$vitals['temperature'] ?? null" />
                    </div>
                    <div class="col-md-6">
                        <x-input name="respiration" label="Pernapasan" :value="$vitals['respiration'] ?? null" />
                    </div>
                    <div class="col-md-6">
                        <x-input name="weight" label="Berat (kg)" :value="$vitals['weight'] ?? null" />
                    </div>
                    <div class="col-md-6">
                        <x-input name="height" label="Tinggi (cm)" :value="$vitals['height'] ?? null" />
                    </div>
                </div>
                <x-button type="submit" icon="bx bx-save">Simpan</x-button>
            </form>
        @endif
    @else
        <p class="mb-0">
            TD {{ $vitals['blood_pressure'] ?? '-' }},
            Nadi {{ $vitals['pulse'] ?? '-' }},
            Suhu {{ $vitals['temperature'] ?? '-' }},
            RR {{ $vitals['respiration'] ?? '-' }}
        </p>
    @endcan
</x-card>
