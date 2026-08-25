class DeliveryAddress {
  final String? recipientName;
  final String? recipientPhone;
  final String? recipientPhoneMasked;
  final String? streetAddress;
  final String? area;
  final String? building;
  final String? floor;
  final String? apartment;
  final String? landmark;
  final String? city;
  final double? latitude;
  final double? longitude;

  DeliveryAddress({
    this.recipientName,
    this.recipientPhone,
    this.recipientPhoneMasked,
    this.streetAddress,
    this.area,
    this.building,
    this.floor,
    this.apartment,
    this.landmark,
    this.city,
    this.latitude,
    this.longitude,
  });

  factory DeliveryAddress.fromJson(Map<String, dynamic> json) => DeliveryAddress(
        recipientName: json['recipient_name'] as String?,
        recipientPhone: json['recipient_phone'] as String?,
        streetAddress: json['street_address'] as String?,
        area: json['area'] as String?,
        building: json['building'] as String?,
        floor: json['floor'] as String?,
        apartment: json['apartment'] as String?,
        landmark: json['landmark'] as String?,
        city: json['city'] as String?,
        latitude: (json['latitude'] as num?)?.toDouble(),
        longitude: (json['longitude'] as num?)?.toDouble(),
      );

  String get formatted => [apartment, building, floor, streetAddress ?? area, city]
      .where((e) => e != null && e.toString().isNotEmpty)
      .join(', ');
}

class AssignmentItem {
  final String name;
  final int quantity;
  final String? image;

  AssignmentItem({required this.name, required this.quantity, this.image});

  factory AssignmentItem.fromJson(Map<String, dynamic> json) => AssignmentItem(
        name: json['name'] as String? ?? '',
        quantity: (json['quantity'] as num?)?.toInt() ?? 1,
        image: json['image'] as String?,
      );
}

class AssignmentShipment {
  final String? trackingNumber;
  final String? carrier;
  final String? status;

  AssignmentShipment({this.trackingNumber, this.carrier, this.status});

  factory AssignmentShipment.fromJson(Map<String, dynamic> json) => AssignmentShipment(
        trackingNumber: json['tracking_number'] as String?,
        carrier: json['carrier'] as String?,
        status: json['status'] as String?,
      );
}

/// Used for both list (masked contact) and detail (full contact) responses —
/// detail-only fields are null when parsed from the list endpoint.
class Assignment {
  final int id;
  final String? subOrderNumber;
  final String status;
  final bool isCod;
  final int? codAmount;
  final int? codAmountDue;
  final int? codAmountCollected;
  final String? currency;
  final bool otpVerified;
  final int otpAttempts;
  final bool otpLocked;
  final DateTime? assignedAt;
  final DateTime? acceptedAt;
  final DateTime? pickedUpAt;
  final DateTime? deliveredAt;
  final DateTime? failedAt;
  final String? recipientName;
  final String? recipientPhoneMasked;
  final String? deliveryAddressLine;
  final double? latitude;
  final double? longitude;
  final DeliveryAddress? fullAddress;
  final List<AssignmentItem> items;
  final AssignmentShipment? shipment;
  final String? failureReason;
  final String? failureNotes;

  Assignment({
    required this.id,
    this.subOrderNumber,
    required this.status,
    required this.isCod,
    this.codAmount,
    this.codAmountDue,
    this.codAmountCollected,
    this.currency,
    required this.otpVerified,
    required this.otpAttempts,
    required this.otpLocked,
    this.assignedAt,
    this.acceptedAt,
    this.pickedUpAt,
    this.deliveredAt,
    this.failedAt,
    this.recipientName,
    this.recipientPhoneMasked,
    this.deliveryAddressLine,
    this.latitude,
    this.longitude,
    this.fullAddress,
    this.items = const [],
    this.shipment,
    this.failureReason,
    this.failureNotes,
  });

  factory Assignment.fromJson(Map<String, dynamic> json) => Assignment(
        id: (json['id'] as num).toInt(),
        subOrderNumber: json['sub_order_number'] as String?,
        status: json['status'] as String? ?? '',
        isCod: json['is_cod'] as bool? ?? false,
        codAmount: (json['cod_amount'] as num?)?.toInt(),
        codAmountDue: (json['cod_amount_due'] as num?)?.toInt(),
        codAmountCollected: (json['cod_amount_collected'] as num?)?.toInt(),
        currency: json['currency'] as String?,
        otpVerified: json['otp_verified'] as bool? ?? false,
        otpAttempts: (json['otp_attempts'] as num?)?.toInt() ?? 0,
        otpLocked: json['otp_locked'] as bool? ?? false,
        assignedAt: json['assigned_at'] != null ? DateTime.tryParse(json['assigned_at']) : null,
        acceptedAt: json['accepted_at'] != null ? DateTime.tryParse(json['accepted_at']) : null,
        pickedUpAt: json['picked_up_at'] != null ? DateTime.tryParse(json['picked_up_at']) : null,
        deliveredAt: json['delivered_at'] != null ? DateTime.tryParse(json['delivered_at']) : null,
        failedAt: json['failed_at'] != null ? DateTime.tryParse(json['failed_at']) : null,
        recipientName: json['recipient_name'] as String?,
        recipientPhoneMasked: json['recipient_phone_masked'] as String?,
        deliveryAddressLine: json['delivery_address'] is String ? json['delivery_address'] as String : null,
        latitude: (json['latitude'] as num?)?.toDouble(),
        longitude: (json['longitude'] as num?)?.toDouble(),
        fullAddress: json['delivery_address'] is Map
            ? DeliveryAddress.fromJson(json['delivery_address'] as Map<String, dynamic>)
            : null,
        items: json['items'] != null
            ? (json['items'] as List).map((e) => AssignmentItem.fromJson(e as Map<String, dynamic>)).toList()
            : const [],
        shipment: json['shipment'] != null
            ? AssignmentShipment.fromJson(json['shipment'] as Map<String, dynamic>)
            : null,
        failureReason: json['failure_reason'] as String?,
        failureNotes: json['failure_notes'] as String?,
      );

  int? get effectiveCodAmount => codAmountDue ?? codAmount;

  bool get isActive => ['assigned', 'accepted', 'picked_up'].contains(status);
}

class AssignmentsDashboard {
  final List<Assignment> active;
  final List<Assignment> completedToday;

  AssignmentsDashboard({required this.active, required this.completedToday});

  factory AssignmentsDashboard.fromJson(Map<String, dynamic> json) => AssignmentsDashboard(
        active: (json['active_assignments'] as List? ?? [])
            .map((e) => Assignment.fromJson(e as Map<String, dynamic>))
            .toList(),
        completedToday: (json['completed_today'] as List? ?? [])
            .map((e) => Assignment.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

const failureReasons = <String>[
  'customer_not_available',
  'customer_rejected',
  'incorrect_address',
  'customer_unreachable',
  'unable_to_locate',
  'other',
];

String failureReasonLabel(String value) {
  switch (value) {
    case 'customer_not_available':
      return 'Customer not available';
    case 'customer_rejected':
      return 'Customer rejected the order';
    case 'incorrect_address':
      return 'Incorrect address';
    case 'customer_unreachable':
      return 'Customer unreachable';
    case 'unable_to_locate':
      return 'Unable to locate address';
    case 'other':
      return 'Other';
    default:
      return value;
  }
}
