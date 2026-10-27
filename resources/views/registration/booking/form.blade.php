@extends('layouts.public')

@section('title', 'Janji temu — '.$tenant->name)

@section('content')
    <div class="booking-hero">
        <div class="booking-hero__eyebrow">Janji temu online</div>
        <h1>{{ $tenant->name }}</h1>
        <p>Pilih sesi dokter, lengkapi data pasien, lalu ambil nomor antrean.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('booking.public.store', $tenant->subdomain) }}" id="booking-form" class="booking-card">
        @csrf
        <div style="position:absolute;left:-9999px" aria-hidden="true">
            <input type="text" name="company_website" tabindex="-1" autocomplete="off">
        </div>

        <div class="booking-section">
            <div class="booking-section__title">Jadwal kunjungan</div>
            <div class="booking-section__hint">Pilih cabang, tanggal, dan sesi dokter yang masih tersedia.</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="branch_id">Cabang</label>
                    <select class="form-select" name="branch_id" id="branch_id" required>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="visit_date">Tanggal</label>
                    <input class="form-control" type="date" name="visit_date" id="visit_date" value="{{ old('visit_date', now()->toDateString()) }}" min="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="doctor_schedule_id">Sesi dokter</label>
                    <select class="form-select" name="doctor_schedule_id" id="doctor_schedule_id" required>
                        <option value="">Memuat sesi…</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="booking-section">
            <div class="booking-section__title">Data pasien</div>
            <div class="booking-section__hint">Isi identitas sesuai KTP. NIK opsional; jika diisi, tanggal lahir dan jenis kelamin bisa terisi otomatis.</div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="name">Nama lengkap</label>
                    <input class="form-control" name="name" id="name" required value="{{ old('name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Telepon</label>
                    <input class="form-control" name="phone" id="phone" required value="{{ old('phone') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="nik">NIK</label>
                    <input class="form-control" name="nik" id="nik" maxlength="16" inputmode="numeric" autocomplete="off" value="{{ old('nik') }}">
                    <div id="nik-wilayah-hint" class="form-text"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="dob">Tanggal lahir</label>
                    <input class="form-control" type="date" name="dob" id="dob" value="{{ old('dob') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="gender">Jenis kelamin</label>
                    <select class="form-select" name="gender" id="gender">
                        <option value="">Pilih</option>
                        @foreach ($genders ?? [] as $value => $label)
                            <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="booking-section">
            <div class="booking-section__title">Alamat</div>
            <div class="booking-section__hint">Isi alamat seperti pada pendaftaran. Desa/kelurahan membantu kelengkapan data pasien.</div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="address">Alamat (jalan / dusun)</label>
                    <input class="form-control" name="address" id="address" value="{{ old('address') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="province_code">Provinsi</label>
                    <select class="form-select" name="province_code" id="province_code" data-placeholder="Pilih provinsi">
                        <option value="">Pilih provinsi</option>
                        @foreach ($provinces ?? [] as $code => $label)
                            <option value="{{ $code }}" @selected((string) ($selectedProvince ?? '') === (string) $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="city_code">Kabupaten / Kota</label>
                    <select class="form-select" name="city_code" id="city_code" data-placeholder="Pilih kab/kota">
                        <option value="">Pilih kab/kota</option>
                        @foreach ($cities ?? [] as $code => $label)
                            <option value="{{ $code }}" @selected((string) ($selectedCity ?? '') === (string) $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="district_code">Kecamatan</label>
                    <select class="form-select" name="district_code" id="district_code" data-placeholder="Pilih kecamatan">
                        <option value="">Pilih kecamatan</option>
                        @foreach ($districts ?? [] as $code => $label)
                            <option value="{{ $code }}" @selected((string) ($selectedDistrict ?? '') === (string) $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="village_code">Desa / Kelurahan</label>
                    <select class="form-select" name="village_code" id="village_code" data-placeholder="Pilih desa/kelurahan">
                        <option value="">Pilih desa/kelurahan</option>
                        @foreach ($villages ?? [] as $code => $label)
                            <option value="{{ $code }}" @selected((string) ($selectedVillage ?? '') === (string) $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="address_city">Kota (nama)</label>
                    <input class="form-control" name="address_city" id="address_city" value="{{ old('address_city') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="address_postal_code">Kode pos</label>
                    <input class="form-control" name="address_postal_code" id="address_postal_code" maxlength="10" value="{{ old('address_postal_code') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="rt">RT</label>
                    <input class="form-control" name="rt" id="rt" maxlength="5" value="{{ old('rt') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="rw">RW</label>
                    <input class="form-control" name="rw" id="rw" maxlength="5" value="{{ old('rw') }}">
                </div>
            </div>
        </div>

        <div class="booking-section d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">Nomor antrean muncul setelah data tersimpan.</div>
            <button class="btn btn-booking" type="submit">Ambil nomor antrean</button>
        </div>
    </form>

    <div class="modal fade" id="bookingResultModal" tabindex="-1" aria-labelledby="bookingResultTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="bookingResultTitle">Janji temu berhasil</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body text-center px-4 pb-2">
                    <div class="booking-result-badge">Nomor antrean Anda</div>
                    <div class="booking-result-number mb-3" id="bookingResultNumber">—</div>
                    <dl class="booking-result-meta mb-0">
                        <dt>Pasien</dt>
                        <dd id="bookingResultPatient">—</dd>
                        <dt>Tanggal & sesi</dt>
                        <dd id="bookingResultSchedule">—</dd>
                        <dt>Dokter</dt>
                        <dd id="bookingResultDoctor">—</dd>
                        <dt>Cabang</dt>
                        <dd id="bookingResultBranch">—</dd>
                        <dt>No. rekam medis</dt>
                        <dd id="bookingResultMrn">—</dd>
                    </dl>
                </div>
                <div class="modal-footer justify-content-center pb-4">
                    <button type="button" class="btn btn-booking" data-bs-dismiss="modal">Mengerti</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const sessionsUrl = @json(route('booking.public.sessions', $tenant->subdomain));
        const fromNikUrl = @json(route('booking.public.from-nik', $tenant->subdomain));
        const wilayahUrls = {
            cities: @json(route('booking.public.wilayah.cities', $tenant->subdomain)),
            districts: @json(route('booking.public.wilayah.districts', $tenant->subdomain)),
            villages: @json(route('booking.public.wilayah.villages', $tenant->subdomain))
        };
        const bookingResult = @json(session('booking'));
        const preferredScheduleId = @json(old('doctor_schedule_id'));

        const branch = document.getElementById('branch_id');
        const date = document.getElementById('visit_date');
        const select = document.getElementById('doctor_schedule_id');
        const nikInput = document.getElementById('nik');
        const nikHint = document.getElementById('nik-wilayah-hint');
        const dobInput = document.getElementById('dob');
        const genderInput = document.getElementById('gender');
        const province = document.getElementById('province_code');
        const city = document.getElementById('city_code');
        const district = document.getElementById('district_code');
        const village = document.getElementById('village_code');
        const addressCity = document.getElementById('address_city');
        const postal = document.getElementById('address_postal_code');
        let lastNikSynced = '';

        function setNikHint(text) {
            if (! nikHint) return;
            nikHint.textContent = text || '';
        }

        function fillSelect(el, options, selected, placeholder) {
            if (! el) return;
            const ph = placeholder || el.getAttribute('data-placeholder') || 'Pilih';
            el.innerHTML = '';
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = ph;
            el.appendChild(empty);
            Object.keys(options || {}).forEach(function (value) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = options[value];
                if (String(selected) === String(value)) option.selected = true;
                el.appendChild(option);
            });
            if (selected) el.value = selected;
        }

        function fetchJson(url, params) {
            const query = new URLSearchParams(params || {}).toString();
            return fetch(url + (query ? '?' + query : ''), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); });
        }

        function syncNik() {
            if (! nikInput) return;
            const nik = String(nikInput.value || '').replace(/\D+/g, '');
            if (nik.length !== 16) {
                setNikHint(nik.length > 0 ? 'NIK harus 16 digit.' : '');
                return;
            }
            if (nik === lastNikSynced) return;

            setNikHint('');
            fetch(fromNikUrl + '?nik=' + encodeURIComponent(nik), { headers: { 'Accept': 'application/json' } })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (! result.ok) {
                        lastNikSynced = '';
                        return;
                    }
                    lastNikSynced = nik;
                    if (dobInput && result.data.dob && ! dobInput.value) dobInput.value = result.data.dob;
                    if (genderInput && result.data.gender && ! genderInput.value) genderInput.value = result.data.gender;
                })
                .catch(function () {
                    lastNikSynced = '';
                });
        }

        function loadSessions() {
            const params = new URLSearchParams({ branch_id: branch.value, date: date.value });
            fetch(sessionsUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    select.innerHTML = '';
                    if (! payload.sessions.length) {
                        select.innerHTML = '<option value="">Tidak ada sesi tersedia</option>';
                        return;
                    }
                    payload.sessions.forEach(function (session) {
                        const option = document.createElement('option');
                        option.value = session.id;
                        option.textContent = session.doctor + ' · ' + session.time + ' · sisa ' + session.remaining;
                        if (String(preferredScheduleId) === String(session.id)) option.selected = true;
                        select.appendChild(option);
                    });
                });
        }

        if (province) {
            province.addEventListener('change', function () {
                fillSelect(city, {}, '', 'Pilih kab/kota');
                fillSelect(district, {}, '', 'Pilih kecamatan');
                fillSelect(village, {}, '', 'Pilih desa/kelurahan');
                if (! this.value) return;
                fetchJson(wilayahUrls.cities, { province_code: this.value }).then(function (payload) {
                    fillSelect(city, payload.options || {}, '', 'Pilih kab/kota');
                });
            });
        }

        if (city) {
            city.addEventListener('change', function () {
                fillSelect(district, {}, '', 'Pilih kecamatan');
                fillSelect(village, {}, '', 'Pilih desa/kelurahan');
                const selected = city.options[city.selectedIndex];
                if (addressCity && selected && selected.value) {
                    const parts = String(selected.textContent || '').split(' — ');
                    addressCity.value = parts.length > 1 ? parts.slice(1).join(' — ') : selected.textContent;
                }
                if (! this.value) return;
                fetchJson(wilayahUrls.districts, { city_code: this.value }).then(function (payload) {
                    fillSelect(district, payload.options || {}, '', 'Pilih kecamatan');
                });
            });
        }

        if (district) {
            district.addEventListener('change', function () {
                fillSelect(village, {}, '', 'Pilih desa/kelurahan');
                if (! this.value) return;
                fetchJson(wilayahUrls.villages, {
                    district_code: this.value,
                    city_code: city ? city.value : ''
                }).then(function (payload) {
                    fillSelect(village, payload.options || {}, '', 'Pilih desa/kelurahan');
                    if (postal && payload.postal_code && ! postal.value) postal.value = payload.postal_code;
                    if (addressCity && payload.city_name && ! addressCity.value) addressCity.value = payload.city_name;
                });
            });
        }

        if (village) {
            village.addEventListener('change', function () {
                if (! this.value) return;
                fetchJson(wilayahUrls.villages, {
                    district_code: district ? district.value : '',
                    village_code: this.value,
                    city_code: city ? city.value : ''
                }).then(function (payload) {
                    if (postal && payload.postal_code) postal.value = payload.postal_code;
                    if (addressCity && payload.city_name && ! addressCity.value) addressCity.value = payload.city_name;
                });
            });
        }

        branch.addEventListener('change', loadSessions);
        date.addEventListener('change', loadSessions);
        loadSessions();

        if (nikInput) {
            nikInput.addEventListener('input', function () {
                const digits = String(nikInput.value || '').replace(/\D+/g, '');
                if (digits !== nikInput.value) nikInput.value = digits;
                if (digits.length < 16) {
                    lastNikSynced = '';
                    setNikHint(digits.length > 0 ? 'NIK harus 16 digit.' : '');
                    return;
                }
                syncNik();
            });
            nikInput.addEventListener('blur', syncNik);
        }

        if (bookingResult && bookingResult.number) {
            document.getElementById('bookingResultNumber').textContent = bookingResult.number;
            document.getElementById('bookingResultPatient').textContent = bookingResult.patient || '—';
            const scheduleBits = [bookingResult.date, bookingResult.time, bookingResult.room].filter(Boolean);
            document.getElementById('bookingResultSchedule').textContent = scheduleBits.length ? scheduleBits.join(' · ') : '—';
            document.getElementById('bookingResultDoctor').textContent = bookingResult.doctor || '—';
            document.getElementById('bookingResultBranch').textContent = bookingResult.branch || '—';
            document.getElementById('bookingResultMrn').textContent = bookingResult.mrn || '—';
            const modal = new bootstrap.Modal(document.getElementById('bookingResultModal'));
            modal.show();
        }
    })();
</script>
@endpush
