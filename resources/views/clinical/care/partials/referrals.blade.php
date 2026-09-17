<x-card title="Rujukan">
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
                <tr><td colspan="4" class="text-muted">Belum ada rujukan.</td></tr>
            @endforelse
        </tbody>
    </x-table>

    @can('referral.manage')
        @if ($writable)
            <form action="{{ route('care.referral.store', $visit) }}" method="POST" class="mt-3">
                @csrf
                <x-input name="referred_to" label="Dirujuk ke" :value="old('referred_to')" required />
                <x-input name="reason" label="Alasan" :value="old('reason')" required />
                <div class="mb-3">
                    <label class="form-label" for="notes">Catatan</label>
                    <textarea name="notes" id="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
                </div>
                <x-button type="submit" icon="bx bx-plus">Tambah Rujukan</x-button>
            </form>
        @endif
    @endcan
</x-card>
