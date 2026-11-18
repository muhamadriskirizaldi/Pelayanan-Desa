<?php

namespace App\Jobs;

use App\Models\SktmApplication;
use App\Models\User;
use App\Services\FonteWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan maksimal jika job gagal.
     */
    public int $tries = 2;

    /**
     * Timeout job dalam detik.
     */
    public int $timeout = 25;

    public function __construct(
        public string $recipientName,
        public string $recipientPhone,
        public string $notificationType,
        public string $message,
        public ?string $applicationNumber = null,
        public ?User $user = null,
        public ?SktmApplication $sktm = null
    ) {}

    /**
     * Jalankan job pengiriman WhatsApp.
     */
    public function handle(FonteWhatsAppService $service): void
    {
        $service->send(
            recipientName: $this->recipientName,
            recipientPhone: $this->recipientPhone,
            notificationType: $this->notificationType,
            message: $this->message,
            applicationNumber: $this->applicationNumber,
            user: $this->user,
            sktm: $this->sktm
        );
    }
}

