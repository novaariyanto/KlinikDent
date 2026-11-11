@php
    $ingredientState = function ($ingredient) {
        if ($ingredient instanceof \App\Models\PrescriptionCompoundItem) {
            return [
                'name' => $ingredient->name,
                'medicine_id' => $ingredient->medicine_id,
                'quantity' => $ingredient->quantity,
                'per_dose_quantity' => $ingredient->per_dose_quantity,
                'unit' => $ingredient->unit,
                'strength_value' => $ingredient->strength_value,
                'strength_unit' => $ingredient->strength_unit,
                'role' => $ingredient->role ?: 'active',
                'additive_kind' => $ingredient->additive_kind,
            ];
        }

        return is_array($ingredient) ? $ingredient : [];
    };
    $patientAge = $visit->patient?->age();
    $isChild = $patientAge !== null && $patientAge < 18;
    $patientWeight = is_array($record?->vital_signs ?? null) ? ($record->vital_signs['weight'] ?? null) : null;
    $ssIssues = \App\Support\Clinical\PrescriptionSatuSehatReadiness::forVisit($visit);
    $ssBlocking = \App\Support\Clinical\PrescriptionSatuSehatReadiness::blockingMessages($ssIssues);
    $ssWarnings = \App\Support\Clinical\PrescriptionSatuSehatReadiness::warningMessages($ssIssues);
