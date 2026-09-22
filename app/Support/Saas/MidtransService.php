<?php

namespace App\Support\Saas;

use App\Enums\SaasInvoiceStatus;
use App\Models\SaasInvoice;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MidtransService
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    public function isReady(): bool
    {
        return PaymentGatewaySettings::isReady();
    }

    public function createSnapToken(SaasInvoice $invoice, User $user): string
    {
        if (! $this->isReady()) {
            throw new MidtransException('Payment gateway Midtrans belum dikonfigurasi.');
        }

        $orderId = $invoice->number.'-'.now()->format('His');
        $amount = (int) round((float) $invoice->amount);
        $packageName = $invoice->subscription?->package?->name ?? 'Paket langganan';

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'item_details' => [[
                'id' => 'pkg-'.($invoice->subscription?->package_id ?? $invoice->id),
                'price' => $amount,
                'quantity' => 1,
                'name' => mb_substr($packageName, 0, 50),
            ]],
            'customer_details' => [
                'first_name' => mb_substr($user->name, 0, 50),
                'email' => $user->email,
            ],
            'callbacks' => [
                'finish' => route('subscription.invoices.finish'),
            ],
        ];

        $response = Http::withBasicAuth(PaymentGatewaySettings::serverKey(), '')
            ->acceptJson()
            ->timeout(20)
            ->post(PaymentGatewaySettings::snapBaseUrl().'/snap/v1/transactions', $payload);

        if (! $response->successful() || ! $response->json('token')) {
            throw new MidtransException($this->errorMessage($response, 'Gagal membuat transaksi Midtrans.'));
        }

        $invoice->update([
            'payment_channel' => 'midtrans',
            'midtrans_order_id' => $orderId,
            'midtrans_snap_token' => $response->json('token'),
            'midtrans_status' => 'pending',
            'payment_payload' => $payload,
        ]);

        return (string) $response->json('token');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleNotification(array $payload): ?SaasInvoice
    {
        $this->assertValidSignature($payload);

        $orderId = (string) ($payload['order_id'] ?? '');
        $invoice = $this->findInvoice($orderId);

        if (! $invoice) {
            return null;
        }

        return $this->applyStatus($invoice, $payload);
    }

    public function syncByOrderId(string $orderId, ?int $tenantId = null): ?SaasInvoice
    {
        if ($orderId === '' || ! $this->isReady()) {
            return null;
        }

        $invoice = $this->findInvoice($orderId);

        if (! $invoice || ($tenantId && (int) $invoice->tenant_id !== $tenantId)) {
            return null;
        }

        $response = Http::withBasicAuth(PaymentGatewaySettings::serverKey(), '')
            ->acceptJson()
            ->timeout(20)
            ->get(PaymentGatewaySettings::apiBaseUrl().'/v2/'.$orderId.'/status');

        if (! $response->successful()) {
            return $invoice;
        }

        $payload = $response->json() ?? [];

        if (! $this->signatureMatches($payload)) {
            return $invoice;
        }

        return $this->applyStatus($invoice, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyStatus(SaasInvoice $invoice, array $payload): SaasInvoice
    {
        $status = (string) ($payload['transaction_status'] ?? '');
        $fraud = (string) ($payload['fraud_status'] ?? 'accept');

        $invoice->update([
            'payment_channel' => 'midtrans',
            'midtrans_order_id' => (string) ($payload['order_id'] ?? $invoice->midtrans_order_id),
            'midtrans_transaction_id' => $payload['transaction_id'] ?? $invoice->midtrans_transaction_id,
            'midtrans_status' => $status !== '' ? $status : $invoice->midtrans_status,
            'payment_payload' => $payload,
        ]);

        $paid = $status === 'settlement' || ($status === 'capture' && $fraud !== 'challenge');

        if ($paid && $invoice->status !== SaasInvoiceStatus::Paid) {
            return $this->subscriptions->markPaid($invoice->fresh(), [
                'channel' => 'midtrans',
                'transaction_id' => $payload['transaction_id'] ?? null,
                'status' => $status,
                'payload' => $payload,
            ]);
        }

        return $invoice->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertValidSignature(array $payload): void
    {
        if (! $this->signatureMatches($payload)) {
            throw new MidtransException('Signature Midtrans tidak valid.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signatureMatches(array $payload): bool
    {
        $expected = $this->signatureFor($payload);
        $given = (string) ($payload['signature_key'] ?? '');

        return $expected !== '' && $given !== '' && hash_equals($expected, $given);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signatureFor(array $payload): string
    {
        $serverKey = PaymentGatewaySettings::serverKey();

        if ($serverKey === '') {
            return '';
        }

        return hash('sha512', ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$serverKey);
    }

    protected function findInvoice(string $orderId): ?SaasInvoice
    {
        if ($orderId === '') {
            return null;
        }

        return SaasInvoice::query()
            ->where(function ($query) use ($orderId) {
                $query->where('midtrans_order_id', $orderId)
                    ->orWhere('number', $orderId);
            })
            ->first();
    }

    protected function errorMessage(Response $response, string $fallback): string
    {
        $message = $response->json('error_messages.0')
            ?? $response->json('status_message')
            ?? $fallback;

        return is_string($message) ? $message : $fallback;
    }
}
