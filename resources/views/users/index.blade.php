@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-page-header title="Users" :breadcrumb="['Management' => null, 'Users' => route('users.index')]" />

    <x-alert />

    <x-card title="User List">
        <x-slot:header-actions>
            @can('users.create')
                <x-button href="{{ route('users.create') }}" icon="bx bx-plus">Add User</x-button>
            @endcan
        </x-slot:header-actions>

        <div class="d-flex align-items-center gap-2 mb-3">
            <label class="form-label mb-0" for="users-status-filter">Tampilkan</label>
            <select id="users-status-filter" class="form-select form-select-sm" style="max-width: 16rem">
                <option value="active">User aktif</option>
                <option value="inactive">User nonaktif</option>
            </select>
        </div>

        <x-data-table
            id="users-table"
            :ajax="route('users.data')"
            :columns="$columns"
            :order="[[auth()->user()?->isPlatformAdmin() ? 7 : 5, 'desc']]"
            :auto-init="false"
        />
    </x-card>
@endsection

@push('scripts')
<script>
(function () {
    function boot() {
        var table = document.getElementById('users-table');
        var filter = document.getElementById('users-status-filter');
        if (!table || !filter || typeof window.initServerDataTable !== 'function') {
            setTimeout(boot, 50);
            return;
        }

        var dt = window.initServerDataTable(table, {
            ajax: {
                url: table.dataset.ajax,
                data: function (payload) {
                    payload.status = filter.value || 'active';
                }
            }
        });

        filter.addEventListener('change', function () {
            if (dt && dt.ajax) dt.ajax.reload();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endpush
