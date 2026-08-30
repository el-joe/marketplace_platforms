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

class FedexCarrier implements ShippingCarrierInterface
{
    private ?string $apiKey;
    private ?string $apiSecret;
    private ?string $accountNumber;
    private ?string $baseUrl;
    private bool $testMode;

    public function __construct()
    {
        $dbCarrier = ShippingCarrier::where('code', 'fedex')->first();
        $creds = [];
        if ($dbCarrier?->credentials_encrypted) {
            $creds = json_decode(Crypt::decryptString($dbCarrier->credentials_encrypted), true) ?? [];
        }

        $this->testMode = !app()->isProduction();
        $this->apiKey = $creds['api_key'] ?? '';
        $this->apiSecret = $creds['api_secret'] ?? '';
        $this->accountNumber = $creds['account_number'] ?? '';
        $this->baseUrl = $this->testMode
            ? 'https://apis-sandbox.fedex.com'
            : 'https://apis.fedex.com';
    }

    public function getCode(): string
    {
        return 'fedex';
    }
    public function getName(): string
    {
        return 'FedEx';
    }
    public function supportsCod(): bool
    {
        return false;
    }

    private function getAccessToken(): string
    {
        $response = Http::asForm()->post($this->baseUrl . '/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
        ]);

        return $response->json('access_token', '');
    }

    public function createShipment(SubOrder $subOrder, array $options = []): ShippingLabel
    {
        try {
            $token = $this->getAccessToken();
            $address = json_decode($subOrder->order->shipping_address_snapshot ?? '{}', true) ?? [];
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->post($this->baseUrl . '/ship/v1/shipments', [
                    'labelResponseOptions' => 'URL_ONLY',
                    'requestedShipment' => [
                        'shipper' => [
                            'contact' => ['personName' => 'Fulfillment Team', 'phoneNumber' => '0000000000'],
                            'address' => ['countryCode' => 'EG', 'city' => 'Cairo'],
                        ],
                        'recipients' => [
                            [
                                'contact' => [
                                    'personName' => $address['recipient_name'] ?? '',
                                    'phoneNumber' => $address['recipient_phone'] ?? '',
                                ],
                                'address' => [
                                    'city' => $address['city'] ?? '',
                                    'countryCode' => $address['country_code'] ?? 'EG',
                                ],
                            ]
                        ],
                        'serviceType' => 'INTERNATIONAL_PRIORITY',
                        'packagingType' => 'FEDEX_BOX',
                        'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
                        'requestedPackageLineItems' => [
                            [
                                'weight' => ['value' => ($options['weight_grams'] ?? 500) / 1000, 'units' => 'KG'],
                            ]
                        ],
                    ],
                    'accountNumber' => ['value' => $this->accountNumber],
                ]);

            $body = $response->json();

            if ($response->successful()) {
                $result = $body['output']['transactionShipments'][0] ?? null;

                return new ShippingLabel(
                    success: true,
                    trackingNumber: $result['masterTrackingNumber'] ?? '',
                    awbLabelUrl: $result['pieceResponses'][0]['packageDocuments'][0]['url'] ?? '',
                    awbLabelBase64: null,
                    shippingCost: 0,
                    currency: 'USD',
                    carrierReferenceId: $result['masterTrackingNumber'] ?? null,
                    errorMessage: null,
                    rawResponse: $body,
                );
            }

            return ShippingLabel::failure($body['errors'][0]['message'] ?? 'FedEx API error', $body);
        } catch (\Exception $e) {
            return ShippingLabel::failure($e->getMessage());
        }
    }

    public function getTracking(string $trackingNumber): TrackingResult
    {
        try {
            $token = $this->getAccessToken();
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl . '/track/v1/trackingdocuments', [
                    'trackingInfo' => [['trackingNumberInfo' => ['trackingNumber' => $trackingNumber]]],
                ]);

            $body = $response->json();
            $tracking = $body['output']['completeTrackResults'][0]['trackResults'][0] ?? null;

            if (!$tracking) {
                return TrackingResult::notFound($trackingNumber, $body);
            }

            $events = array_map(fn($e) => [
                'status' => $e['eventType'] ?? '',
                'description' => $e['eventDescription'] ?? '',
                'location' => $e['scanLocation']['city'] ?? 'Unknown',
                'occurred_at' => $e['date'] ?? '',
            ], $tracking['scanEvents'] ?? []);

            return new TrackingResult(
                found: true,
                trackingNumber: $trackingNumber,
                currentStatus: $tracking['latestStatusDetail']['code'] ?? 'unknown',
                events: $events,
                estimatedDelivery: $tracking['estimatedDeliveryTimeWindow']['window']['ends'] ?? null,
                rawResponse: $body,
            );
        } catch (\Exception $e) {
            return TrackingResult::error($trackingNumber);
        }
    }

    public function cancelShipment(Shipment $shipment): bool
    {
        return false; // FedEx does not support programmatic cancel via REST
    }

    public function calculateRate(array $from, array $to, int $weightGrams, array $dimensions = [], string $currency = ''): array
    {
        try {
            $token = $this->getAccessToken();
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl . '/rate/v1/rates/quotes', [
                    'accountNumber' => ['value' => $this->accountNumber],
                    'requestedShipment' => [
                        'shipper' => ['address' => ['countryCode' => $from['country_code']]],
                        'recipient' => ['address' => ['countryCode' => $to['country_code']]],
                        'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
                        'serviceType' => 'INTERNATIONAL_PRIORITY',
                        'requestedPackageLineItems' => [
                            [
                                'weight' => ['value' => $weightGrams / 1000, 'units' => 'KG'],
                            ]
                        ],
                    ],
                ]);

            $body = $response->json();
            $rated = $body['output']['rateReplyDetails'][0]['ratedShipmentDetails'][0]['totalNetCharge'] ?? null;

            return [
                'rate' => $rated ? (int) ($rated * 100) : 0,
                'currency' => 'USD',
                'estimated_days' => 3,
                'service_name' => 'FedEx International Priority',
            ];
        } catch (\Exception $e) {
            return ['rate' => 0, 'currency' => 'USD', 'estimated_days' => 0, 'service_name' => 'FedEx'];
        }
    }

    public function testConnection(): array
    {
        try {
            $start = microtime(true);
            $token = $this->getAccessToken();
            $latency = round((microtime(true) - $start) * 1000);

            return [
                'success' => !empty($token),
                'latency_ms' => $latency,
                'message' => !empty($token)
                    ? 'Connected (' . $latency . 'ms)'
                    : 'Failed to obtain access token',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'latency_ms' => 0, 'message' => $e->getMessage()];
        }
    }
}
