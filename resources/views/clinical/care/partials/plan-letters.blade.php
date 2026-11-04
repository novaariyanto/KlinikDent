@php($embedded = $embedded ?? false)
<section class="care-section{{ $embedded ? ' care-section--embedded' : '' }}" id="surat">
    @unless ($embedded)
        <div class="care-section__head">
            <h5 class="care-section__title">Surat</h5>
            <p class="care-section__hint">Surat keterangan medis, resume, dan dokumen yang sudah diterbitkan.</p>
        </div>
    @endunless
    <div class="care-section__body">
        <div class="mb-3 d-flex flex-wrap gap-2">
            @can('medical_record.update')
                <x-button href="{{ route('care.letters.create', $visit) }}" variant="soft-success" icon="bx bx-plus">Buat surat</x-button>
            @endcan
            <x-button href="{{ route('care.resume.pdf', $visit) }}" variant="soft-info" icon="bx bx-file" target="_blank">Resume medis</x-button>
            @can('medical_record.view')
                <x-button href="{{ route('medical-letters.index', ['patient_id' => $visit->patient_id]) }}" variant="soft-primary" icon="bx bx-list-ul">Riwayat surat pasien</x-button>
            @endcan
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
                @forelse ($medicalLetters ?? [] as $letter)
                    <tr>
                        <td>{{ $letter->type->label() }}</td>
                        <td>{{ $letter->number }}</td>
                        <td><span class="{{ $letter->status->badgeClass() }}">{{ $letter->status->label() }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('medical-letters.show', $letter) }}" target="_blank">Detail</a>
                            <a href="{{ route('medical-letters.pdf', $letter) }}" target="_blank">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Belum ada surat keterangan.</td></tr>
                @endforelse
                @foreach ($clinicalDocuments ?? [] as $document)
                    <tr>
                        <td>{{ $document->type->label() }}</td>
                        <td>{{ $document->number }}</td>
                        <td>{{ $document->status->label() }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('care.documents.pdf', [$visit, $document]) }}" target="_blank">PDF</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </div>
</section>
