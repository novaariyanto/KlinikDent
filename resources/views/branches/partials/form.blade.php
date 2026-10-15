@php
    $branch = $branch ?? null;
    $tenantOptions = collect($tenants ?? [])->mapWithKeys(fn ($tenant) => [$tenant->id => $tenant->name])->all();
    $hours = old('opening_hours', $branch?->opening_hours ?? []);
@endphp

<div class="row">
    @if (auth()->user()?->isPlatformAdmin())
        <div class="col-md-6">
            <x-select
                name="tenant_id"
                label="Klinik"
                :options="$tenantOptions"
                :selected="old('tenant_id', $branch?->tenant_id)"
                required
            />
        </div>
    @endif
    <div class="col-md-6">
        <x-input name="name" label="Nama Cabang" :value="$branch?->name" required />
    </div>
    <div class="col-md-12">
        <x-input name="address" label="Alamat" :value="$branch?->address" />
    </div>
    <div class="col-md-4">
        <x-input name="city" label="Kota" :value="$branch?->city" />
    </div>
    <div class="col-md-4">
        <x-input name="postal_code" label="Kode Pos" :value="$branch?->postal_code" />
    </div>
    <div class="col-md-4">
        <x-input name="phone" label="Telepon" :value="$branch?->phone" />
    </div>
    <div class="col-md-6">
        <x-input name="email" type="email" label="Email" :value="$branch?->email" />
    </div>
    <div class="col-md-6">
        <x-input name="website" label="Website" :value="$branch?->website" placeholder="www.klinik.co.id" />
    </div>
    <div class="col-12">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        <label class="form-label" for="branch-map-search">Titik lokasi</label>
        <div class="input-group mb-2">
            <input type="search" id="branch-map-search" class="form-control" placeholder="Cari alamat atau nama tempat">
            <button type="button" class="btn btn-secondary" id="branch-map-find">Cari</button>
        </div>
        <div id="branch-map" style="height:320px;border-radius:.5rem;border:1px solid var(--bs-border-color, #dee2e6)"></div>
        <div class="form-text mb-3">Klik peta atau geser penanda. Lintang dan bujur terisi dari titik itu.</div>
    </div>
    <div class="col-md-4">
        <x-input name="latitude" label="Lintang" :value="$branch?->latitude" placeholder="-6.2000000" />
    </div>
    <div class="col-md-4">
        <x-input name="longitude" label="Bujur" :value="$branch?->longitude" placeholder="106.8166660" />
    </div>
    <div class="col-md-4">
        <x-input name="altitude" label="Ketinggian (m)" :value="$branch?->altitude" />
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-2">
            <input
                class="form-check-input"
                type="checkbox"
                name="is_active"
                id="is_active"
                value="1"
                @checked(old('is_active', $branch?->is_active ?? true))
            >
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>

<h6 class="mt-2 mb-3">Jam Operasional</h6>
<div class="row">
    @foreach (\App\Models\Branch::WEEK_DAYS as $day => $label)
        <div class="col-md-6">
            <x-input
                name="opening_hours[{{ $day }}]"
                :label="$label"
                :value="$hours[$day] ?? ''"
                placeholder="08:00-17:00"
            />
        </div>
    @endforeach
</div>

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mapElement = document.getElementById('branch-map');
            var latInput = document.getElementById('latitude');
            var lngInput = document.getElementById('longitude');

            if (!mapElement || !latInput || !lngInput || typeof L === 'undefined') {
                return;
            }

            var lat = parseFloat(latInput.value);
            var lng = parseFloat(lngInput.value);
            var hasPoint = Number.isFinite(lat) && Number.isFinite(lng);
            var center = hasPoint ? [lat, lng] : [-2.5, 118];
            var map = L.map(mapElement).setView(center, hasPoint ? 16 : 5);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            var marker = L.marker(center, { draggable: true });

            if (hasPoint) {
                marker.addTo(map);
            }

            function writePoint(latlng) {
                latInput.value = latlng.lat.toFixed(7);
                lngInput.value = latlng.lng.toFixed(7);

                if (!map.hasLayer(marker)) {
                    marker.addTo(map);
                }

                marker.setLatLng(latlng);
            }

            map.on('click', function (event) {
                writePoint(event.latlng);
            });

            marker.on('dragend', function () {
                writePoint(marker.getLatLng());
            });

            function readInputs() {
                var nextLat = parseFloat(latInput.value);
                var nextLng = parseFloat(lngInput.value);

                if (!Number.isFinite(nextLat) || !Number.isFinite(nextLng)) {
                    return;
                }

                var latlng = L.latLng(nextLat, nextLng);
                writePoint(latlng);
                map.setView(latlng, Math.max(map.getZoom(), 16));
            }

            latInput.addEventListener('change', readInputs);
            lngInput.addEventListener('change', readInputs);

            var search = document.getElementById('branch-map-search');
            var find = document.getElementById('branch-map-find');

            function searchPlace() {
                var query = (search.value || '').trim();

                if (query.length < 3) {
                    return;
                }

                find.disabled = true;
                fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json' }
                }).then(function (response) {
                    return response.json();
                }).then(function (results) {
                    if (!results.length) {
                        return;
                    }

                    var latlng = L.latLng(parseFloat(results[0].lat), parseFloat(results[0].lon));
                    writePoint(latlng);
                    map.setView(latlng, 16);
                }).finally(function () {
                    find.disabled = false;
                });
            }

            find.addEventListener('click', searchPlace);
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchPlace();
                }
            });

            setTimeout(function () {
                map.invalidateSize();
            }, 200);
        });
    </script>
@endpush
