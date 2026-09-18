@extends('layouts.app')

@section('title', 'Pengaturan Klinik')

@section('content')
    <x-page-header title="Pengaturan Klinik" :breadcrumb="['Pengaturan' => null, 'Klinik' => route('settings.clinic')]" />

    <x-alert />

    <form method="POST" action="{{ route('settings.clinic.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xl-8">
                <x-card title="Identitas Klinik">
                    <div class="row">
                        <div class="col-md-6">
                            <x-input name="clinic_name" label="Nama Klinik" :value="$settings['clinic_name']" required />
                        </div>
                        <div class="col-md-6">
                            <x-input name="legal_name" label="Nama Badan Usaha" :value="$settings['legal_name']" placeholder="PT / Yayasan / CV" />
                        </div>
                        <div class="col-md-12">
                            <x-input name="tagline" label="Slogan" :value="$settings['tagline']" placeholder="Mis. Senyum sehat, perawatan nyaman" />
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">Alamat</label>
                                <textarea name="address" id="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $settings['address']) }}</textarea>
                                @error('address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <x-input name="city" label="Kota / Kabupaten" :value="$settings['city']" />
                        </div>
                        <div class="col-md-4">
                            <x-input name="province" label="Provinsi" :value="$settings['province']" />
                        </div>
                        <div class="col-md-4">
                            <x-input name="postal_code" label="Kode Pos" :value="$settings['postal_code']" />
                        </div>
                    </div>
                </x-card>

                <x-card title="Kontak">
                    <div class="row">
                        <div class="col-md-4">
                            <x-input name="phone" label="Telepon" :value="$settings['phone']" />
                        </div>
                        <div class="col-md-4">
                            <x-input name="email" type="email" label="Email" :value="$settings['email']" />
                        </div>
                        <div class="col-md-4">
                            <x-input name="website" label="Situs Web" :value="$settings['website']" placeholder="https://" />
                        </div>
                    </div>
                </x-card>

                <x-card title="Izin & Penanggung Jawab">
                    <div class="row">
                        <div class="col-md-6">
                            <x-input name="npwp" label="NPWP" :value="$settings['npwp']" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="license_number" label="Nomor Izin Klinik" :value="$settings['license_number']" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="pic_name" label="Penanggung Jawab" :value="$settings['pic_name']" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="pic_sip" label="SIP Penanggung Jawab" :value="$settings['pic_sip']" />
                        </div>
                    </div>
                </x-card>

                <x-card title="Dokumen & Operasional">
                    <div class="row">
                        <div class="col-md-6">
                            <x-select
                                name="timezone"
                                label="Zona Waktu"
                                :options="$timezones"
                                :selected="$settings['timezone']"
                                required
                                :placeholder="null"
                            />
                        </div>
                        <div class="col-md-6">
                            <x-input name="rm_prefix" label="Prefix Nomor RM" :value="$settings['rm_prefix']" placeholder="KLINIK-A" />
                            <small class="text-muted d-block mb-3 mt-n2">Huruf, angka, dan tanda hubung. Dipakai untuk nomor rekam medis baru.</small>
                        </div>
                        <div class="col-md-6">
                            <x-input name="print_city" label="Kota pada Dokumen" :value="$settings['print_city']" placeholder="Jakarta" />
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="print_footer" class="form-label">Catatan kaki dokumen</label>
                                <textarea name="print_footer" id="print_footer" rows="2" class="form-control @error('print_footer') is-invalid @enderror" placeholder="Dokumen ini dicetak secara elektronik.">{{ old('print_footer', $settings['print_footer']) }}</textarea>
                                @error('print_footer')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </x-card>

                @if ($canManage)
                    <div class="mb-4">
                        <x-button type="submit" icon="bx bx-save">Simpan Pengaturan</x-button>
                    </div>
                @else
                    <div class="alert alert-info">Anda dapat melihat pengaturan, tetapi tidak memiliki izin untuk mengubahnya.</div>
                @endif
            </div>

            <div class="col-xl-4">
                <x-card title="Logo Klinik">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="border rounded p-2 bg-light">
                            <img src="{{ $logoUrl }}" alt="Logo" style="max-height: 48px; max-width: 160px;">
                        </div>
                        @if ($hasCustomLogo)
                            <span class="badge bg-success">Logo kustom</span>
                        @else
                            <span class="badge bg-secondary">Logo default</span>
                        @endif
                    </div>
                    @if ($canManage)
                        <input
                            type="file"
                            name="logo"
                            id="logo"
                            class="form-control @error('logo') is-invalid @enderror"
                            accept="image/png,image/jpeg,image/svg+xml,image/webp"
                        >
                        <small class="text-muted d-block mt-1">PNG, JPG, SVG, atau WEBP. Maks. 2 MB.</small>
                        @error('logo')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @if ($hasCustomLogo)
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="remove_logo" id="remove_logo" value="1">
                                <label class="form-check-label" for="remove_logo">Hapus logo kustom</label>
                            </div>
                        @endif
                    @endif
                </x-card>

                <x-card title="Pratinjau Kop Surat">
                    <div class="border rounded p-3 bg-light">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="{{ $logoUrl }}" alt="" style="height: 36px; max-width: 80px;">
                            <div>
                                <h5 class="mb-0">{{ $settings['clinic_name'] }}</h5>
                                @if ($settings['tagline'])
                                    <div class="text-muted font-size-12">{{ $settings['tagline'] }}</div>
                                @endif
                            </div>
                        </div>
                        @if ($settings['legal_name'])
                            <div class="font-size-12">{{ $settings['legal_name'] }}</div>
                        @endif
                        @foreach (\App\Support\ClinicSettings::addressLines() as $line)
                            <div class="font-size-12 text-muted">{{ $line }}</div>
                        @endforeach
                        @if ($settings['phone'] || $settings['email'])
                            <div class="font-size-12 text-muted">
                                {{ collect([$settings['phone'] ? 'Telp. '.$settings['phone'] : null, $settings['email']])->filter()->join(' · ') }}
                            </div>
                        @endif
                        @if ($settings['license_number'])
                            <div class="font-size-12 text-muted">Izin: {{ $settings['license_number'] }}</div>
                        @endif
                    </div>
                    <p class="text-muted font-size-12 mb-0 mt-3">
                        Subdomain tenant: <code>{{ $tenant->subdomain }}</code>
                    </p>
                </x-card>
            </div>
        </div>
    </form>
@endsection
