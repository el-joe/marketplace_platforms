<?php

namespace App\Services\Shipping\Carriers;

use App\Contracts\ShippingCarrierInterface;
use App\DTOs\ShippingLabel;
use App\DTOs\TrackingResult;
use App\Models\Shipment;
use App\Models\ShippingCarrier;
use App\Models\SubOrder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class BostaCarrier implements ShippingCarrierInterface
{
    private ?string $apiKey;
    private ?string $baseUrl;

    public function __construct()
    {
        $dbCarrier = ShippingCarrier::where('code', 'bosta')->first();
        $creds = [];
        if ($dbCarrier?->credentials_encrypted) {
            $creds = json_decode(Crypt::decryptString($dbCarrier->credentials_encrypted), true) ?? [];
        }

        $this->apiKey = $creds['api_key'] ?? config('services.bosta.api_key', '');
        $this->baseUrl = config('services.bosta.base_url', 'https://app.bosta.co/api/v2');
    }

    public function getCode(): string
    {
        return 'bosta';
    }
    public function getName(): string
    {
        return 'Bosta';
    }
    public function supportsCod(): bool
    {
        return true;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['X-Bosta-Api-Key' => $this->apiKey])
            ->acceptJson()
            ->timeout(30);
    }

    public function createShipment(SubOrder $subOrder, array $options = []): ShippingLabel
    {
        try {
            $address = json_decode($subOrder->order->shipping_address_snapshot ?? '{}', true) ?? [];
            $response = $this->http()->post($this->baseUrl . '/deliveries', [
                'type' => 20, // Forward delivery
                'cod' => ($options['cod_amount'] ?? 0) > 0 ? $options['cod_amount'] / 100 : 0,
                'specs' => [
                    'packageDetails' => [
                        'itemsCount' => 1,
                        'weight' => ($options['weight_grams'] ?? 500) / 1000,
                    ],
                ],
                'dropOffAddress' => [
                    'city' => $address['city'] ?? '',
                    'firstLine' => $address['street_address'] ?? '',
                ],
                'receiver' => [
                    'firstName' => $address['recipient_name'] ?? '',
                    'phone' => $address['recipient_phone'] ?? '',
                ],
            ]);

            $body = $response->json();

            if ($response->successful()) {
                return new ShippingLabel(
                    success: true,
                    trackingNumber: $body['trackingNumber'] ?? '',
                    awbLabelUrl: $body['labelUrl'] ?? '',
                    awbLabelBase64: null,
                    shippingCost: (int) (($body['cod'] ?? 0) * 100),
                    currency: 'EGP',
                    carrierReferenceId: $body['_id'] ?? null,
                    errorMessage: null,
                    rawResponse: $body,
                );
            }

            return ShippingLabel::failure($body['message'] ?? 'Bosta API error', $body);
        } catch (\Exception $e) {
            return ShippingLabel::failure($e->getMessage());
        }
    }

    public function getTracking(string $trackingNumber): TrackingResult
    {
        try {
            $response = $this->http()->get($this->baseUrl . '/deliveries/' . $trackingNumber);
            $body = $response->json();

            if (!$response->successful()) {
                return TrackingResult::notFound($trackingNumber, $body);
            }

            $events = array_map(fn($event) => [
                'status' => $event['state']['code'] ?? '',
                'description' => $event['state']['value'] ?? '',
                'location' => $event['hub']['name'] ?? 'Unknown',
                'occurred_at' => $event['timestamp'] ?? '',
            ], $body['TransitEvents'] ?? []);

            return new TrackingResult(
                found: true,
                trackingNumber: $trackingNumber,
                currentStatus: $body['state']['value'] ?? 'unknown',
                events: $events,
                estimatedDelivery: null,
                rawResponse: $body,
            );
        } catch (\Exception $e) {
            return TrackingResult::error($trackingNumber);
        }
    }

    public function cancelShipment(Shipment $shipment): bool
    {
        try {
            $response = $this->http()->delete($this->baseUrl . '/deliveries/' . $shipment->carrier_reference_id);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function calculateRate(array $from, array $to, int $weightGrams, array $dimensions = []): array
    {
        // Bosta does not expose a public rate-calculator endpoint
        return [
            'rate' => 0,
            'currency' => 'EGP',
            'estimated_days' => 2,
            'service_name' => 'Bosta Standard',
        ];
    }

    public function testConnection(): array
    {
        try {
            $start = microtime(true);
            $response = $this->http()->get($this->baseUrl . '/deliveries?limit=1');
            $latency = round((microtime(true) - $start) * 1000);

            return [
                'success' => $response->successful(),
                'latency_ms' => $latency,
                'message' => $response->successful()
                    ? 'Connected (' . $latency . 'ms)'
                    : 'Authentication failed (HTTP ' . $response->status() . ')',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'latency_ms' => 0, 'message' => $e->getMessage()];
        }
    }
}
