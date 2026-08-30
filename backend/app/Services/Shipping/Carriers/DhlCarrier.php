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

class DhlCarrier implements ShippingCarrierInterface
{
    private ?string $apiKey;
    private ?string $apiSecret;
    private ?string $baseUrl = 'https://express.api.dhl.com/mydhlapi';

    public function __construct()
    {
        $dbCarrier = ShippingCarrier::where('code', 'dhl')->first();
        $creds = [];
        if ($dbCarrier?->credentials_encrypted) {
            $creds = json_decode(Crypt::decryptString($dbCarrier->credentials_encrypted), true) ?? [];
        }

        $this->apiKey = $creds['api_key'] ?? config('services.dhl.api_key', '');
        $this->apiSecret = $creds['api_secret'] ?? config('services.dhl.api_secret', '');
    }

    public function getCode(): string
    {
        return 'dhl';
    }
    public function getName(): string
    {
        return 'DHL Express';
    }
    public function supportsCod(): bool
    {
        return false;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth($this->apiKey, $this->apiSecret)
            ->acceptJson()
            ->timeout(30);
    }

    public function createShipment(SubOrder $subOrder, array $options = []): ShippingLabel
    {
        try {
            $address = json_decode($subOrder->order->shipping_address_snapshot ?? '{}', true) ?? [];
            $response = $this->http()->post($this->baseUrl . '/shipments', [
                'plannedShippingDateAndTime' => now()->format('Y-m-d\TH:i:s \G\M\T+00:00'),
                'pickup' => ['isRequested' => false],
                'productCode' => 'P',
                'accounts' => [['typeCode' => 'shipper', 'number' => $this->apiKey]],
                'customerDetails' => [
                    'shipperDetails' => [
                        'postalAddress' => [
                            'countryCode' => 'EG',
                            'cityName' => 'Cairo',
                        ],
                        'contactInformation' => [
                            'fullName' => 'Platform Warehouse',
                            'phone' => '+20 1000000000',
                        ],
                    ],
                    'receiverDetails' => [
                        'postalAddress' => [
                            'countryCode' => $address['country_code'] ?? 'EG',
                            'cityName' => $address['city'] ?? '',
                        ],
                        'contactInformation' => [
                            'fullName' => $address['recipient_name'] ?? '',
                            'phone' => $address['recipient_phone'] ?? '',
                        ],
                    ],
                ],
                'content' => [
                    'packages' => [
                        [
                            'weight' => ($options['weight_grams'] ?? 500) / 1000,
                            'dimensions' => [
                                'length' => $options['length'] ?? 10,
                                'width' => $options['width'] ?? 10,
                                'height' => $options['height'] ?? 10,
                            ],
                        ]
                    ],
                    'unitOfMeasurement' => 'metric',
                    'isCustomsDeclarable' => false,
                    'description' => 'Order #' . $subOrder->sub_order_number,
                ],
            ]);

            $body = $response->json();

            if ($response->successful()) {
                return new ShippingLabel(
                    success: true,
                    trackingNumber: $body['shipmentTrackingNumber'] ?? '',
                    awbLabelUrl: $body['documents'][0]['content'] ?? '',
                    awbLabelBase64: $body['documents'][0]['content'] ?? null,
                    shippingCost: 0,
                    currency: 'USD',
                    carrierReferenceId: $body['dispatchConfirmationNumber'] ?? null,
                    errorMessage: null,
                    rawResponse: $body,
                );
            }

            return ShippingLabel::failure($body['detail'] ?? 'DHL API error', $body);
        } catch (\Exception $e) {
            return ShippingLabel::failure($e->getMessage());
        }
    }

    public function getTracking(string $trackingNumber): TrackingResult
    {
        try {
            $response = $this->http()->get($this->baseUrl . '/shipments/' . $trackingNumber . '/tracking');
            $body = $response->json();

            if (!$response->successful()) {
                return TrackingResult::notFound($trackingNumber, $body);
            }

            $shipment = $body['shipments'][0] ?? null;
            $events = array_map(fn($e) => [
                'status' => $e['typeCode'] ?? '',
                'description' => $e['description'] ?? '',
                'location' => $e['serviceArea'][0]['description'] ?? 'Unknown',
                'occurred_at' => $e['date'] . 'T' . ($e['time'] ?? '00:00:00'),
            ], $shipment['events'] ?? []);

            return new TrackingResult(
                found: true,
                trackingNumber: $trackingNumber,
                currentStatus: $shipment['status'] ?? 'unknown',
                events: $events,
                estimatedDelivery: $shipment['estimatedTimeOfDelivery'] ?? null,
                rawResponse: $body,
            );
        } catch (\Exception $e) {
            return TrackingResult::error($trackingNumber);
        }
    }

    public function cancelShipment(Shipment $shipment): bool
    {
        // DHL requires contact; treated as not supported programmatically
        return false;
    }

    public function calculateRate(array $from, array $to, int $weightGrams, array $dimensions = []): array
    {
        try {
            $response = $this->http()->get($this->baseUrl . '/rates', [
                'accountNumber' => $this->apiKey,
                'originCountryCode' => $from['country_code'],
                'destinationCountryCode' => $to['country_code'],
                'weight' => $weightGrams / 1000,
                'length' => $dimensions['length'] ?? 10,
                'width' => $dimensions['width'] ?? 10,
                'height' => $dimensions['height'] ?? 10,
                'plannedShippingDate' => now()->format('Y-m-d'),
                'productCode' => 'P',
            ]);

            $body = $response->json();
            $price = $body['products'][0]['totalPrice'][0] ?? null;

            return [
                'rate' => $price ? (int) ($price['price'] * 100) : 0,
                'currency' => $price['priceCurrency'] ?? 'USD',
                'estimated_days' => $body['products'][0]['deliveryCapabilities']['estimatedDeliveryDateAndTime'] ?? 3,
                'service_name' => 'DHL Express',
            ];
        } catch (\Exception $e) {
            return ['rate' => 0, 'currency' => 'USD', 'estimated_days' => 0, 'service_name' => 'DHL'];
        }
    }

    public function testConnection(): array
    {
        try {
            $start = microtime(true);
            $response = $this->http()->get($this->baseUrl . '/address-validate', [
                'type' => 'pickup',
                'countryCode' => 'EG',
            ]);
            $latency = round((microtime(true) - $start) * 1000);

            return [
                'success' => $response->status() < 500,
                'latency_ms' => $latency,
                'message' => $response->status() < 500
                    ? 'Connected (' . $latency . 'ms)'
                    : 'DHL API error',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'latency_ms' => 0, 'message' => $e->getMessage()];
        }
    }
}
