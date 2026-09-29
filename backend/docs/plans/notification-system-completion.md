# Notification System Completion Plan

**Date:** 2026-09-29  
**Status:** Implementation in progress

---

## Current State

The platform has a solid notification infrastructure:

- **`BaseDatabaseBroadcastNotification`** — abstract base for all notifications; queued, database + broadcast channels
- **`CustomDatabaseChannel`** — writes to custom `notifications` table with `channel` enum + `sent_at`
- **`VendorPushChannel`** — FCM push via `VendorFCMService`
- **`NotificationChannel`** enum — `database | email | sms | push | whatsapp`
- **Broadcasting** — private channels per guard: `admin.{id}`, `vendor.{id}`, `delivery-agent.{id}`, `carrier-supervisor.{id}`, `travel-agency.{id}`, `marketer.{id}`
- **Notification CRUD API routes** exist for all portals (vendor, marketer, delivery, carrier, travel, admin, customer)

### Auth Guards → Models

| Guard | Model | Broadcast Channel |
|---|---|---|
| `admin` | `Admin` | `admin.{id}` |
| `vendor` / `vendor_api` | `VendorAdmin` | `vendor.{id}` |
| `marketer` / `marketer_api` | `MarketerAdmin` | `marketer.{id}` |
| `delivery` / `delivery_api` | `DeliveryAgent` | `delivery-agent.{id}` |
| `shipping_supervisor` / `shipping_supervisor_api` | `ShippingCompanySupervisor` | `carrier-supervisor.{id}` |
| `travel_agency` / `travel_agencies` | `TravelAgency` | `travel-agency.{id}` |
| `customer` / `web` | `Customer` | `customer.{id}` *(to be added to channels.php)* |

---

## Gap Analysis

### Admin (`app/Notifications/Admin/`)

| Notification | Trigger | Missing? |
|---|---|---|
| `NewOrderPlaced` | Any order placed | ✅ MISSING |
| `OrderCancelledByCustomer` | Customer self-cancels | ✅ MISSING |
| `ReturnRequestReceived` | Customer submits return request | ✅ MISSING |
| `NewCustomerRegistered` | Customer registration | ✅ MISSING |
| `PaymentFailed` | Payment fails/declined | ✅ MISSING |
| `CodRemittanceRequested` | Vendor submits COD remittance | ✅ MISSING |
| `NewVendorApplicationSubmitted` | Vendor applies | ✔ exists |
| `DisputeOpened` | Customer opens dispute | ✔ exists |
| `WithdrawalRequested` | Vendor withdrawal | ✔ exists |
| `PayoutBatchReadyForApproval` | Payout batch ready | ✔ exists |

### Vendor (`app/Notifications/Vendor/`)

| Notification | Trigger | Missing? |
|---|---|---|
| `SubOrderCancelledByCustomer` | Customer self-cancels their suborder | ✅ MISSING |
| `PaymentCapturedForOrder` | Payment successfully captured | ✅ MISSING |
| `ReturnArrivedAtVendor` | Return physically delivered back | ✅ MISSING |
| `NewOrderReceived` | New suborder | ✔ exists |
| `OrderCancelledByAdmin` | Admin cancels | ✔ exists |
| `PayoutProcessed` | Payout sent | ✔ exists |
| `LowStockAlert` | Stock low | ✔ exists |
| `AccountSuspended/Reactivated` | Account actions | ✔ exists |

### DeliveryAgent (`app/Notifications/DeliveryAgent/`)

| Notification | Trigger | Missing? |
|---|---|---|
| `EarningsPaid` | Agent earnings payout processed | ✅ MISSING |
| `ShiftStartReminder` | X minutes before shift starts | ✅ MISSING |
| `NewDeliveryAssigned` | New delivery assigned | ✔ exists |
| `DeliveryReassigned` | Assignment reassigned | ✔ exists |

### ShippingCompanySupervisor (`app/Notifications/Carrier/`)

| Notification | Trigger | Missing? |
|---|---|---|
| `PayoutProcessed` | Carrier payout processed | ✅ MISSING |
| `NewAgentRegistered` | New delivery agent added to company | ✅ MISSING |
| `AgentWentOffline` | Agent goes offline mid-shift | ✔ exists |
| `DeliveryCompleted` | Assignment completed | ✔ exists |
| `DeliveryFailed` | Assignment failed | ✔ exists |
| `NewUnassignedShipmentArrived` | Shipment arrived unassigned | ✔ exists |
| `AssignmentReassignedNotification` | Assignment reassigned | ✔ exists |

### Marketer (`app/Notifications/Marketer/`)

