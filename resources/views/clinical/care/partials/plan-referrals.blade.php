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
                    @can('referral.manage')<th></th>@endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($visit->referrals as $referral)
                    <tr>
                        <td>{{ $referral->referred_to }}</td>
                        <td>{{ $referral->reason }}</td>
                        <td>{{ $referral->notes ?: '-' }}</td>
                        @can('referral.manage')
                            <td class="text-end">
                                @if ($writable)
                                    <form action="{{ route('care.referral.destroy', [$visit, $referral]) }}" method="POST" onsubmit="return confirm('Hapus rujukan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-soft-danger" type="submit">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        @endcan
                    </tr>
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
