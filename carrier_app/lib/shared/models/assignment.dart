class AssignmentAgentRef {
  final String id;
  final String? name;

  AssignmentAgentRef({required this.id, this.name});

  factory AssignmentAgentRef.fromJson(Map<String, dynamic> json) =>
      AssignmentAgentRef(id: json['id'].toString(), name: json['name'] as String?);
}

class SubOrderRef {
  final String id;
  final String? subOrderNumber;

  SubOrderRef({required this.id, this.subOrderNumber});

  factory SubOrderRef.fromJson(Map<String, dynamic> json) =>
      SubOrderRef(id: json['id'].toString(), subOrderNumber: json['sub_order_number'] as String?);
}

class TrackingEvent {
  final String? status;
  final DateTime? occurredAt;

  TrackingEvent({this.status, this.occurredAt});

  factory TrackingEvent.fromJson(Map<String, dynamic> json) => TrackingEvent(
        status: json['status'] as String?,
        occurredAt: json['occurred_at'] != null ? DateTime.tryParse(json['occurred_at']) : null,
      );
}

class AssignmentShipment {
  final String id;
  final String? trackingNumber;
  final SubOrderRef? subOrder;
  final List<TrackingEvent> trackingEvents;

  AssignmentShipment({required this.id, this.trackingNumber, this.subOrder, this.trackingEvents = const []});

  factory AssignmentShipment.fromJson(Map<String, dynamic> json) => AssignmentShipment(
        id: json['id'].toString(),
        trackingNumber: json['tracking_number'] as String?,
        subOrder: json['sub_order'] != null ? SubOrderRef.fromJson(json['sub_order'] as Map<String, dynamic>) : null,
        trackingEvents: (json['tracking_events'] as List? ?? [])
            .map((e) => TrackingEvent.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class Assignment {
  final String id;
  final String status;
  final String? subOrderNumber;
  final AssignmentAgentRef? agent;
  final DateTime? assignedAt;
  final DateTime? acceptedAt;
  final DateTime? pickedUpAt;
  final DateTime? deliveredAt;

  // Detailed fields (present on show()).
  final AssignmentShipment? shipment;
  final int? codAmountCollected;
  final String? deliveryOtp;
  final String? agentNotes;

  Assignment({
    required this.id,
    required this.status,
    this.subOrderNumber,
    this.agent,
    this.assignedAt,
    this.acceptedAt,
    this.pickedUpAt,
    this.deliveredAt,
    this.shipment,
    this.codAmountCollected,
    this.deliveryOtp,
    this.agentNotes,
  });

  bool get isReassignable => status == 'assigned' || status == 'accepted';

  factory Assignment.fromJson(Map<String, dynamic> json) => Assignment(
        id: json['id'].toString(),
        status: json['status'] as String? ?? '',
        subOrderNumber: json['sub_order_number'] as String?,
        agent: json['agent'] != null ? AssignmentAgentRef.fromJson(json['agent'] as Map<String, dynamic>) : null,
        assignedAt: json['assigned_at'] != null ? DateTime.tryParse(json['assigned_at']) : null,
        acceptedAt: json['accepted_at'] != null ? DateTime.tryParse(json['accepted_at']) : null,
        pickedUpAt: json['picked_up_at'] != null ? DateTime.tryParse(json['picked_up_at']) : null,
        deliveredAt: json['delivered_at'] != null ? DateTime.tryParse(json['delivered_at']) : null,
        shipment: json['shipment'] != null ? AssignmentShipment.fromJson(json['shipment'] as Map<String, dynamic>) : null,
        codAmountCollected: (json['cod_amount_collected'] as num?)?.toInt(),
        deliveryOtp: json['delivery_otp'] as String?,
        agentNotes: json['agent_notes'] as String?,
      );
}

class UnassignedShipment {
  final String id;
  final String? trackingNumber;
  final String? subOrderNumber;
  final String? orderNumber;
  final String status;
  final DateTime? createdAt;

  UnassignedShipment({
    required this.id,
    this.trackingNumber,
    this.subOrderNumber,
    this.orderNumber,
    required this.status,
    this.createdAt,
  });

  factory UnassignedShipment.fromJson(Map<String, dynamic> json) => UnassignedShipment(
        id: json['id'].toString(),
        trackingNumber: json['tracking_number'] as String?,
        subOrderNumber: json['sub_order_number'] as String?,
        orderNumber: json['order_number'] as String?,
        status: json['status'] as String? ?? '',
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
      );
}
