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

class AramexCarrier implements ShippingCarrierInterface
{
    private ?array $credentials;
    private ?string $wsdlUrl;
    private bool $testMode;

    public function __construct()
    {
        $dbCarrier = ShippingCarrier::where('code', 'aramex')->first();

        $this->credentials = $dbCarrier?->credentials_encrypted
            ? json_decode(Crypt::decryptString($dbCarrier->credentials_encrypted), true) ?? []
            : [];

        $this->testMode = !app()->isProduction();
        $this->wsdlUrl = $this->testMode
            ? 'https://ws.dev.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc'
            : 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc';
    }

    public function getCode(): string
    {
        return 'aramex';
    }
    public function getName(): string
    {
        return 'Aramex';
    }
    public function supportsCod(): bool
    {
        return true;
    }

    public function createShipment(SubOrder $subOrder, array $options = []): ShippingLabel
    {
        try {
            $start = microtime(true);
            $response = Http::timeout(30)
                ->post($this->wsdlUrl . '/CreateShipments', [
                    'ClientInfo' => $this->credentials,
                    'Shipments' => [
                        [
                            'Reference1' => $subOrder->sub_order_number,
                            'Shipper' => $this->buildShipperAddress($subOrder),
                            'Consignee' => $this->buildConsigneeAddress($subOrder),
                            'Details' => [
                                'Dimensions' => [
                                    'Length' => $options['length'] ?? 10,
                                    'Width' => $options['width'] ?? 10,
                                    'Height' => $options['height'] ?? 10,
                                    'Unit' => 'CM',
                                ],
                                'ActualWeight' => [
                                    'Value' => ($options['weight_grams'] ?? 500) / 1000,
                                    'Unit' => 'KG',
                                ],
                                'ProductType' => 'PDX',
                                'PaymentType' => 'P',
                                'NumberOfPieces' => 1,
                                'CashOnDeliveryAmount' => $options['cod_amount'] ?? null,
                            ],
                        ]
                    ],
                    'LabelInfo' => [
                        'ReportID' => 9201,
                        'ReportType' => 'URL',
                    ],
                ]);

            if (!$response->successful()) {
                return ShippingLabel::failure('Aramex API error: ' . $response->status(), $response->json() ?? []);
            }

            $body = $response->json();
            $shipment = $body['Shipments'][0] ?? null;

            return new ShippingLabel(
                success: true,
                trackingNumber: $shipment['ID'] ?? '',
                awbLabelUrl: $body['Labels'][0]['LabelURL'] ?? '',
                awbLabelBase64: null,
                shippingCostCents: (int) (($shipment['TotalAmount']['Value'] ?? 0) * 100),
                currency: $shipment['TotalAmount']['CurrencyCode'] ?? 'USD',
                carrierReferenceId: $shipment['ID'] ?? null,
                errorMessage: null,
                rawResponse: $body,
            );
        } catch (\Exception $e) {
            return ShippingLabel::failure($e->getMessage());
        }
    }

    public function getTracking(string $trackingNumber): TrackingResult
    {
        try {
            $response = Http::timeout(15)
                ->post($this->wsdlUrl . '/TrackShipments', [
                    'ClientInfo' => $this->credentials,
                    'Shipments' => [$trackingNumber],
                    'GetLastTrackingUpdateOnly' => false,
                ]);

            $body = $response->json();
            $trackingData = $body['TrackingResults'][0]['Value'][0] ?? null;

            if (!$trackingData) {
                return TrackingResult::notFound($trackingNumber, $body);
            }

            $events = array_map(fn($event) => [
                'status' => $event['UpdateCode'],
                'description' => $event['UpdateDescription'],
                'location' => $event['UpdateLocation'] ?? 'Unknown',
                'occurred_at' => $event['UpdateDateTime'],
            ], $trackingData['TrackingHistory'] ?? []);

            return new TrackingResult(
                found: true,
                trackingNumber: $trackingNumber,
                currentStatus: $trackingData['UpdateCode'] ?? 'unknown',
                events: $events,
                estimatedDelivery: $trackingData['ScheduledDelivery'] ?? null,
                rawResponse: $body,
            );
        } catch (\Exception $e) {
            return TrackingResult::error($trackingNumber);
        }
    }

