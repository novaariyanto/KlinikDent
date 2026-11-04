<div class="d-flex flex-wrap justify-content-end gap-1">
    <a class="btn btn-sm btn-soft-primary" href="{{ route('medical-letters.show', $letter) }}">Detail</a>
    <a class="btn btn-sm btn-light" href="{{ route('medical-letters.show', $letter) }}" target="_blank">Pratinjau</a>
    <a class="btn btn-sm btn-light" href="{{ route('medical-letters.show', [$letter, 'cetak' => 1]) }}" target="_blank">Cetak</a>
    <a class="btn btn-sm btn-light" href="{{ route('medical-letters.pdf', $letter) }}" target="_blank">PDF</a>
    <a class="btn btn-sm btn-light" href="{{ route('medical-letters.pdf', [$letter, 'download' => 1]) }}">Unduh</a>
</div>
