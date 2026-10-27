<?php

namespace App\Support\Billing;

use App\Enums\InvoiceItemSource;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\ProcedureRecord;
use App\Models\User;

class CashierDesk
{
    public function __construct(protected BillingService $billing) {}

    /**
     * @return array{
     *     procedures: list<array<string, mixed>>,
     *     medicines: list<array<string, mixed>>,
     *     mismatches: list<array{level: string, text: string}>,
     *     summary: array<string, int>,
     *     confirmed_at: ?string,
     *     confirmed_by: ?string
     * }
     */
    public function review(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'items',
            'visit.doctor',
            'visit.procedureRecords.procedure',
            'visit.prescriptions.doctor',
            'visit.prescriptions.items.medicine',
            'visit.prescriptions.items.ingredients.medicine',
        ]);

        $confirmed = $this->confirmation($invoice);
        $procedureIds = $confirmed['procedures'];
        $medicineIds = $confirmed['medicines'];

        $items = $invoice->items;
        $procedureItems = $items->where('source_type', InvoiceItemSource::Procedure)->keyBy('source_id');
        $medicineItems = $items->where('source_type', InvoiceItemSource::Medicine)->keyBy('source_id');

        $records = $invoice->visit?->procedureRecords ?? collect();
        $prescriptions = $invoice->visit?->prescriptions ?? collect();
        $rxItems = $prescriptions->flatMap(fn (Prescription $prescription) => $prescription->items);
        $declined = $this->billing->declinedQuantities($rxItems->pluck('id')->map(fn ($id) => (int) $id)->all());

        $procedures = [];
        $medicines = [];
        $mismatches = [];

        foreach ($records as $record) {
            $row = $this->procedureRow($invoice, $record, $procedureItems->get($record->id), in_array((int) $record->id, $procedureIds, true));
            $procedures[] = $row;

            if ($row['match'] !== 'Sesuai' && $row['match'] !== 'Tidak berlaku') {
                $mismatches[] = ['level' => $row['match'], 'text' => $row['name'].': '.$row['note']];
            }
        }

        $seenMedicineIds = [];

        foreach ($prescriptions as $prescription) {
            foreach ($prescription->items as $item) {
                $seenMedicineIds[] = (int) $item->id;
                $row = $this->medicineRow(
                    $invoice,
                    $prescription,
                    $item,
                    $medicineItems->get($item->id),
                    (int) ($declined[$item->id] ?? 0),
                    in_array((int) $item->id, $medicineIds, true),
                );
                $medicines[] = $row;

                if ($row['match'] !== 'Sesuai' && $row['match'] !== 'Tidak berlaku') {
                    $mismatches[] = ['level' => $row['match'], 'text' => $row['name'].': '.$row['note']];
                }
            }
        }

        foreach ($items as $item) {
            if ($item->source_type === InvoiceItemSource::Procedure && ! $records->contains('id', $item->source_id)) {
                $mismatches[] = [
                    'level' => 'Tidak sesuai',
                    'text' => $item->description.': item tagihan tidak punya tindakan di rekam medis.',
                ];
            }

            if ($item->source_type === InvoiceItemSource::Medicine && ! in_array((int) $item->source_id, $seenMedicineIds, true)) {
                $mismatches[] = [
                    'level' => 'Tidak sesuai',
                    'text' => $item->description.': item tagihan tidak punya item resep.',
                ];
            }
        }

        return [
            'procedures' => $procedures,
            'medicines' => $medicines,
            'mismatches' => $mismatches,
            'summary' => [
                'procedure_count' => count($procedures),
                'procedure_confirmed' => count(array_filter($procedures, fn (array $row) => $row['confirmed'])),
                'medicine_count' => count($medicines),
                'medicine_confirmed' => count(array_filter($medicines, fn (array $row) => $row['confirmed'])),
                'medicine_declined' => count(array_filter($medicines, fn (array $row) => $row['declined'] > 0)),
                'mismatch_count' => count($mismatches),
            ],
            'confirmed_at' => $confirmed['at'],
            'confirmed_by' => $confirmed['by'],
        ];
    }

    /**
     * @return array{procedures: list<int>, medicines: list<int>, at: ?string, by: ?string}
     */
    public function confirmation(Invoice $invoice): array
    {
        $log = ActivityLog::query()
            ->where('subject_type', $invoice::class)
            ->where('subject_id', $invoice->id)
            ->where('event', 'cashier_confirmed')
            ->latest('id')
            ->first();

        if (! $log) {
            return ['procedures' => [], 'medicines' => [], 'at' => null, 'by' => null];
        }

        $log->loadMissing('user');

        return [
            'procedures' => array_map('intval', $log->properties['procedures'] ?? []),
            'medicines' => array_map('intval', $log->properties['medicines'] ?? []),
            'at' => $log->created_at?->format('d M Y H:i'),
            'by' => $log->user?->name,
        ];
    }

    /**
     * @param  list<int>  $procedureIds
     * @param  list<int>  $medicineIds
     */
    public function confirm(Invoice $invoice, User $user, array $procedureIds, array $medicineIds): void
    {
        $invoice->loadMissing([
            'visit.procedureRecords',
            'visit.prescriptions.items',
        ]);

        $allowedProcedures = $invoice->visit?->procedureRecords->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
        $allowedMedicines = $invoice->visit?->prescriptions
            ->flatMap(fn (Prescription $prescription) => $prescription->items)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all() ?? [];

        $procedureIds = array_values(array_intersect(array_map('intval', $procedureIds), $allowedProcedures));
        $medicineIds = array_values(array_intersect(array_map('intval', $medicineIds), $allowedMedicines));

        if ($procedureIds === [] && $medicineIds === []) {
            throw new BillingException('Pilih minimal satu tindakan atau obat untuk dikonfirmasi.');
        }

        $previous = $this->confirmation($invoice);

        activity_log('cashier_confirmed', $invoice, [
            'procedures' => array_values(array_unique([...$previous['procedures'], ...$procedureIds])),
            'medicines' => array_values(array_unique([...$previous['medicines'], ...$medicineIds])),
            'added_procedures' => $procedureIds,
            'added_medicines' => $medicineIds,
        ], 'Konfirmasi layanan kasir '.$invoice->number, 'billing', $user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function procedureRow(Invoice $invoice, ProcedureRecord $record, ?InvoiceItem $item, bool $confirmed): array
    {
        $name = $record->procedure?->name ?? 'Tindakan';

        if ($record->tooth_number) {
            $name .= ' gigi '.$record->tooth_number;
        }

        $match = 'Sesuai';
        $note = 'Jumlah dan harga sama dengan tagihan.';

        if ($invoice->isVoid()) {
            $match = 'Tidak berlaku';
            $note = 'Tagihan dibatalkan.';
        } elseif (! $item) {
            $match = 'Perlu pemeriksaan';
            $note = $record->isUnbilled()
                ? 'Tindakan tercatat tetapi belum masuk tagihan.'
                : 'Tindakan sudah berstatus ditagih, tetapi tidak ada di tagihan ini.';
        } elseif ((int) $item->quantity !== (int) $record->quantity) {
            $match = 'Tidak sesuai';
            $note = 'Jumlah tagihan '.$item->quantity.' berbeda dari rekam tindakan '.$record->quantity.'.';
        } elseif (bccomp((string) $item->unit_price, (string) $record->price_at_time, 2) !== 0) {
            $match = 'Tidak sesuai';
            $note = 'Harga tagihan berbeda dari harga saat tindakan dicatat.';
        }

        $price = (string) ($item->unit_price ?? $record->price_at_time);
        $qty = (int) $record->quantity;

        return [
            'id' => (int) $record->id,
            'name' => $name,
            'performer' => $invoice->visit?->doctor?->name ?: '—',
            'date' => $record->created_at?->format('d M Y H:i') ?: '—',
            'quantity' => $qty,
            'price' => $price,
            'total' => bcmul($price, (string) $qty, 2),
            'billing' => $item ? 'Ditagih' : ($record->isUnbilled() ? 'Belum ditagih' : 'Ditagih di tempat lain'),
            'perform_status' => 'Tercatat',
            'confirmed' => $confirmed,
            'confirm' => $confirmed ? 'Dikonfirmasi' : 'Belum dikonfirmasi',
            'match' => $match,
            'note' => $note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function medicineRow(
        Invoice $invoice,
        Prescription $prescription,
        PrescriptionItem $item,
        ?InvoiceItem $line,
        int $declined,
        bool $confirmed,
    ): array {
        $name = $item->isCompound() ? $item->label() : ($item->medicine?->name ?? 'Obat');
        $prescribed = $item->isCompound() ? 1 : (int) $item->quantity;
        $billed = $line ? (int) $line->quantity : 0;
        $unit = $line ? (string) $line->unit_price : $this->estimateUnitPrice($item);
        $fulfilled = $prescription->isFulfilled();
        $fullyDeclined = $declined >= $prescribed && $prescribed > 0;

        $handover = 'Belum diserahkan';

        if ($fullyDeclined || ($line && $billed === 0 && $declined > 0)) {
            $handover = 'Tidak jadi diambil';
        } elseif ($confirmed) {
            $handover = 'Diserahkan (dikonfirmasi kasir)';
        } elseif ($fulfilled) {
            $handover = 'Disiapkan farmasi, belum dikonfirmasi kasir';
        }

        $match = 'Sesuai';
        $note = 'Siap diperiksa kasir.';

        if ($invoice->isVoid()) {
            $match = 'Tidak berlaku';
            $note = 'Tagihan dibatalkan.';
        } elseif (! $fulfilled && ! $fullyDeclined) {
            $match = 'Perlu pemeriksaan';
            $note = 'Resep berstatus '.$prescription->status->label().'. Obat belum dihitung lunas sebagai penyerahan.';
        } elseif ($fulfilled && ! $line && ! $fullyDeclined) {
            $match = 'Perlu pemeriksaan';
            $note = 'Obat sudah dipenuhi farmasi tetapi belum masuk tagihan.';
        } elseif ($line && $fulfilled && ! $item->isCompound() && ($billed + $declined) !== $prescribed) {
            $match = 'Tidak sesuai';
            $note = 'Jumlah ditagih ('.$billed.') dan tidak diambil ('.$declined.') tidak sama dengan resep ('.$prescribed.').';
        } elseif ($line && ! $fulfilled && $billed > 0) {
            $match = 'Tidak sesuai';
            $note = 'Ditagihkan padahal resep belum dipenuhi farmasi.';
        } elseif ($fullyDeclined) {
            $match = 'Tidak berlaku';
            $note = 'Obat tidak jadi diambil dan tidak dihitung pada sisa tagihan.';
        }

        $canDecline = ! $invoice->isVoid() && ! $confirmed && ! $fullyDeclined && ($billed > 0 || ! $fulfilled);
        $declineMax = $item->isCompound() ? 1 : ($line && $billed > 0 ? $billed : max(0, $prescribed - $declined));

        if ($declineMax < 1) {
            $canDecline = false;
        }

        $block = null;

        if ($confirmed) {
            $block = 'Sudah dikonfirmasi diserahkan. Gunakan pengembalian dana, bukan pembatalan pengambilan.';
            $canDecline = false;
        } elseif ($invoice->isPaid()) {
            $block = 'Tagihan sudah lunas. Permintaan hanya dicatat; total tidak diubah. Gunakan pengembalian dana.';
        }

        return [
            'id' => (int) $item->id,
            'name' => $name,
            'form' => $item->isCompound()
                ? 'Racikan'
                : trim(collect([$item->medicine?->unit, $item->medicine?->strengthLabel()])->filter()->implode(' · ')),
            'prescribed' => $prescribed,
            'billed' => $billed,
            'declined' => $declined,
            'handed' => $confirmed ? $billed : 0,
            'sig' => collect([$item->dosage, $item->frequency, $item->duration])->filter()->implode(' · ') ?: '—',
            'rx_status' => $prescription->status->label(),
            'handover' => $handover,
            'billing' => $billed > 0 ? 'Ditagih' : ($fullyDeclined ? 'Dikeluarkan dari tagihan' : 'Belum ditagih'),
            'unit_price' => $unit,
            'total' => $billed > 0 ? bcmul($unit, (string) $billed, 2) : '0.00',
            'confirmed' => $confirmed,
            'confirm' => $confirmed ? 'Dikonfirmasi' : 'Belum dikonfirmasi',
            'match' => $match,
            'note' => $note,
            'can_decline' => $canDecline,
            'decline_max' => $declineMax,
            'compound' => $item->isCompound(),
            'paid_lock' => $invoice->isPaid(),
            'block' => $block,
            'doctor' => $prescription->doctor?->name ?: ($invoice->visit?->doctor?->name ?: '—'),
        ];
    }

    protected function estimateUnitPrice(PrescriptionItem $item): string
    {
        if ($item->isCompound()) {
            $price = '0.00';

            foreach ($item->ingredients as $ingredient) {
                $unit = (string) ($ingredient->medicine?->sell_price ?? $ingredient->medicine?->base_price ?? '0.00');
                $price = bcadd($price, bcmul($unit, (string) $ingredient->quantity, 2), 2);
            }

            return $price;
        }

        return (string) ($item->medicine?->sell_price ?? $item->medicine?->base_price ?? '0.00');
    }
}