@endphp
        @if ($ssBlocking !== [] || $ssWarnings !== [])
            <div class="alert {{ $ssBlocking !== [] ? 'alert-danger' : 'alert-warning' }} py-2 mb-3" role="status">
                <div class="fw-semibold mb-1">Kesiapan pengiriman farmasi · SATUSEHAT</div>
                @if ($ssBlocking !== [])
                    <ul class="mb-1 ps-3">
                        @foreach ($ssBlocking as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                    <p class="mb-0 small">Perbaiki item di atas sebelum kirim ke farmasi / selesaikan kunjungan.</p>
                @endif
                @if ($ssWarnings !== [])
                    <ul class="mb-0 ps-3 {{ $ssBlocking !== [] ? 'mt-2' : '' }}">
                        @foreach ($ssWarnings as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
        @if ($isChild)
            <div class="rx-child-note">
                <span class="badge bg-info">Pasien anak</span>
                <span>{{ $visit->patient?->ageLabel() }} · Berat {{ filled($patientWeight) ? $patientWeight.' kg' : 'belum diisi' }}</span>
                <span class="rx-meta mb-0">Dosis ditentukan dokter, tidak otomatis dari berat badan.</span>
            </div>
        @endif

        @forelse ($visit->prescriptions as $prescription)
            <div class="plan-block rx-plan-block">
                <div class="rx-plan-head">
                    <strong>Resep #{{ $prescription->id }}</strong>
                    <div class="rx-toolbar">
                        <span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span>
                        @can('prescription.view')
                            @if ($prescription->items->isNotEmpty())
                                <a href="{{ route('care.prescription.pdf', [$visit, $prescription]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border" title="Cetak resep" aria-label="Cetak resep #{{ $prescription->id }}">
                                    <i class="bx bx-printer me-1"></i>Cetak
                                </a>
                            @endif
                        @endcan
                        @can('prescription.create')
                            @if ($writable && $prescription->isDraft() && $prescription->items->isNotEmpty())
                                <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#rx-save-template-{{ $prescription->id }}" title="Simpan sebagai template" aria-label="Simpan sebagai template">
                                    <i class="bx bx-bookmark-plus me-1"></i>Simpan sebagai template
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
                <x-table class="rx-table mb-0">
                    <thead>
                        <tr>
                            <th>Obat</th>
                            <th>Dosis</th>
                            <th>Frekuensi</th>
                            <th>Aturan pakai</th>
                            <th>Qty</th>
                            @if ($writable && $prescription->isDraft() && auth()->user()?->can('prescription.update'))
                                <th class="text-end">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prescription->items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->label() }}</div>
                                    @if ($item->medication_type)
                                        <div class="rx-meta">{{ $item->medication_type->value }} · {{ $item->medication_type->label() }}</div>
                                    @endif
                                    @if ($item->medicine && ! filled($item->medicine->kfa_code))
                                        <div class="rx-meta rx-meta--warn">Belum KFA — tidak ke SATUSEHAT</div>
                                    @elseif ($item->medicine?->kfa_code)
                                        <div class="rx-meta">KFA {{ $item->medicine->kfa_code }}</div>
                                    @endif
                                    @if ($item->isCompound())
                                        @foreach ($item->ingredients as $ingredient)
                                            <div class="rx-meta">{{ $ingredient->compositionLabel() }}</div>
                                        @endforeach
                                        @php
                                            $missingKfa = $item->ingredients->contains(function ($ing) {
                                                return $ing->medicine_id && ! filled(optional($ing->medicine)->kfa_code);
                                            });
                                        @endphp
                                        @if ($missingKfa)
                                            <div class="rx-meta rx-meta--warn">Bahan belum KFA — tidak ke SATUSEHAT</div>
                                        @endif
                                    @endif
                                </td>
                                <td>{{ $item->dosage ?: '-' }}</td>
                                <td>{{ $item->frequency ?: '-' }}</td>
                                <td>
                                    {{ $item->duration ?: '-' }}
                                    @if ($item->course)
                                        <div class="rx-meta">{{ $item->course }}</div>
                                    @endif
                                </td>
                                <td>{{ $item->quantity }}{{ $item->isCompound() ? ' bks' : '' }}</td>
                                @if ($writable && $prescription->isDraft() && auth()->user()?->can('prescription.update'))
                                    <td class="text-end">
                                        <div class="rx-actions justify-content-end">
                                            <button type="button" class="btn btn-sm btn-soft-primary rx-icon-btn" data-toggle-panel="#rx-edit-{{ $item->id }}" title="Ubah" aria-label="Ubah item resep">
                                                <i class="bx bx-edit"></i>
                                            </button>
                                            <form action="{{ route('care.prescription-item.destroy', [$visit, $item]) }}" method="POST" class="d-inline" data-rx-ajax data-rx-confirm="Hapus obat ini dari resep?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-soft-danger rx-icon-btn" type="submit" title="Hapus" aria-label="Hapus item resep">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            @if ($writable && $prescription->isDraft() && auth()->user()?->can('prescription.update'))
                                @php
                                    $editingThis = old('_form') === 'prescription-update' && (string) old('_item') === (string) $item->id;
                                @endphp
                                <tr id="rx-edit-{{ $item->id }}" @if (! $editingThis) hidden @endif>
                                    <td colspan="6">
                                        <form action="{{ route('care.prescription-item.update', [$visit, $item]) }}" method="POST" class="care-inline-form rx-inline-form rx-form" data-rx-ajax>
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="_form" value="prescription-update">
                                            <input type="hidden" name="_item" value="{{ $item->id }}">
                                            @if ($item->isCompound())
                                                @php
                                                    $editRows = $editingThis ? array_values(old('ingredients', [])) : $item->ingredients->map($ingredientState)->all();
                                                @endphp
                                                @include('clinical.care.partials.prescription-compound-fields', [
                                                    'sigPrefix' => 'rx-edit-'.$item->id,
                                                    'ingredientRows' => $editRows,
                                                    'medicines' => $medicines,
                                                    'medicationType' => $editingThis ? old('medication_type', $item->medication_type?->value) : $item->medication_type?->value,
                                                    'dosage' => $editingThis ? old('dosage') : $item->dosage,
                                                    'frequency' => $editingThis ? old('frequency') : $item->frequency,
                                                    'duration' => $editingThis ? old('duration') : $item->duration,
                                                    'course' => $editingThis ? old('course') : $item->course,
                                                    'quantity' => $editingThis ? old('quantity') : $item->quantity,
                                                ])
                                            @else
                                                <div class="row g-2">
                                                    <div class="col-md-6 col-lg-3">
                                                        <label class="form-label" for="rx-dosage-{{ $item->id }}">Dosis</label>
                                                        @include('clinical.care.partials.prescription-sig', ['group' => 'doses', 'target' => 'rx-dosage-'.$item->id])
                                                        <x-input name="dosage" :id="'rx-dosage-'.$item->id" label="" :value="$editingThis ? old('dosage') : $item->dosage" required />
                                                    </div>
                                                    <div class="col-md-6 col-lg-3">
                                                        <label class="form-label" for="rx-frequency-{{ $item->id }}">Frekuensi</label>
                                                        @include('clinical.care.partials.prescription-sig', ['group' => 'frequencies', 'target' => 'rx-frequency-'.$item->id])
                                                        <x-input name="frequency" :id="'rx-frequency-'.$item->id" label="" :value="$editingThis ? old('frequency') : $item->frequency" required />
                                                    </div>
                                                    <div class="col-md-6 col-lg-3">
                                                        <label class="form-label" for="rx-duration-{{ $item->id }}">Aturan pakai</label>
                                                        @include('clinical.care.partials.prescription-sig', ['group' => 'instructions', 'target' => 'rx-duration-'.$item->id])
                                                        <x-input name="duration" :id="'rx-duration-'.$item->id" label="" :value="$editingThis ? old('duration') : $item->duration" required />
                                                    </div>
                                                    <div class="col-6 col-md-3 col-lg-2">
                                                        <label class="form-label" for="rx-course-{{ $item->id }}">Durasi</label>
                                                        @include('clinical.care.partials.prescription-sig', ['group' => 'courses', 'target' => 'rx-course-'.$item->id])
                                                        <x-input name="course" :id="'rx-course-'.$item->id" label="" :value="$editingThis ? old('course') : $item->course" />
                                                    </div>
                                                    <div class="col-6 col-md-3 col-lg-1">
                                                        <x-input name="quantity" type="number" min="1" :id="'rx-qty-'.$item->id" label="Qty" :value="$editingThis ? old('quantity') : $item->quantity" required />
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="rx-toolbar justify-content-end mt-1">
                                                <button type="submit" class="btn btn-sm btn-primary" title="Simpan" aria-label="Simpan perubahan item">
                                                    <i class="bx bx-save me-1"></i>Simpan
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </x-table>
                @if ($writable && $prescription->isDraft())
                    <p class="rx-meta mt-2 mb-0">Draft · dikirim ke farmasi saat Simpan &amp; Selesaikan Kunjungan.</p>
                @endif
                @can('prescription.create')
                    @if ($writable && $prescription->isDraft() && $prescription->items->isNotEmpty())
                        @php
                            $saveFormKey = 'template-from-'.$prescription->id;
                            $reopenSave = old('_form') === $saveFormKey;
                            $saveSources = $reopenSave
                                ? collect(old('items', []))->values()
                                : $prescription->items->values();
                        @endphp
                        <x-modal id="rx-save-template-{{ $prescription->id }}" title="Simpan sebagai template" size="modal-xl" class="care-modal" :data-show-on-load="$reopenSave ? '' : null">
                            <p class="form-text mb-2">Obat resep ini sudah diisi. Beri nama template, lalu simpan. Racikan ikut tersimpan.</p>
                            <form action="{{ route('care.prescription-templates.store', $visit) }}" method="POST" class="rx-form" data-template-editor data-rx-ajax>
                                @csrf
                                <input type="hidden" name="_form" value="{{ $saveFormKey }}">
                                <x-input name="name" label="Nama template" :value="$reopenSave ? old('name') : ''" placeholder="Resep sakit gigi" required />
                                <div data-template-rows>
                                    @foreach ($saveSources as $index => $source)
                                        @php
                                            $isModel = $source instanceof \App\Models\PrescriptionItem;
                                            $kind = $isModel ? ($source->kind?->value ?? 'ready') : ($source['kind'] ?? 'ready');
                                        @endphp
                                        @if ($kind === 'racikan')
                                            @include('clinical.care.partials.prescription-template-compound-row', [
                                                'index' => $index,
                                                'prefix' => 'tpl-cmp-rx-'.$prescription->id.'-'.$index,
                                                'row' => $isModel ? [
                                                    'medication_type' => $source->medication_type?->value,
                                                    'dosage' => $source->dosage,
                                                    'frequency' => $source->frequency,
                                                    'duration' => $source->duration,
                                                    'course' => $source->course,
                                                    'quantity' => $source->quantity,
                                                    'ingredients' => $source->ingredients->map(fn ($ingredient) => $ingredient->only(['name', 'medicine_id', 'unit', 'role', 'additive_kind', 'quantity', 'per_dose_quantity', 'strength_value', 'strength_unit']))->all(),
                                                ] : $source,
                                                'medicines' => $medicines,
                                            ])
                                        @else
                                            @include('clinical.care.partials.prescription-template-row', [
                                                'index' => $index,
                                                'prefix' => 'tpl-rx-'.$prescription->id.'-'.$index,
                                                'row' => $isModel ? [
                                                    'medicine_id' => $source->medicine_id,
                                                    'medicine_label' => $source->medicine?->catalogLabel(),
                                                    'dosage' => $source->dosage,
                                                    'frequency' => $source->frequency,
                                                    'duration' => $source->duration,
                                                    'course' => $source->course,
                                                    'unit' => $source->medicine?->unitLabel(),
                                                    'quantity' => $source->quantity,
                                                ] : $source,
                                                'medicines' => $medicines,
                                            ])
                                        @endif
                                    @endforeach
                                </div>
                                <template data-template-row-template>
                                    @include('clinical.care.partials.prescription-template-row', [
                                        'index' => '__i__',
                                        'prefix' => 'tpl-rx-'.$prescription->id.'-__i__',
                                        'row' => ['quantity' => 1],
                                        'medicines' => $medicines,
                                    ])
                                </template>
                                <template data-template-compound-template>
                                    @include('clinical.care.partials.prescription-template-compound-row', [
                                        'index' => '__i__',
                                        'prefix' => 'tpl-cmp-rx-'.$prescription->id.'-__i__',
                                        'row' => [
                                            'medication_type' => 'EP',
                                            'quantity' => 1,
                                            'ingredients' => [
                                                ['role' => 'active', 'quantity' => 1],
                                                ['role' => 'active', 'quantity' => 1],
                                            ],
                                        ],
                                        'medicines' => $medicines,
                                    ])
                                </template>
                                <div class="rx-toolbar justify-content-between mt-1">
                                    <div class="rx-toolbar">
                                        <button type="button" class="btn btn-sm btn-light border" data-add-template-row title="Tambah obat" aria-label="Tambah obat ke template">
                                            <i class="bx bx-plus me-1"></i>Tambah obat
                                        </button>
                                        <button type="button" class="btn btn-sm btn-light border" data-add-template-compound title="Tambah racikan" aria-label="Tambah racikan ke template">
                                            <i class="bx bx-capsule me-1"></i>Tambah racikan
                                        </button>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary" title="Simpan template" aria-label="Simpan template">
                                        <i class="bx bx-save me-1"></i>Simpan
                                    </button>
                                </div>
                            </form>
                        </x-modal>
                    @endif
                @endcan
            </div>
        @empty
            <p class="text-muted">Tidak ada resep.</p>
        @endforelse
