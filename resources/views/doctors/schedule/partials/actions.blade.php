@php
    $item = $schedule;
    $from = $from ?? 'schedule';
@endphp

@can('update', $item)
    <a href="{{ route('doctors.schedules.edit', $item) }}?from={{ $from }}" class="btn btn-sm btn-soft-secondary">Edit</a>
@endcan
@can('delete', $item)
    <form action="{{ route('doctors.schedules.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?')">
        @csrf
        @method('DELETE')
        <input type="hidden" name="from" value="{{ $from }}">
        <button type="submit" class="btn btn-sm btn-soft-danger">Hapus</button>
    </form>
@endcan
