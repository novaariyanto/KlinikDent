<section class="care-section" id="surat">
    <div class="care-section__head">
        <h5 class="care-section__title">Surat</h5>
        <p class="care-section__hint">Surat sakit, resume, dan dokumen yang bisa dicek tanpa akun.</p>
    </div>
    <div class="care-section__body">
        <div class="mb-3">
            <x-button href="{{ route('care.resume.pdf', $visit) }}" variant="light" icon="bx bx-file" target="_blank">Resume medis</x-button>
        </div>

        <x-table>
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>Nomor</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clinicalDocuments ?? [] as $document)
                    <tr>
                        <td>{{ $document->type->label() }}</td>
                        <td>{{ $document->number }}</td>
                        <td>{{ $document->status->label() }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('care.documents.pdf', [$visit, $document]) }}" target="_blank">PDF</a>
                            @if ($document->isValid())
                                <form class="d-inline" method="POST" action="{{ route('care.documents.cancel', [$visit, $document]) }}">
                                    @csrf
                                    <button class="btn btn-link btn-sm" type="submit">Batalkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Belum ada surat.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        @can('medical_record.view')
            <form method="POST" action="{{ route('care.sick-letter.store', $visit) }}" class="mt-3">
                @csrf
                <h6>Surat sakit</h6>
                <div class="row">
                    <div class="col-md-4"><x-input name="rest_from" type="date" label="Mulai" :value="now()->toDateString()" required /></div>
                    <div class="col-md-4"><x-input name="rest_days" type="number" label="Hari" value="1" required /></div>
                    <div class="col-md-4"><x-input name="notes" label="Catatan" /></div>
                </div>
                <x-button type="submit" variant="light" icon="bx bx-printer">Cetak surat sakit</x-button>
            </form>
        @endcan
    </div>
</section>