| Notification | Trigger | Missing? |
|---|---|---|
| `CampaignExpired` | Campaign reaches end date | ✅ MISSING |
| `PayoutScheduled` | Marketer payout scheduled | ✅ MISSING |
| `NewConversionNotification` | Conversion attributed | ✔ exists |
| `CampaignInvitationReceivedNotification` | Invitation from vendor | ✔ exists |
| `PayoutProcessedNotification` | Payout sent | ✔ exists |
| `FlashSaleInvitationNotification` | Flash sale invite | ✔ exists |

### TravelAgency (`app/Notifications/TravelAgency/`)

| Notification | Trigger | Missing? |
|---|---|---|
| `PaymentReceived` | Booking payment captured | ✅ MISSING |
| `BookingModified` | Customer modifies booking details | ✅ MISSING |
| `NewBookingReceived` | New booking | ✔ exists |
| `BookingCancelled` | Booking cancelled | ✔ exists |
| `LowSeatsRemaining` | Seats running low | ✔ exists |
| `PackageApproved/Rejected` | Admin decisions | ✔ exists |

### Customer (`app/Notifications/Customer/`)

| Notification | Trigger | Missing? |
|---|---|---|
| `PaymentFailed` | Payment declined/failed | ✅ MISSING |
| `WalletCredited` | Wallet topped up / refund credited | ✅ MISSING |
| `OrderConfirmed` | Order confirmed | ✔ exists |
| `OrderShipped` | Shipped | ✔ exists |
| `OrderDelivered` | Delivered | ✔ exists |
| `OrderCancelled` | Cancelled | ✔ exists |
| `OrderRefunded` | Refunded | ✔ exists |

---

## Dispatch Points (Where notifications must be fired)

### Existing gaps in services/controllers

| Location | Event | Notifications to dispatch |
|---|---|---|
| `PaymentService::handleCallback` (captured) | Payment captured | `Customer\OrderConfirmed` (if not already), `Vendor\PaymentCapturedForOrder`, `Admin\NewOrderPlaced` |
| `PaymentService::handleCallback` (failed) | Payment failed | `Customer\PaymentFailed`, `Admin\PaymentFailed` |
| `OrderCancellationService::notify` | Customer self-cancels | Add `Admin\OrderCancelledByCustomer` dispatch |
| `Customer/ReturnService` | Return submitted | `Admin\ReturnRequestReceived` |
| Customer registration controller | New customer | `Admin\NewCustomerRegistered` |
| COD remittance controller | Vendor submits COD | `Admin\CodRemittanceRequested` |
| `OrderFulfillmentService` or return flow | Return delivered to vendor | `Vendor\ReturnArrivedAtVendor` |
| Delivery payout job/service | Agent payout | `DeliveryAgent\EarningsPaid` |
| `ShiftService` (scheduled) | 30 min before shift | `DeliveryAgent\ShiftStartReminder` |
| Carrier payout service | Supervisor payout | `Carrier\PayoutProcessed` |
| Agent roster service (create agent) | New agent registered | `Carrier\NewAgentRegistered` |
| `MarketerCampaignService` (campaign end) | Campaign expires | `Marketer\CampaignExpired` |
| Marketer payout job | Payout scheduled | `Marketer\PayoutScheduled` |
| `TravelBookingService` (payment hook) | Booking payment captured | `TravelAgency\PaymentReceived` |
| Booking modification flow | Booking modified | `TravelAgency\BookingModified` |
| `WalletService` / `RefundService` | Wallet credited | `Customer\WalletCredited` |

---

## Notification Class Template

Every notification extends `BaseDatabaseBroadcastNotification`:

```php
namespace App\Notifications\{Guard};

use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class ExampleNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly Model $model) {}

    public function notificationType(): string
    {
        return 'snake_case_event_name';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'    => 'Human-readable title',
            'message'  => 'Human-readable body.',
            'url'      => route('named.route', $this->model),
            'model_id' => $this->model->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('channel-name.' . $notifiable->id)];
    }
}
```

**Channel naming by guard:**
- Admin → `admin.{notifiable->id}`
- Vendor → `vendor.{notifiable->id}`
- Marketer → `marketer.{notifiable->id}`
- DeliveryAgent → `delivery-agent.{notifiable->id}`
- Supervisor → `carrier-supervisor.{notifiable->id}`
- TravelAgency → `travel-agency.{notifiable->id}`
- Customer → `customer.{notifiable->id}` *(add channel auth to channels.php)*

---

## Implementation Plan — 5 Agents

