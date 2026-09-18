<div class="row g-2 align-items-end mb-3">
    <div class="col-md-3">
        <label class="form-label" for="date_from">Dari</label>
        <input id="date_from" type="date" name="date_from" value="{{ $from ?? request('date_from') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="date_to">Sampai</label>
        <input id="date_to" type="date" name="date_to" value="{{ $to ?? request('date_to') }}" class="form-control">
    </div>
    @if (isset($branches) && $branches->isNotEmpty())
        <div class="col-md-3">
            <label class="form-label" for="branch_id">Cabang</label>
            <select id="branch_id" name="branch_id" class="form-select">
                <option value="">Semua cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) ($branchId ?? request('branch_id')) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-md-3">
        <x-button type="submit" icon="bx bx-filter-alt">Filter</x-button>
        @if (! empty($showExport))
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-light ms-1">CSV</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-light">PDF</a>
        @endif
    </div>
</div>
