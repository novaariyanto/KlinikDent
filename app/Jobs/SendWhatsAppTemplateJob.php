<?php

namespace App\Jobs;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessageLog;
use App\Support\WhatsApp\WhatsAppException;
use App\Support\WhatsApp\WhatsAppTemplateService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppTemplateJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 60, 120, 300];

    public int $uniqueFor = 600;

    public function __construct(public int $logId) {}

    public function uniqueId(): string
    {
        return 'whatsapp-log-'.$this->logId;
    }

    public function handle(WhatsAppTemplateService $templates): void
    {
        $log = WhatsAppMessageLog::query()->withoutGlobalScopes()->find($this->logId);

        if (! $log || $log->status->isSuccess()) {
            return;
        }

        if ($log->status === WhatsAppMessageStatus::Failed && $log->error_code && ! $this->retryableCode($log->error_code)) {
            return;
        }

        $templates->sendLog($log);
    }

    public function failed(?\Throwable $exception): void
    {
        if (! $exception instanceof WhatsAppException) {
            $log = WhatsAppMessageLog::query()->withoutGlobalScopes()->find($this->logId);
            $log?->update([
                'status' => WhatsAppMessageStatus::Failed,
                'error_code' => 'job_failed',
                'error_message' => $exception?->getMessage() ?: 'Pengiriman WhatsApp gagal.',
                'failed_at' => now(),
            ]);
        }
    }

    protected function retryableCode(string $code): bool
    {
        return in_array($code, ['timeout', '429', '500', '502', '503', '504'], true)
            || str_starts_with($code, '5');
    }
}
