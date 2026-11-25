@php
    $racikIndex = $racikIndex ?? 0;
@endphp
<div class="recipe">
    @foreach ($items as $item)
        @php
            $isCompound = $item->isCompound();
            if ($isCompound) {
                $racikIndex++;
            }
            $signa = $item->signaLabel();
            $unitName = $item->unitName();
        @endphp
        <div class="recipe__item">
            <div class="recipe__head">
                <span class="recipe__r">R/</span>
                <span class="recipe__name">
                    @if ($isCompound)
                        RACIK-{{ str_pad((string) $racikIndex, 2, '0', STR_PAD_LEFT) }} ·
                        {{ $item->name ?: $item->label() }}
                        @if (filled($item->jenis_racikan))
                            <span class="recipe__meta">({{ $item->jenis_racikan }})</span>
                        @endif
                    @else
                        @php
                            $medicineName = $item->medicine?->name ?: $item->label();
                            $strengthLabel = $item->medicine?->strengthLabel();
                            $strengthInName = $strengthLabel !== null
                                && str_contains(
                                    preg_replace('/\s+/', '', strtolower($medicineName)),
                                    preg_replace('/\s+/', '', strtolower($strengthLabel))
                                );
                        @endphp
                        {{ $medicineName }}
                        @if ($strengthLabel && ! $strengthInName)
                            <span class="recipe__meta">{{ $strengthLabel }}</span>
                        @endif
                    @endif
                </span>
            </div>

            @if ($isCompound && $item->ingredients->isNotEmpty())
                <div class="recipe__components">
                    @foreach ($item->ingredients as $ingredient)
                        <div class="recipe__component">
                            <span class="recipe__no">{{ $loop->iteration }})</span>
                            <span>{{ $ingredient->name }}</span>
                            @php
                                $ingredientStrength = trim($ingredient->strength_value.' '.$ingredient->strength_unit);
                                $strengthInIngredientName = $ingredientStrength !== ''
                                    && str_contains(
                                        preg_replace('/\s+/', '', strtolower((string) $ingredient->name)),
                                        preg_replace('/\s+/', '', strtolower($ingredientStrength))
                                    );
                            @endphp
                            @if ($ingredientStrength !== '' && ! $strengthInIngredientName)
                                <span class="recipe__meta">{{ $ingredientStrength }}</span>
                            @endif
                            @if ($ingredient->doseLabel())
                                <span class="recipe__dose">— dosis {{ $ingredient->doseLabel() }}/bungkus</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="recipe__signa">
                <span class="recipe__s">S.</span>
                {{ $signa !== '' ? $signa : '—' }}
                @if (filled($item->waktu))
                    · {{ $item->waktu }}
                @endif
                @if ($item->jho)
                    · JHO {{ $item->jho }} hari
                @elseif (filled($item->duration))
                    · {{ $item->duration }}
                @endif
            </div>

            @if (filled($item->notes))
                <div class="recipe__note">Keterangan: {{ $item->notes }}</div>
            @endif

            @if ($isCompound)
                <div class="recipe__amount">Jumlah racikan: {{ $item->quantityLabel() }}</div>
            @else
                <div class="recipe__amount">Jumlah: {{ $item->quantityLabel() }}</div>
            @endif
        </div>
    @endforeach
</div>
