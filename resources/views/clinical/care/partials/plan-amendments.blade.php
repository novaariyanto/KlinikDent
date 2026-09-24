<section class="care-section" id="adendum">
    <div class="care-section__head">
        <h5 class="care-section__title">Adendum</h5>
        <p class="care-section__hint">Setelah final, koreksi isi hanya 2×24 jam. Lewat itu, perubahan lewat adendum.</p>
    </div>
    <div class="care-section__body">
        @if ($recordLocked)
            <div class="alert alert-warning">Batas koreksi 2×24 jam telah lewat. Isi rekam medis terkunci.</div>
        @elseif ($recordFinal)
            <p>Rekam medis final. Koreksi isi masih dibuka sampai {{ $correctUntil?->format('d M Y H:i') }}.</p>
        @else
            <p class="text-muted">Rekam medis belum difinalisasi. SOAP dan odontogram masih bisa diubah.</p>
        @endif

        @can('medical_record.update')
            @if ($recordLocked)
                <form method="POST" action="{{ route('care.record.amend', $visit) }}" class="mt-3">
                    @csrf
                    <label class="form-label" for="amendment-body">Adendum</label>
                    <textarea class="form-control mb-2" name="body" id="amendment-body" rows="3" required></textarea>
                    <x-button type="submit">Simpan adendum</x-button>
                </form>
            @elseif (! $recordFinal)
                <form method="POST" action="{{ route('care.record.finalize', $visit) }}" class="mt-3" onsubmit="return confirm('Finalisasi rekam medis? Koreksi isi hanya 2×24 jam setelah ini.')">
                    @csrf
                    <x-button type="submit" variant="warning">Finalisasi rekam medis</x-button>
                </form>
            @endif
        @endcan

        @foreach ($amendments ?? [] as $amendment)
            <p class="mb-1 mt-3"><strong>{{ $amendment->created_at?->format('d M Y H:i') }}</strong> — {{ $amendment->author?->name }}</p>
            <p>{{ $amendment->body }}</p>
        @endforeach
    </div>
</section>
