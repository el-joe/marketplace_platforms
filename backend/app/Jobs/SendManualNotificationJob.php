<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\Customer;
use App\Services\Customer\CustomerFCMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendManualNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 1000;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $target,
        public readonly ?string $countryId,
        public readonly array $customerIds,
        public readonly string $titleEn,
        public readonly string $titleAr,
        public readonly string $bodyEn,
        public readonly string $bodyAr,
        public readonly array $channels,
        public readonly string $adminId,
    ) {}

    public function handle(CustomerFCMService $fcmService): void
    {
        $query = Customer::query()->select(['id']);

        match ($this->target) {
            'country' => $query->where('country_id', $this->countryId),
            'specific' => $query->whereIn('id', $this->customerIds),
            default => null,
        };

        $sendDatabase = in_array('database', $this->channels, true);
        $sendPush = in_array('push', $this->channels, true);

        $query->chunkById(self::CHUNK_SIZE, function ($customers) use ($sendDatabase, $sendPush, $fcmService) {
            $now = now();

            if ($sendDatabase) {
                $rows = $customers->map(fn (Customer $customer) => [
                    'id' => (string) Str::uuid(),
                    'type' => 'admin_broadcast',
                    'notifiable_type' => Customer::class,
                    'notifiable_id' => $customer->id,
                    'channel' => 'database',
                    'data' => json_encode([
                        'title' => $this->titleEn,
                        'title_ar' => $this->titleAr,
                        'message' => $this->bodyEn,
                        'message_ar' => $this->bodyAr,
                        'admin_id' => $this->adminId,
                    ]),
                    'sent_at' => $now,
                    'created_at' => $now,
                ])->all();

                DB::table('notifications')->insert($rows);
            }

            if ($sendPush) {
                foreach ($customers as $customer) {
                    $fcmService->sendToCustomer(
                        $customer->id,
                        $this->titleEn,
                        $this->bodyEn,
                        ['type' => 'admin_broadcast']
                    );
                }
            }
        }, 'id');

        Log::info('Manual customer notification broadcast completed', [
            'admin_id' => $this->adminId,
            'target' => $this->target,
        ]);
    }
}
