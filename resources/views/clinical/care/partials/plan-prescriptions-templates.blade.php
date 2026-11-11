@forelse ($prescriptionTemplates as $template)
    <div class="rx-card">
        <div class="rx-card__head">
            <div>
                <strong>{{ $template->name }}</strong>
                <div class="rx-meta">{{ $template->author?->name ?: 'Pengguna' }} · {{ $template->items->count() }} item</div>
            </div>
            <div class="rx-actions">
                <form action="{{ route('care.prescription-templates.apply', [$visit, $template]) }}" method="POST" data-rx-ajax>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary" title="Terapkan" aria-label="Terapkan template">
                        <i class="bx bx-check me-1"></i>Terapkan
                    </button>
                </form>
                @if ($template->canBeManagedBy(auth()->user()))
                    <form action="{{ route('care.prescription-templates.destroy', [$visit, $template]) }}" method="POST" data-rx-ajax data-rx-confirm="Hapus template ini?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-soft-danger rx-icon-btn" title="Hapus template" aria-label="Hapus template">
                            <i class="bx bx-trash"></i>
                        </button>
                    </form>
                @endif
            </div>
        </div>
        <ul class="rx-template-list">
            @foreach ($template->items as $templateItem)
                <li>
                    @if ($templateItem->isCompound())
                        Racikan ·
                    @endif
                    {{ $templateItem->label() }} · {{ $templateItem->dosage ?: '-' }} · {{ $templateItem->frequency ?: '-' }} · {{ $templateItem->duration ?: '-' }} · {{ $templateItem->quantity }}{{ $templateItem->isCompound() ? ' bks' : '' }}
                </li>
            @endforeach
        </ul>
        @if ($template->canBeManagedBy(auth()->user()))
            <details class="mt-2">
                <summary class="small">Ubah template</summary>
                <form action="{{ route('care.prescription-templates.update', [$visit, $template]) }}" method="POST" class="mt-2 rx-form" data-template-editor data-rx-ajax>
                    @csrf
                    @method('PUT')
                    <x-input name="name" label="Nama template" :value="$template->name" required />
                    <div data-template-rows>
                        @foreach ($template->items as $index => $templateItem)
                            @if ($templateItem->isCompound())
                                @include('clinical.care.partials.prescription-template-compound-row', [
                                    'index' => $index,
                                    'prefix' => 'tpl-cmp-'.$template->id.'-'.$index,
                                    'row' => [
                                        'medication_type' => $templateItem->medication_type?->value,
                                        'dosage' => $templateItem->dosage,
                                        'frequency' => $templateItem->frequency,
                                        'duration' => $templateItem->duration,
                                        'course' => $templateItem->course,
                                        'quantity' => $templateItem->quantity,
                                        'ingredients' => $templateItem->ingredients->map(fn ($ingredient) => $ingredient->only(['name', 'medicine_id', 'unit', 'role', 'additive_kind', 'quantity', 'per_dose_quantity', 'strength_value', 'strength_unit']))->all(),
                                    ],
                                    'medicines' => $medicines,
                                ])
                            @else
                                @include('clinical.care.partials.prescription-template-row', [
                                    'index' => $index,
                                    'prefix' => 'tpl-'.$template->id.'-'.$index,
                                    'row' => [
                                        'medicine_id' => $templateItem->medicine_id,
                                        'dosage' => $templateItem->dosage,
                                        'frequency' => $templateItem->frequency,
                                        'duration' => $templateItem->duration,
                                        'course' => $templateItem->course,
                                        'unit' => $templateItem->unit,
                                        'quantity' => $templateItem->quantity,
                                    ],
                                    'medicines' => $medicines,
                                ])
                            @endif
                        @endforeach
                    </div>
                    <template data-template-row-template>
                        @include('clinical.care.partials.prescription-template-row', [
                            'index' => '__i__',
                            'prefix' => 'tpl-'.$template->id.'-__i__',
                            'row' => ['quantity' => 1],
                            'medicines' => $medicines,
                        ])
                    </template>
                    <template data-template-compound-template>
                        @include('clinical.care.partials.prescription-template-compound-row', [
                            'index' => '__i__',
                            'prefix' => 'tpl-cmp-'.$template->id.'-__i__',
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
                        <button type="submit" class="btn btn-sm btn-primary" title="Simpan template" aria-label="Simpan perubahan template">
                            <i class="bx bx-save me-1"></i>Simpan
                        </button>
                    </div>
                </form>
            </details>
        @endif
    </div>
@empty
    <p class="text-muted mb-0">Belum ada template. Klik <strong>Tambah baru</strong> untuk membuat.</p>
@endforelse
