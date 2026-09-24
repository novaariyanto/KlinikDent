<section class="care-section" id="rujukan">
    <div class="care-section__head d-flex justify-content-between align-items-start gap-2">
        <div>
            <h5 class="care-section__title">Rujukan</h5>
            <p class="care-section__hint">Catat hanya jika pasien dirujuk.</p>
        </div>
        @can('referral.manage')
            @if ($writable)
                <x-button type="button" data-bs-toggle="modal" data-bs-target="#referral-create-modal" icon="bx bx-plus">Tambah</x-button>
            @endif
        @endcan
    </div>
    <div class="care-section__body">
        <x-table>
            <thead>
                <tr>
                    <th>Tujuan</th>
                    <th>Alasan</th>
                    <th>Catatan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($visit->referrals as $referral)
                    <tr>
                        <td>{{ $referral->referred_to }}</td>
                        <td>{{ $referral->reason }}</td>
                        <td>{{ $referral->notes ?: '-' }}</td>
                        <td class="text-end text-nowrap">
                            @can('referral.view')
                                <a href="{{ route('care.referral.pdf', [$visit, $referral]) }}" class="btn btn-sm btn-soft-secondary" target="_blank">
                                    <i class="bx bx-printer me-1"></i>Cetak PDF
                                </a>
                            @endcan
                            @can('referral.manage')
                                @if ($writable)
                                    <button type="button" class="btn btn-sm btn-soft-primary" data-toggle-panel="#referral-edit-{{ $referral->id }}">Ubah</button>
                                    <form action="{{ route('care.referral.destroy', [$visit, $referral]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus rujukan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-soft-danger" type="submit">Hapus</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                    @can('referral.manage')
                        @if ($writable)
                            <tr id="referral-edit-{{ $referral->id }}" hidden>
                                <td colspan="4">
                                    <form action="{{ route('care.referral.update', [$visit, $referral]) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <x-input name="referred_to" :id="'referred_to_'.$referral->id" label="Dirujuk ke" :value="old('referred_to', $referral->referred_to)" required />
                                        <x-input name="reason" :id="'reason_'.$referral->id" label="Alasan" :value="old('reason', $referral->reason)" required />
                                        <div class="mb-3">
                                            <label class="form-label" for="referral_notes_{{ $referral->id }}">Catatan</label>
                                            <textarea name="notes" id="referral_notes_{{ $referral->id }}" rows="3" class="form-control">{{ old('notes', $referral->notes) }}</textarea>
                                        </div>
                                        <x-button type="submit" icon="bx bx-save">Simpan rujukan</x-button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endcan
                @empty
                    <tr><td colspan="4" class="text-muted">Tidak ada rujukan.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        @can('referral.manage')
            @if ($writable)
                @php($reopenReferral = old('_form') === 'referral-create')
                <x-modal id="referral-create-modal" title="Tambah rujukan" size="modal-lg" class="care-modal" :data-show-on-load="$reopenReferral ? '' : null">
                    <form action="{{ route('care.referral.store', $visit) }}" method="POST">
                        @csrf
                        <input type="hidden" name="_form" value="referral-create">
                        <x-input name="referred_to" id="referral-create-to" label="Dirujuk ke" :value="$reopenReferral ? old('referred_to') : ''" required />
                        <x-input name="reason" id="referral-create-reason" label="Alasan" :value="$reopenReferral ? old('reason') : ''" required />
                        <div class="mb-3">
                            <label class="form-label" for="referral-create-notes">Catatan</label>
                            <textarea name="notes" id="referral-create-notes" rows="3" class="form-control">{{ $reopenReferral ? old('notes') : '' }}</textarea>
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
