<div class="mb-4">
    <h3 class="mb-1">Tambah user / staf</h3>
    <p class="text-muted">Satu orang dapat memiliki beberapa role. Jangan membuat akun terpisah jika orangnya sama.</p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'staff') }}">
    @csrf
    <div id="staff-list">
        @php $rows = old('staff', [['name' => '', 'roles' => []]]); @endphp
        @foreach ($rows as $index => $row)
            @include('onboarding.partials.staff-row', ['index' => $index, 'row' => $row])
        @endforeach
    </div>
    <button type="button" class="btn btn-outline-primary mb-3" id="add-staff">+ Tambah Staf</button>
    @include('onboarding.partials.actions', ['canSkip' => true, 'step' => 'staff'])
</form>

<template id="staff-template">
    @include('onboarding.partials.staff-row', ['index' => '__i__', 'row' => ['name' => '', 'roles' => []]])
</template>

@push('scripts')
<script>
document.getElementById('add-staff')?.addEventListener('click', () => {
    const list = document.getElementById('staff-list');
    const tpl = document.getElementById('staff-template').innerHTML.replaceAll('__i__', String(list.children.length));
    list.insertAdjacentHTML('beforeend', tpl);
});
</script>
@endpush
