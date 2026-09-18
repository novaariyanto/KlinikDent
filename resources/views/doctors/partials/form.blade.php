@php
    $doctor = $doctor ?? null;
    $source = old('source', 'new');
    $userOptions = collect($availableUsers ?? [])->mapWithKeys(fn ($user) => [$user->id => $user->name.' ('.$user->email.')'])->all();
    $branchList = $branches ?? collect();
    $selectedBranches = collect(old('branch_ids', []))->map(fn ($id) => (string) $id);
@endphp

<div class="row" id="doctor-source-fields">
    <div class="col-12">
        <label class="form-label">Sumber data</label>
        <div class="d-flex flex-wrap gap-3 mb-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="source" id="source-new" value="new" @checked($source === 'new')>
                <label class="form-check-label" for="source-new">Buat akun baru</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="source" id="source-existing" value="existing" @checked($source === 'existing')>
                <label class="form-check-label" for="source-existing">Pakai pengguna yang sudah ada</label>
            </div>
        </div>
    </div>
</div>

<div data-source-panel="existing" class="row">
    <div class="col-12">
        <x-select name="user_id" label="Pengguna tenaga medis" :options="$userOptions" :selected="old('user_id')" placeholder="Pilih pengguna tanpa profil" />
        @if ($userOptions === [])
            <p class="text-muted small">Semua tenaga medis sudah punya profil, atau belum ada user dengan role dokter/asisten/perawat.</p>
        @endif
    </div>
</div>

<div data-source-panel="new" class="row">
    <div class="col-md-6">
        <x-input name="name" label="Nama" :value="null" />
    </div>
    <div class="col-md-6">
        <x-input name="email" type="email" label="Email login" :value="null" />
    </div>
    <div class="col-md-6">
        <x-input name="password" type="password" label="Password" autocomplete="new-password" />
    </div>
    <div class="col-md-6">
        <x-input name="password_confirmation" type="password" label="Konfirmasi Password" autocomplete="new-password" />
    </div>
    <div class="col-md-6">
        <x-select name="role" label="Peran" :options="$roleOptions ?? []" :selected="old('role', 'dentist')" />
    </div>
    <div class="col-12">
        <label class="form-label">Cabang</label>
        <p class="text-muted small mb-2">Cabang pertama menjadi cabang utama.</p>
        <div class="row">
            @forelse ($branchList as $branch)
                <div class="col-md-4 col-lg-3">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="branch_ids[]" id="branch-{{ $branch->id }}" value="{{ $branch->id }}" @checked($selectedBranches->contains((string) $branch->id))>
                        <label class="form-check-label" for="branch-{{ $branch->id }}">{{ $branch->name }}</label>
                    </div>
                </div>
            @empty
                <div class="col-12"><p class="text-muted">Belum ada cabang.</p></div>
            @endforelse
        </div>
        @error('branch_ids')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

@include('doctors.partials.profile-fields')

@push('scripts')
<script>
    (function () {
        const radios = document.querySelectorAll('input[name="source"]');
        const panels = document.querySelectorAll('[data-source-panel]');

        function sync() {
            const source = document.querySelector('input[name="source"]:checked')?.value || 'new';
            panels.forEach((panel) => {
                panel.classList.toggle('d-none', panel.getAttribute('data-source-panel') !== source);
            });
        }

        radios.forEach((radio) => radio.addEventListener('change', sync));
        sync();
    })();
</script>
@endpush
