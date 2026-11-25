@php
    use App\Support\Pharmacy\MedicineStockQuery;

    $draft = $visit->prescriptions->first(fn ($rx) => $rx->isDraft())
        ?? $visit->prescriptions->sortByDesc('id')->first();
    $medicineIds = $visit->prescriptions
        ->flatMap(fn ($rx) => $rx->items->pluck('medicine_id'))
        ->merge($medicines->pluck('id'))
        ->filter()
        ->unique()
        ->values()
        ->all();
    $stockMap = app(MedicineStockQuery::class)->availableByMedicines(
        (int) $visit->tenant_id,
        (int) $visit->branch_id,
        $medicineIds,
    );
    $unitOptions = \App\Models\Medicine::UNITS + ['ml' => 'ml'];
    $estimate = $visit->prescriptions->flatMap->items->sum(fn ($item) => $item->subtotal());
    $canEditDraft = $writable && auth()->user()?->can('prescription.create');
    $racikIndex = 0;
@endphp

<div class="rx-editor" data-rx-editor
     data-store="{{ route('care.prescription-item.store', $visit) }}"
     data-template-apply-base="{{ url('/visits/'.$visit->id.'/care/prescription-templates') }}"
     data-editable="{{ $canEditDraft ? '1' : '0' }}"
     data-auto-draft="{{ $canEditDraft && $visit->prescriptions->every(fn ($rx) => $rx->items->isEmpty()) ? '1' : '0' }}"
     autocomplete="off">
    <div class="rx-toolbar-mini">
        @can('prescription.view')
            <a href="{{ route('care.prescriptions.pdf', $visit) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border">
                <i class="bx bx-printer me-1"></i>Cetak
            </a>
            <a href="{{ route('care.prescriptions.receipt', $visit) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border">
                <i class="bx bx-receipt me-1"></i>58mm
            </a>
        @endcan
        @if ($canEditDraft)
            @if ($draft && $draft->isDraft() && $draft->items->isNotEmpty())
                <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#rx-save-template-{{ $draft->id }}">
                    # Simpan Sebagai Template
                </button>
            @endif
            <select class="form-select form-select-sm" style="max-width:14rem" data-rx-template-select>
                <option value="">Pilih Template Resep</option>
                @foreach ($prescriptionTemplates ?? [] as $template)
                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="table-responsive rx-editor-table-wrap">
        <table class="table table-sm table-bordered rx-editor-table mb-0">
            <thead>
                <tr>
                    <th>R/</th>
                    <th>Barang</th>
                    <th>Kekuatan</th>
                    <th>Dosis</th>
                    <th>Stok</th>
                    <th>Satuan</th>
                    <th title="Pagi, Siang, Sore, Malam">Signa</th>
                    <th>Waktu</th>
                    <th title="Jumlah hari obat">JHO (hari)</th>
                    <th>Qty</th>
                    <th>Keterangan</th>
                    <th class="rx-col-money">Harga</th>
                    <th class="rx-col-money">Subtotal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody data-rx-rows>
                @forelse ($visit->prescriptions as $prescription)
                    @foreach ($prescription->items as $item)
                        @php
                            $isCompound = $item->isCompound();
                            if ($isCompound) {
                                $racikIndex++;
                            }
                            $stock = $item->medicine_id ? ($stockMap[$item->medicine_id] ?? 0) : null;
                            $rowEditable = $canEditDraft && $prescription->isDraft() && auth()->user()?->can('prescription.update');
                        @endphp
                        <tr class="rx-row {{ $isCompound ? 'rx-row--compound' : '' }}"
                            data-rx-row
                            data-rx-key="{{ $item->id }}"
                            data-item-id="{{ $item->id }}"
                            data-kind="{{ $item->kind?->value }}"
                            data-update="{{ route('care.prescription-item.update', [$visit, $item]) }}"
                            data-destroy="{{ route('care.prescription-item.destroy', [$visit, $item]) }}">
                            <td class="text-center fw-semibold">R/</td>
                            <td>
                                @if ($isCompound)
                                    <div class="fw-semibold">RACIK-{{ str_pad((string) $racikIndex, 2, '0', STR_PAD_LEFT) }}</div>
                                    <div class="rx-when-view">
                                        <div class="rx-meta">{{ $item->name ?: $item->label() }}</div>
                                        <div class="rx-meta">{{ $item->ingredients->count() }} komponen</div>
                                    </div>
                                    @if ($rowEditable)
                                        <div class="rx-when-edit">
                                            <input type="hidden" name="kind" value="racikan">
                                            <input type="text" class="form-control form-control-sm mb-1" name="name" value="{{ $item->name }}" placeholder="Nama racikan" maxlength="80" data-rx-field autocomplete="off">
                                            <button type="button" class="btn btn-sm btn-light border" data-rx-add-ingredient>+ Komponen Racik</button>
                                        </div>
                                    @endif
                                    <input type="hidden" name="medicine_id" value="{{ $item->medicine_id }}">
                                @elseif ($rowEditable)
                                    <span class="rx-when-view fw-semibold">{{ $item->medicine?->name ?: $item->label() }}</span>
                                    <div class="rx-when-edit">
                                        <select class="form-select form-select-sm" name="medicine_id" data-rx-field data-rx-medicine-id data-rx-medicine-select autocomplete="off">
                                            <option value="">Cari obat…</option>
                                            @foreach ($medicines as $medicine)
                                                <option value="{{ $medicine->id }}" @selected((int) $item->medicine_id === (int) $medicine->id)>{{ $medicine->name }}{{ ($strength = $medicine->strengthLabel()) ? ' · '.$strength : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <div class="fw-semibold">{{ $item->medicine?->name ?: $item->label() }}</div>
                                    @if ($item->medicine?->kfa_code)
                                        <div class="rx-meta">KFA {{ $item->medicine->kfa_code }}</div>
                                    @endif
                                    <input type="hidden" name="medicine_id" value="{{ $item->medicine_id }}">
                                @endif
                                <input type="hidden" name="medication_type" value="{{ $item->medication_type?->value }}">
                            </td>
                            <td class="text-nowrap" data-rx-strength>{{ $item->medicine?->strengthLabel() ?: '—' }}</td>
                            <td style="min-width:6rem">
                                @if ($rowEditable)
                                    <span class="rx-when-view">{{ $item->dosage ?: '—' }}</span>
                                    <div class="rx-when-edit {{ $isCompound ? 'rx-sig-field' : '' }}">
                                        <input class="form-control form-control-sm" name="dosage" value="{{ $item->dosage }}" data-rx-field autocomplete="off" @if (! $isCompound) readonly title="Dosis mengikuti kekuatan obat" @endif>
                                        @if ($isCompound)
                                            @include('clinical.care.partials.prescription-sig', [
                                                'group' => 'forms',
                                                'name' => 'dosage',
                                                'compact' => true,
                                            ])
                                        @endif
                                    </div>
                                @else
                                    {{ $item->dosage ?: '—' }}
                                @endif
                            </td>
                            <td class="text-center" data-rx-stock>
                                @if ($stock === null)
                                    —
                                @else
                                    <span class="badge {{ $stock > 0 ? 'bg-success' : 'bg-danger' }}">{{ $stock }}</span>
                                @endif
                            </td>
                            <td style="min-width:6rem">
                                @if ($rowEditable)
                                    <span class="rx-when-view">{{ $isCompound ? ($item->jenis_racikan ?: '—') : ($item->unit ? ($unitOptions[$item->unit] ?? $item->unit) : ($item->medicine?->unitLabel() ?: '—')) }}</span>
                                    <div class="rx-when-edit">
                                        @if ($isCompound)
                                            @php
                                                $jenisOptions = config('pharmacy.jenis_racikan', []);
                                                $jenisCurrent = (string) $item->jenis_racikan;
                                            @endphp
                                            <select class="form-select form-select-sm" name="jenis_racikan" data-rx-field autocomplete="off">
                                                <option value="">—</option>
                                                @foreach ($jenisOptions as $jenis)
                                                    <option value="{{ $jenis }}" @selected($jenisCurrent === $jenis)>{{ $jenis }}</option>
                                                @endforeach
                                                @if ($jenisCurrent !== '' && ! in_array($jenisCurrent, $jenisOptions, true))
                                                    <option value="{{ $jenisCurrent }}" selected>{{ $jenisCurrent }}</option>
                                                @endif
                                            </select>
                                            <input type="hidden" name="unit" value="{{ $item->unit ?: 'bungkus' }}">
                                        @else
                                            <input type="text" class="form-control form-control-sm" data-rx-unit-view value="{{ $item->unit ? ($unitOptions[$item->unit] ?? $item->unit) : ($item->medicine?->unitLabel() ?: '') }}" readonly title="Satuan mengikuti master obat">
                                            <input type="hidden" name="unit" value="{{ $item->unit ?: $item->medicine?->unit }}">
                                        @endif
                                    </div>
                                @else
                                    {{ $isCompound ? ($item->jenis_racikan ?: '—') : ($item->unit ? ($unitOptions[$item->unit] ?? $item->unit) : ($item->medicine?->unitLabel() ?: '—')) }}
                                @endif
                            </td>
                            <td>
                                @if ($rowEditable)
                                    <span class="rx-when-view">{{ $item->signaLine() ?: ($item->frequency ?: '—') }}</span>
                                    <div class="rx-when-edit rx-sig-field">
                                        @include('clinical.care.partials.prescription-signa-slots', ['values' => [
                                            'signa_p' => $item->signa_p,
                                            'signa_s' => $item->signa_s,
                                            'signa_sr' => $item->signa_sr,
                                            'signa_m' => $item->signa_m,
                                        ]])
                                        <input type="hidden" name="frequency" value="{{ $item->frequency }}" data-rx-frequency>
                                        @include('clinical.care.partials.prescription-sig', [
                                            'group' => 'frequencies',
                                            'name' => 'frequency',
                                            'mode' => 'signa',
                                            'compact' => true,
                                        ])
                                    </div>
                                @else
                                    {{ $item->signaLine() ?: ($item->frequency ?: '—') }}
                                @endif
                            </td>
                            <td style="min-width:5rem">
                                @if ($rowEditable)
                                    <span class="rx-when-view">{{ $item->waktu ?: '—' }}</span>
                                    <div class="rx-when-edit">
                                        @include('clinical.care.partials.prescription-waktu-select', ['value' => $item->waktu, 'name' => 'waktu'])
                                    </div>
                                @else
                                    {{ $item->waktu ?: '—' }}
                                @endif
                            </td>
                            <td style="min-width:3.5rem">
                                @if ($rowEditable)
                                    <span class="rx-when-view">{{ $item->jho ?: '—' }}</span>
                                    <div class="rx-when-edit rx-sig-field">
                                        <input class="form-control form-control-sm" name="jho" type="number" min="1" value="{{ $item->jho }}" title="Jumlah hari obat" data-rx-field autocomplete="off">
                                        @include('clinical.care.partials.prescription-sig', [
                                            'group' => 'courses',
                                            'name' => 'jho',
                                            'compact' => true,
                                        ])
                                    </div>
                                @else
                                    {{ $item->jho ?: '—' }}
                                @endif
                            </td>
                            <td style="min-width:3.5rem">
                                @if ($rowEditable)
                                    <span class="rx-when-view">{{ $item->quantity }}</span>
                                    <div class="rx-when-edit">
                                        <input class="form-control form-control-sm" name="quantity" type="number" min="1" value="{{ $item->quantity }}" data-rx-field data-rx-qty @if ($item->quantity_manual) data-manual="1" @endif>
                                        <input type="hidden" name="quantity_manual" value="{{ $item->quantity_manual ? 1 : 0 }}">
                                        <div class="rx-qty-hint" data-rx-qty-hint></div>
                                    </div>
                                @else
                                    {{ $item->quantity }}
                                @endif
                            </td>
                            <td style="min-width:7rem">
                                @if ($rowEditable)
                                    <span class="rx-when-view">{{ $item->notes ?: ($item->duration ?: '—') }}</span>
                                    <div class="rx-when-edit">
                                        <input class="form-control form-control-sm" name="notes" value="{{ $item->notes }}" data-rx-field>
                                        <input type="hidden" name="duration" value="{{ $item->duration }}">
                                        <input type="hidden" name="course" value="{{ $item->course }}">
                                    </div>
                                @else
                                    {{ $item->notes ?: ($item->duration ?: '—') }}
                                @endif
                            </td>
                            @php
                                $linePrice = $isCompound ? (float) $item->compoundCharge() : (float) ($item->unit_price ?? 0);
                            @endphp
                            <td class="rx-col-money text-end text-nowrap" data-rx-price data-raw="{{ $linePrice }}">{{ number_format($linePrice, 2, ',', '.') }}</td>
                            <td class="rx-col-money text-end text-nowrap" data-rx-subtotal>{{ number_format($item->subtotal(), 2, ',', '.') }}</td>
                            <td class="text-center text-nowrap">
                                @if ($rowEditable)
                                    <button type="button" class="btn btn-sm btn-light border rx-when-view" data-rx-edit title="Edit" aria-label="Edit">
                                        <i class="bx bx-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary rx-when-edit" data-rx-save title="Simpan" aria-label="Simpan">
                                        <i class="bx bx-save"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light border rx-when-edit" data-rx-cancel-edit title="Batal" aria-label="Batal">
                                        <i class="bx bx-x"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" data-rx-delete title="Hapus" aria-label="Hapus">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @if ($isCompound)
                            @foreach ($item->ingredients as $ingredient)
                                @php
                                    $ingredientPrice = (float) ($ingredient->medicine?->sell_price ?? $ingredient->medicine?->base_price ?? 0);
                                @endphp
                                @include('clinical.care.partials.prescription-component-row', [
                                    'row' => [
                                        'medicine_id' => $ingredient->medicine_id,
                                        'name' => $ingredient->name,
                                        'unit' => $ingredient->unit,
                                        'role' => $ingredient->role ?: 'active',
                                        'additive_kind' => $ingredient->additive_kind,
                                        'quantity' => $ingredient->quantity,
                                        'dose_amount' => $ingredient->dose_amount,
                                        'per_dose_quantity' => $ingredient->per_dose_quantity,
                                        'strength_value' => $ingredient->strength_value,
                                        'strength_unit' => $ingredient->strength_unit,
                                        'price' => $ingredientPrice,
                                        'subtotal' => $ingredientPrice * (int) $ingredient->quantity,
                                    ],
                                    'medicines' => $medicines,
                                    'parentKey' => $item->id,
                                ])
                            @endforeach
                        @endif
                    @endforeach
                @empty
                    <tr data-rx-empty>
                        <td colspan="14" class="text-muted text-center py-2">Belum ada item resep.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canEditDraft)
        <div class="rx-editor-actions">
            <button type="button" class="btn btn-sm btn-primary" data-rx-add-ready>+ Non Racik</button>
            <button type="button" class="btn btn-sm btn-secondary" data-rx-add-compound>+ Racik</button>
            <button type="button" class="btn btn-sm btn-success ms-auto" data-rx-save-all>
                <i class="bx bx-save me-1"></i>Simpan Resep
            </button>
        </div>

        <template id="rx-draft-ready-template">
            <tr class="rx-row rx-row--draft" data-rx-draft data-kind="ready">
                <td class="text-center fw-semibold">R/</td>
                <td>
                    <select class="form-select form-select-sm" name="medicine_id" data-rx-medicine-id data-rx-medicine-select autocomplete="off">
                        <option value="">Cari obat…</option>
                        @foreach ($medicines as $medicine)
                            <option value="{{ $medicine->id }}">{{ $medicine->catalogLabel() }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="medication_type" value="NC">
                </td>
                <td data-rx-strength>—</td>
                <td>
                    <input class="form-control form-control-sm" name="dosage" data-rx-field placeholder="otomatis dari kekuatan" readonly autocomplete="off">
                </td>
                <td class="text-center" data-rx-stock>—</td>
                <td>
                    <input type="text" class="form-control form-control-sm" data-rx-unit-view readonly aria-label="Satuan">
                    <input type="hidden" name="unit" data-rx-unit>
                </td>
                <td>
                    <div class="rx-sig-field">
                        @include('clinical.care.partials.prescription-signa-slots', ['values' => []])
                        <input type="hidden" name="frequency" data-rx-frequency>
                        @include('clinical.care.partials.prescription-sig', ['group' => 'frequencies', 'name' => 'frequency', 'mode' => 'signa', 'compact' => true])
                    </div>
                </td>
                <td>
                    @include('clinical.care.partials.prescription-waktu-select', ['value' => null, 'name' => 'waktu'])
                </td>
                <td>
                    <div class="rx-sig-field">
                        <input class="form-control form-control-sm" name="jho" type="number" min="1" title="Jumlah hari obat" data-rx-field autocomplete="off">
                        @include('clinical.care.partials.prescription-sig', ['group' => 'courses', 'name' => 'jho', 'compact' => true])
                    </div>
                </td>
                <td>
                    <input class="form-control form-control-sm" name="quantity" type="number" min="1" value="1" data-rx-field data-rx-qty>
                    <input type="hidden" name="quantity_manual" value="0">
                    <div class="rx-qty-hint" data-rx-qty-hint></div>
                </td>
                <td>
                    <input class="form-control form-control-sm" name="notes" data-rx-field placeholder="Keterangan">
                </td>
                <td class="rx-col-money text-end" data-rx-price>0,00</td>
                <td class="rx-col-money text-end" data-rx-subtotal>0,00</td>
                <td class="text-center text-nowrap">
                    <button type="button" class="btn btn-sm btn-primary" data-rx-save title="Simpan" aria-label="Simpan"><i class="bx bx-save"></i></button>
                    <button type="button" class="btn btn-sm btn-danger" data-rx-draft-cancel title="Batal" aria-label="Batal"><i class="bx bx-x"></i></button>
                </td>
            </tr>
        </template>

        <template id="rx-draft-compound-template">
            <tr class="rx-row rx-row--draft rx-row--compound" data-rx-draft data-kind="racikan">
                <td class="text-center fw-semibold">R/</td>
                <td>
                    <div class="fw-semibold mb-1">RACIK<span data-rx-racik-label></span></div>
                    <input type="hidden" name="kind" value="racikan">
                    <input type="hidden" name="medication_type" value="EP">
                    <input type="text" class="form-control form-control-sm mb-1" name="name" placeholder="Nama racikan" maxlength="80" data-rx-field autocomplete="off">
                    <button type="button" class="btn btn-sm btn-light border" data-rx-add-ingredient>+ Komponen Racik</button>
                </td>
                <td>—</td>
                <td>
                    <div class="rx-sig-field">
                        <input class="form-control form-control-sm" name="dosage" data-rx-field placeholder="bentuk sediaan" autocomplete="off">
                        @include('clinical.care.partials.prescription-sig', ['group' => 'forms', 'name' => 'dosage', 'compact' => true])
                    </div>
                </td>
                <td class="text-center">—</td>
                <td>
                    <select class="form-select form-select-sm" name="jenis_racikan" data-rx-field autocomplete="off">
                        <option value="">—</option>
                        @foreach (config('pharmacy.jenis_racikan', []) as $jenis)
                            <option value="{{ $jenis }}">{{ $jenis }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="unit" value="bungkus">
                </td>
                <td>
                    <div class="rx-sig-field">
                        @include('clinical.care.partials.prescription-signa-slots', ['values' => []])
                        <input type="hidden" name="frequency" data-rx-frequency>
                        @include('clinical.care.partials.prescription-sig', ['group' => 'frequencies', 'name' => 'frequency', 'mode' => 'signa', 'compact' => true])
                    </div>
                </td>
                <td>
                    @include('clinical.care.partials.prescription-waktu-select', ['value' => null, 'name' => 'waktu'])
                </td>
                <td>
                    <div class="rx-sig-field">
                        <input class="form-control form-control-sm" name="jho" type="number" min="1" title="Jumlah hari obat" data-rx-field autocomplete="off">
                        @include('clinical.care.partials.prescription-sig', ['group' => 'courses', 'name' => 'jho', 'compact' => true])
                    </div>
                </td>
                <td>
                    <input class="form-control form-control-sm" name="quantity" type="number" min="1" value="1" data-rx-field data-rx-qty>
                    <input type="hidden" name="quantity_manual" value="0">
                    <div class="rx-qty-hint" data-rx-qty-hint></div>
                </td>
                <td>
                    <input class="form-control form-control-sm" name="notes" data-rx-field placeholder="Instruksi racikan">
                </td>
                <td class="rx-col-money text-end" data-rx-price>0,00</td>
                <td class="rx-col-money text-end" data-rx-subtotal>0,00</td>
                <td class="text-center text-nowrap">
                    <button type="button" class="btn btn-sm btn-primary" data-rx-save title="Simpan" aria-label="Simpan"><i class="bx bx-save"></i></button>
                    <button type="button" class="btn btn-sm btn-danger" data-rx-draft-cancel title="Batal" aria-label="Batal"><i class="bx bx-x"></i></button>
                </td>
            </tr>
        </template>

        <template id="rx-draft-ingredient-template">
            @include('clinical.care.partials.prescription-component-row', [
                'row' => ['role' => 'active', 'quantity' => 1],
                'medicines' => $medicines,
                'componentDraft' => true,
            ])
        </template>
    @endif

    <div class="rx-editor-footer">
        <div class="rx-estimate">
            Estimasi Biaya:
            <strong data-rx-estimate>Rp {{ number_format($estimate, 2, ',', '.') }}</strong>
        </div>
    </div>

    @can('prescription.create')
        @if ($canEditDraft && $draft && $draft->isDraft() && $draft->items->isNotEmpty())
            @php
                $prescription = $draft;
                $saveFormKey = 'template-from-'.$prescription->id;
                $reopenSave = old('_form') === $saveFormKey;
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
                $saveSources = $reopenSave
                    ? collect(old('items', []))->values()
                    : $prescription->items->values();
            @endphp
            <x-modal id="rx-save-template-{{ $prescription->id }}" title="Simpan sebagai template" size="modal-xl" class="care-modal" :data-show-on-load="$reopenSave ? '' : null">
                <p class="form-text mb-2">Beri nama template dari isi resep draft ini.</p>
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
                    <div class="text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bx bx-save me-1"></i>Simpan</button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endcan
</div>
