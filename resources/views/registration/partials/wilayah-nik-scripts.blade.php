@once
    @push('styles')
        <link href="{{ theme('libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css">
        <style>
            .main-content:has(select.js-wilayah-select),
            .page-content:has(select.js-wilayah-select),
            .card:has(select.js-wilayah-select),
            .card-body:has(select.js-wilayah-select),
            #new-patient-fields,
            .mb-3:has(select.js-wilayah-select) {
                overflow: visible !important;
            }
            select.js-wilayah-select + .select2-container {
                display: block !important;
                width: 100% !important;
                position: relative;
                z-index: 3;
            }
            select.js-wilayah-select + .select2-container .select2-selection--single {
                height: 38px;
                border: 1px solid #ced4da;
                background-color: #fff;
            }
            select.js-wilayah-select + .select2-container .select2-selection--single .select2-selection__rendered {
                line-height: 36px;
                padding-left: 12px;
                color: #495057;
            }
            select.js-wilayah-select + .select2-container .select2-selection--single .select2-selection__placeholder {
                color: #74788d;
            }
            .select2-container--open {
                z-index: 10050 !important;
            }
            .select2-dropdown {
                z-index: 10050 !important;
                background-color: #fff !important;
                color: #495057 !important;
            }
            .select2-results__options {
                max-height: 240px;
            }
            .select2-container--default .select2-results__option {
                color: #495057 !important;
                background-color: #fff;
            }
            .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
                background-color: #556ee6 !important;
                color: #fff !important;
            }
        </style>
    @endpush

    @push('scripts')
    <script src="{{ theme('libs/select2/js/select2.min.js') }}"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var $ = window.jQuery;
        var urls = {
            cities: @json(route('registration.wilayah.cities')),
            districts: @json(route('registration.wilayah.districts')),
            villages: @json(route('registration.wilayah.villages')),
            fromNik: @json(route('registration.wilayah.from-nik'))
        };

        var province = document.getElementById('province_code');
        var city = document.getElementById('city_code');
        var district = document.getElementById('district_code');
        var village = document.getElementById('village_code');
        var addressCity = document.getElementById('address_city');
        var postal = document.getElementById('address_postal_code');
        var nikInput = document.getElementById('nik');
        var nikHint = document.getElementById('nik-wilayah-hint');
        var dobInput = document.getElementById('dob');
        var genderInput = document.getElementById('gender');
        var branchSelect = document.getElementById('branch_id');
        var syncingFromNik = false;
        var lastNikSynced = '';

        if (! province || ! city || ! district || ! village || ! $ || ! $.fn.select2) return;

        function branchIdParam() {
            return branchSelect && branchSelect.value ? { branch_id: branchSelect.value } : {};
        }

        function setNikHint(text, isError) {
            if (! nikHint) return;
            nikHint.textContent = text || '';
            nikHint.className = 'form-text' + (isError ? ' text-danger' : (text ? ' text-success' : ''));
        }

        function dropdownParentFor(select) {
            var parent = $(select).closest('.mb-3');
            return parent.length ? parent : $(document.body);
        }

        function revealSelect(select) {
            select.classList.remove('select2-hidden-accessible');
            select.removeAttribute('aria-hidden');
            select.style.position = '';
            select.style.width = '';
            select.style.height = '';
            select.style.clip = '';
        }

        function initSearchable(select) {
            var $el = $(select);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }
            try {
                $el.select2({
                    width: '100%',
                    dropdownParent: dropdownParentFor(select),
                    placeholder: $el.data('placeholder') || 'Cari / pilih',
                    allowClear: true,
                    language: {
                        noResults: function () { return 'Tidak ditemukan'; },
                        searching: function () { return 'Mencari…'; }
                    }
                });
            } catch (error) {
                revealSelect(select);
                return;
            }

            var box = select.nextElementSibling;
            if (! box || ! box.classList.contains('select2-container')) {
                revealSelect(select);
                return;
            }
            box.style.width = '100%';
            box.style.display = 'block';
        }

        function optionMap(options) {
            var map = {};
            if (! options) return map;
            if (Array.isArray(options)) {
                options.forEach(function (item, index) {
                    if (item && typeof item === 'object') {
                        var id = item.id != null ? item.id : (item.value != null ? item.value : index);
                        map[String(id)] = String(item.text || item.label || item.name || '');
                    } else if (item != null) {
                        map[String(index)] = String(item);
                    }
                });
                return map;
            }
            Object.keys(options).forEach(function (key) {
                var value = options[key];
                if (value && typeof value === 'object') {
                    var id = value.id != null ? value.id : key;
                    map[String(id)] = String(value.text || value.label || value.name || '');
                } else {
                    map[String(key)] = value == null ? '' : String(value);
                }
            });
            return map;
        }

        function fillSelect(select, options, selected) {
            var $el = $(select);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }

            var placeholderText = select.getAttribute('data-placeholder') || 'Cari / pilih';
            var map = optionMap(options);
            var selectedValue = selected == null ? '' : String(selected);
            select.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = placeholderText;
            select.appendChild(empty);

            Object.keys(map).forEach(function (value) {
                var option = document.createElement('option');
                option.value = value;
                option.textContent = map[value];
                if (selectedValue !== '' && selectedValue === value) option.selected = true;
                select.appendChild(option);
            });

            select.value = selectedValue && map[selectedValue] != null ? selectedValue : '';
            initSearchable(select);
        }

        function clearBelow(level) {
            if (level <= 1) fillSelect(city, {}, '');
            if (level <= 2) fillSelect(district, {}, '');
            if (level <= 3) fillSelect(village, {}, '');
        }

        function applyNikWilayah(data) {
            if (! data.province_code || ! data.city_code) {
                if (dobInput && data.dob && ! dobInput.value) {
                    dobInput.value = data.dob;
                }
                if (genderInput && data.gender && ! genderInput.value) {
                    genderInput.value = data.gender;
                    if ($ && $.fn.select2 && $(genderInput).data('select2')) {
                        $(genderInput).val(data.gender).trigger('change');
                    }
                }
                setNikHint('NIK 16 digit. Kode wilayah tidak ada di master, isi alamat secara manual.', false);
                document.dispatchEvent(new CustomEvent('wilayah:nik-synced', {
                    detail: Object.assign({ nik: data.nik || '' }, data || {})
                }));
                return;
            }

            syncingFromNik = true;
            fillSelect(province, @json($provinces ?? []), data.province_code || '');
            fillSelect(city, data.cities || {}, data.city_code || '');
            fillSelect(district, data.districts || {}, data.district_code || '');
            fillSelect(village, data.villages || {}, '');

            if (addressCity && data.city_name) {
                addressCity.value = data.city_name;
            }
            if (dobInput && data.dob && ! dobInput.value) {
                dobInput.value = data.dob;
            }
            if (genderInput && data.gender && ! genderInput.value) {
                genderInput.value = data.gender;
                if ($ && $.fn.select2 && $(genderInput).data('select2')) {
                    $(genderInput).val(data.gender).trigger('change');
                }
            }

            syncingFromNik = false;

            var bits = [data.province_name, data.city_name, data.district_name].filter(Boolean);
            setNikHint(bits.length
                ? ('Wilayah dari NIK: ' + bits.join(' · ') + '. Pilih desa/kelurahan (Master Wilayah SATUSEHAT / Kemendagri).')
                : 'Wilayah dari NIK terisi.', false);

            // Muat ulang desa dari Master Wilayah SATUSEHAT bila cabang sudah terhubung.
            if (data.district_code) {
                loadVillages(data.village_code || '', function () {
                    document.dispatchEvent(new CustomEvent('wilayah:nik-synced', {
                        detail: Object.assign({ nik: data.nik || '' }, data || {})
                    }));
                });
                return;
            }

            document.dispatchEvent(new CustomEvent('wilayah:nik-synced', {
                detail: Object.assign({ nik: data.nik || '' }, data || {})
            }));
        }

        function loadVillages(selected, done) {
            if (! district.value) {
                fillSelect(village, {}, '');
                if (typeof done === 'function') done();
                return;
            }
            if (! window.axios) {
                if (typeof done === 'function') done();
                return;
            }
            var params = Object.assign({
                district_code: district.value,
                city_code: city.value
            }, branchIdParam());
            window.axios.get(urls.villages, { params: params }).then(function (response) {
                fillSelect(village, response.data.options, selected || '');
                if (addressCity && response.data.city_name && ! addressCity.value) {
                    addressCity.value = response.data.city_name;
                }
                if (typeof done === 'function') done();
            }).catch(function () {
                if (typeof done === 'function') done();
            });
        }

        function syncNikToWilayah() {
            if (! nikInput || ! window.axios) return;
            var nik = String(nikInput.value || '').replace(/\D+/g, '');
            if (nik.length !== 16) {
                if (nik.length > 0) setNikHint('NIK harus 16 digit.', true);
                else setNikHint('', false);
                return;
            }
            if (nik === lastNikSynced) return;

            setNikHint('Memetakan NIK ke wilayah…', false);
            window.axios.get(urls.fromNik, { params: { nik: nik } }).then(function (response) {
                lastNikSynced = nik;
                var payload = Object.assign({ nik: nik }, response.data || {});
                applyNikWilayah(payload);
            }).catch(function (error) {
                lastNikSynced = '';
                var message = error.response && error.response.data && error.response.data.message
                    ? error.response.data.message
                    : 'NIK tidak bisa dipetakan ke wilayah.';
                setNikHint(message, true);
            });
        }

        [province, city, district, village].forEach(initSearchable);

        $(province).on('change', function () {
            if (syncingFromNik) return;
            clearBelow(1);
            if (! this.value || ! window.axios) return;
            window.axios.get(urls.cities, { params: { province_code: this.value } }).then(function (response) {
                fillSelect(city, response.data.options, '');
            });
        });

        $(city).on('change', function () {
            if (syncingFromNik) return;
            clearBelow(2);
            var selected = city.options[city.selectedIndex];
            if (addressCity && selected && selected.value) {
                var label = selected.textContent || '';
                var parts = label.split(' — ');
                addressCity.value = parts.length > 1 ? parts.slice(1).join(' — ') : label;
            }
            if (! this.value || ! window.axios) return;
            window.axios.get(urls.districts, { params: { city_code: this.value } }).then(function (response) {
                fillSelect(district, response.data.options, '');
            });
        });

        $(district).on('change', function () {
            if (syncingFromNik) return;
            clearBelow(3);
            if (! this.value) return;
            loadVillages('');
        });

        $(village).on('change', function () {
            if (syncingFromNik) return;
            if (! this.value) return;
            var params = Object.assign({
                district_code: district.value,
                city_code: city.value,
                village_code: this.value
            }, branchIdParam());
            window.axios.get(urls.villages, { params: params }).then(function (response) {
                if (postal && response.data.postal_code) {
                    postal.value = response.data.postal_code;
                }
            });
        });

        if (branchSelect) {
            $(branchSelect).on('change', function () {
                if (district.value) loadVillages(village.value || '');
            });
        }

        if (nikInput) {
            nikInput.addEventListener('input', function () {
                var digits = String(nikInput.value || '').replace(/\D+/g, '');
                if (digits !== nikInput.value) nikInput.value = digits;
                if (digits.length < 16) {
                    lastNikSynced = '';
                    if (digits.length > 0) setNikHint('NIK harus 16 digit.', false);
                    else setNikHint('', false);
                    return;
                }
                syncNikToWilayah();
            });
            nikInput.addEventListener('blur', syncNikToWilayah);
            if (String(nikInput.value || '').replace(/\D+/g, '').length === 16) {
                syncNikToWilayah();
            }
        }
    });
    </script>
    @endpush
@endonce
