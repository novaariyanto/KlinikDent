<section class="care-section" id="diagnosis">
    <div class="care-section__head d-flex justify-content-between align-items-start gap-2">
        <div>
            <h5 class="care-section__title">Diagnosis</h5>
            <p class="care-section__hint">Cari kode atau nama ICD-10, lalu tambahkan. Gigi opsional dari temuan odontogram.</p>
        </div>
        @can('diagnosis.manage')
            @if ($writable)
                <x-button type="button" data-bs-toggle="modal" data-bs-target="#diagnosis-create-modal" icon="bx bx-plus">Tambah</x-button>
            @endif
        @endcan
    </div>
    <div class="care-section__body">
        <x-table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Diagnosa</th>
                    <th>Gigi</th>
                    @can('diagnosis.manage')<th></th>@endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($visit->diagnoses as $diagnosis)
                    <tr>
                        <td>{{ $diagnosis->code }}</td>
                        <td>{{ $diagnosis->description }}</td>
                        <td>{{ $diagnosis->tooth_number ?: '-' }}</td>
                        @can('diagnosis.manage')
                            <td class="text-end text-nowrap">
                                @if ($writable)
                                    <button type="button" class="btn btn-sm btn-soft-primary" data-toggle-panel="#dx-edit-{{ $diagnosis->id }}">Ubah</button>
                                    <form action="{{ route('care.diagnosis.destroy', [$visit, $diagnosis]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus diagnosis ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-soft-danger" type="submit">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        @endcan
                    </tr>
                    @can('diagnosis.manage')
                        @if ($writable)
                            <tr id="dx-edit-{{ $diagnosis->id }}" hidden>
                                <td colspan="4">
                                    <form action="{{ route('care.diagnosis.update', [$visit, $diagnosis]) }}" method="POST" class="care-inline-form" data-icd-form>
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="code" value="{{ $diagnosis->code }}">
                                        <input type="hidden" name="description" value="{{ $diagnosis->description }}">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label for="icd-select-{{ $diagnosis->id }}" class="form-label">ICD-10 <span class="text-danger">*</span></label>
                                                    <select
                                                        id="icd-select-{{ $diagnosis->id }}"
                                                        class="form-select"
                                                        data-icd-select
                                                        data-icd-url="{{ route('care.diagnosis.search', $visit) }}"
                                                    >
                                                        <option value=""></option>
                                                        <option value="{{ $diagnosis->code }}" selected>{{ $diagnosis->code }} — {{ $diagnosis->description }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                @include('clinical.care.partials.tooth-select', [
                                                    'id' => 'diagnosis-tooth-'.$diagnosis->id,
                                                    'selected' => $diagnosis->tooth_number,
                                                ])
                                            </div>
                                        </div>
                                        <x-button type="submit" icon="bx bx-save">Simpan diagnosis</x-button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endcan
                @empty
                    <tr><td colspan="4" class="text-muted">Belum ada diagnosis.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        @can('diagnosis.manage')
            @if ($writable)
                @php($reopenDiagnosis = old('_form') === 'diagnosis-create')
                <x-modal id="diagnosis-create-modal" title="Tambah diagnosis" size="modal-lg" class="care-modal" :data-show-on-load="$reopenDiagnosis ? '' : null">
                    <form action="{{ route('care.diagnosis.store', $visit) }}" method="POST" data-icd-form>
                        @csrf
                        <input type="hidden" name="_form" value="diagnosis-create">
                        <input type="hidden" name="code" value="{{ $reopenDiagnosis ? old('code') : '' }}">
                        <input type="hidden" name="description" value="{{ $reopenDiagnosis ? old('description') : '' }}">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="icd-select-create" class="form-label">ICD-10 <span class="text-danger">*</span></label>
                                    <select
                                        id="icd-select-create"
                                        class="form-select"
                                        data-icd-select
                                        data-icd-url="{{ route('care.diagnosis.search', $visit) }}"
                                    >
                                        <option value=""></option>
                                        @if ($reopenDiagnosis && old('code'))
                                            <option value="{{ old('code') }}" selected>{{ old('code') }} — {{ old('description') }}</option>
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                @include('clinical.care.partials.tooth-select', [
                                    'id' => 'diagnosis-tooth-create',
                                    'selected' => $reopenDiagnosis ? old('tooth_number') : '',
                                ])
                            </div>
                        </div>
                        <div class="text-end">
                            <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                        </div>
                    </form>
                </x-modal>
            @endif
        @endcan
    </div>
</section>