    public function cancelShipment(Shipment $shipment): bool
    {
        try {
            $response = Http::timeout(15)
                ->post($this->wsdlUrl . '/DeleteShipments', [
                    'ClientInfo' => $this->credentials,
                    'Shipments' => [$shipment->tracking_number],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function calculateRate(array $from, array $to, int $weightGrams, array $dimensions = []): array
    {
        try {
            $response = Http::timeout(15)
                ->post($this->wsdlUrl . '/CalculateRate', [
                    'ClientInfo' => $this->credentials,
                    'OriginAddress' => ['CountryCode' => $from['country_code']],
                    'DestinationAddress' => ['CountryCode' => $to['country_code']],
                    'ShipmentDetails' => [
                        'ActualWeight' => ['Value' => $weightGrams / 1000, 'Unit' => 'KG'],
                        'ProductType' => 'PDX',
                        'PaymentType' => 'P',
                        'NumberOfPieces' => 1,
                    ],
                ]);

            $body = $response->json();
            $rate = $body['TotalAmount'] ?? null;

            return [
                'rate' => $rate ? (int) ($rate['Value'] * 100) : 0,
                'currency' => $rate['CurrencyCode'] ?? 'USD',
                'estimated_days' => 3,
                'service_name' => 'Aramex Express',
            ];
        } catch (\Exception $e) {
            return ['rate' => 0, 'currency' => 'USD', 'estimated_days' => 0, 'service_name' => 'Aramex'];
        }
    }

    public function testConnection(): array
    {
        try {
            $start = microtime(true);
            $response = Http::timeout(5)
                ->post($this->wsdlUrl . '/TrackShipments', [
                    'ClientInfo' => $this->credentials,
                    'Shipments' => ['TEST123'],
                ]);
            $latency = round((microtime(true) - $start) * 1000);

            return [
                'success' => $response->status() !== 500,
                'latency_ms' => $latency,
                'message' => $response->status() !== 500
                    ? 'Connected (' . $latency . 'ms)'
                    : 'Authentication failed',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'latency_ms' => 0, 'message' => $e->getMessage()];
        }
    }

    private function buildShipperAddress(SubOrder $subOrder): array
    {
        $warehouse = $subOrder->warehouse ?? null;

        return [
            'Reference1' => $warehouse?->code ?? 'WH001',
            'AccountNumber' => $this->credentials['account_number'] ?? '',
            'PartyAddress' => [
                'CountryCode' => $warehouse?->country?->iso_code_2 ?? 'EG',
                'City' => 'Cairo',
                'PostCode' => '11511',
                'Line1' => $warehouse?->address ?? 'Platform Warehouse',
            ],
            'Contact' => [
                'Department' => 'Warehouse',
                'PersonName' => 'Fulfillment Team',
                'PhoneNumber1' => '+20 1000000000',
                'EmailAddress' => 'warehouse@platform.com',
            ],
        ];
    }

    private function buildConsigneeAddress(SubOrder $subOrder): array
    {
        $address = json_decode($subOrder->order->shipping_address_snapshot ?? '{}', true) ?? [];

        return [
            'Reference1' => $subOrder->sub_order_number,
            'AccountNumber' => '',
            'PartyAddress' => [
                'CountryCode' => $address['country_code'] ?? 'EG',
                'City' => $address['city'] ?? '',
                'PostCode' => $address['postal_code'] ?? '',
                'Line1' => $address['street_address'] ?? '',
                'Line2' => $address['building'] ?? '',
                'Line3' => $address['area'] ?? '',
            ],
            'Contact' => [
                'PersonName' => $address['recipient_name'] ?? '',
                'PhoneNumber1' => $address['recipient_phone'] ?? '',
                'EmailAddress' => '',
            ],
        ];
    }
}