### Agent 1 — Admin Missing Notifications
**Files to create:**
- `app/Notifications/Admin/NewOrderPlaced.php`
- `app/Notifications/Admin/OrderCancelledByCustomer.php`
- `app/Notifications/Admin/ReturnRequestReceived.php`
- `app/Notifications/Admin/NewCustomerRegistered.php`
- `app/Notifications/Admin/PaymentFailed.php`
- `app/Notifications/Admin/CodRemittanceRequested.php`

**Dispatch wiring:**
- `app/Services/Customer/OrderService.php` — after order created → `NewOrderPlaced` to admins with `notifications.manage` permission
- `app/Services/OrderCancellationService.php` — customer cancel path → `OrderCancelledByCustomer`
- `app/Services/Customer/ReturnService.php` — after return request stored → `ReturnRequestReceived`
- `app/Services/PaymentService.php` — on `failed` branch → `PaymentFailed`
- Customer registration auth controller — after Customer created → `NewCustomerRegistered`
- COD remittance controller — after submission → `CodRemittanceRequested`

### Agent 2 — Vendor Missing Notifications
**Files to create:**
- `app/Notifications/Vendor/SubOrderCancelledByCustomer.php`
- `app/Notifications/Vendor/PaymentCapturedForOrder.php`
- `app/Notifications/Vendor/ReturnArrivedAtVendor.php`

**Dispatch wiring:**
- `app/Services/OrderCancellationService.php` — customer cancel path (alongside existing `OrderCancelledByAdmin`) → `SubOrderCancelledByCustomer`
- `app/Services/PaymentService.php` — on `captured` branch → `PaymentCapturedForOrder` to each suborder's vendor admins
- Return inspection/delivery controller or service — when return status = delivered_to_vendor → `ReturnArrivedAtVendor`

### Agent 3 — DeliveryAgent + Carrier Supervisor Missing Notifications
**Files to create:**
- `app/Notifications/DeliveryAgent/EarningsPaid.php`
- `app/Notifications/DeliveryAgent/ShiftStartReminder.php`
- `app/Notifications/Carrier/PayoutProcessed.php`
- `app/Notifications/Carrier/NewAgentRegistered.php`

**Dispatch wiring:**
- Delivery payout job/service — after payout → `EarningsPaid` to agent
- `app/Services/Delivery/ShiftService.php` — scheduled 30 min before shift → `ShiftStartReminder` (add artisan command or use existing scheduler)
- Carrier payout service — after payout → `Carrier\PayoutProcessed` to supervisor
- `app/Services/Carrier/AgentRosterService.php` — after agent created → `NewAgentRegistered` to supervisors

### Agent 4 — Marketer + TravelAgency Missing Notifications
**Files to create:**
- `app/Notifications/Marketer/CampaignExpired.php`
- `app/Notifications/Marketer/PayoutScheduled.php`
- `app/Notifications/TravelAgency/PaymentReceived.php`
- `app/Notifications/TravelAgency/BookingModified.php`

**Dispatch wiring:**
- `app/Services/MarketerCampaignService.php` or campaign end job — on campaign expiry → `CampaignExpired`
- Marketer payout job — when scheduled → `PayoutScheduled`
- `app/Services/Customer/TravelBookingService.php` or payment hook — on booking payment captured → `PaymentReceived`
- Travel booking modification endpoint — on booking update → `BookingModified`

### Agent 5 — Customer Missing Notifications + channels.php
**Files to create:**
- `app/Notifications/Customer/PaymentFailed.php`
- `app/Notifications/Customer/WalletCredited.php`

**Files to modify:**
- `routes/channels.php` — add `customer.{customerId}` channel authorization
- `app/Services/PaymentService.php` — on `failed` branch → `Customer\PaymentFailed`
- `app/Services/WalletService.php` or refund service — on wallet credit → `WalletCredited`

---

## Notes on Permissions

When dispatching to Admins, use the Spatie permission filter:
```php
Admin::permission('orders.view')->get()  // or relevant permission
```

For supervisors, fetch by shipping company:
```php
$agent->shippingCompany->supervisors
```

For vendors, use:
```php
$subOrder->vendor->vendorAdmins
```

---

## Mail Notifications

For critical financial events (payment failed, payout processed), add email channel alongside database:

```php
public function via(object $notifiable): array
{
    return ['database', 'broadcast', 'mail'];
}

public function toMail(object $notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Payment Failed — Order #' . $this->order->order_number)
        ->line('...')
        ->action('View Order', route(...));
}
```

Events warranting email:
- `Customer\PaymentFailed`
- `Vendor\PaymentCapturedForOrder`
- `DeliveryAgent\EarningsPaid`
- `Carrier\PayoutProcessed`
- `Marketer\PayoutScheduled`
