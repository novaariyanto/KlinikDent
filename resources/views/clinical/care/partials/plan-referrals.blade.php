@php($hasReferral = $visit->referrals->isNotEmpty() || old('referred_to'))
<section class="care-section" id="rujukan">
    <div class="care-section__head">
        <h5 class="care-section__title">Rujukan</h5>
        <p class="care-section__hint">Default tidak ada. Buka form hanya jika pasien dirujuk.</p>
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
                <div class="exam-field mt-3" data-reveal-group="referral_need" data-reveal-when="ada">
                    <div class="exam-field__label">Perlu rujukan?</div>
                    <div class="exam-choices">
                        <label class="exam-choice">
                            <input type="radio" name="referral_need" value="tidak" @checked(! $hasReferral)>
                            <span>Tidak ada</span>
                        </label>
                        <label class="exam-choice">
                            <input type="radio" name="referral_need" value="ada" @checked($hasReferral)>
                            <span>Ada</span>
                        </label>
                    </div>
                </div>
                <form action="{{ route('care.referral.store', $visit) }}" method="POST" class="mt-3" data-reveal="referral_need" @if (! $hasReferral) hidden @endif>
                    @csrf
                    <x-input name="referred_to" label="Dirujuk ke" :value="old('referred_to')" required />
                    <x-input name="reason" label="Alasan" :value="old('reason')" required />
                    <div class="mb-3">
                        <label class="form-label" for="referral_notes">Catatan</label>
                        <textarea name="notes" id="referral_notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                    <x-button type="submit" icon="bx bx-plus">Tambah Rujukan</x-button>
                </form>
            @endif
        @endcan
    </div>
</section>
