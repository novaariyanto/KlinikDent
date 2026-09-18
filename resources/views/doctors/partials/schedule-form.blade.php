@php
    $schedule = $schedule ?? null;
    $weekdaySelected = old('weekday', $schedule?->weekday?->value ?? ($selectedWeekday ?? null));
    $branchOptions = collect($branches ?? [])->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all();
    $start = old('start_time', $schedule?->start_time ? substr((string) $schedule->start_time, 0, 5) : '08:00');
    $end = old('end_time', $schedule?->end_time ? substr((string) $schedule->end_time, 0, 5) : '16:00');
@endphp

<div class="row">
    <div class="col-md-6">
        <x-select name="weekday" label="Hari" :options="\App\Enums\Weekday::options()" :selected="$weekdaySelected" required />
    </div>
    <div class="col-md-6">
        <x-select name="branch_id" label="Cabang" :options="$branchOptions" :selected="old('branch_id', $schedule?->branch_id)" required />
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="room_id" class="form-label">Poli / Ruangan</label>
            <select name="room_id" id="room_id" class="form-select @error('room_id') is-invalid @enderror">
                <option value="">Pilih poli di cabang ini</option>
                @foreach ($rooms ?? [] as $room)
                    <option
                        value="{{ $room->id }}"
                        data-branch="{{ $room->branch_id }}"
                        @selected((string) old('room_id', $schedule?->room_id) === (string) $room->id)
                    >
                        {{ $room->name }}{{ $room->type?->label() ? ' — '.$room->type->label() : '' }}
                    </option>
                @endforeach
            </select>
            @error('room_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <x-input name="start_time" type="time" label="Mulai" :value="$start" required />
    </div>
    <div class="col-md-3">
        <x-input name="end_time" type="time" label="Selesai" :value="$end" required />
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="schedule_is_active" value="1" @checked(old('is_active', $schedule?->is_active ?? true))>
            <label class="form-check-label" for="schedule_is_active">Aktif</label>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const branchSelect = document.getElementById('branch_id');
        const roomSelect = document.getElementById('room_id');
        if (!branchSelect || !roomSelect) return;

        function filterRooms() {
            const branchId = branchSelect.value;
            let keep = false;
            roomSelect.querySelectorAll('option[data-branch]').forEach(function (opt) {
                const show = !branchId || opt.getAttribute('data-branch') === branchId;
                opt.hidden = !show;
                opt.disabled = !show;
                if (show && opt.value === roomSelect.value) keep = true;
            });
            if (!keep) roomSelect.value = '';
        }

        branchSelect.addEventListener('change', filterRooms);
        filterRooms();
    })();
</script>
@endpush
