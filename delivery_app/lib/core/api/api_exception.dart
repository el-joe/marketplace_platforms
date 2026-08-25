class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  ApiException(this.message, {this.statusCode, this.errors});

  bool get isOtpLocked => statusCode == 423;
  bool get isRateLimited => statusCode == 429;
  bool get isUnauthorized => statusCode == 401;
  bool get requiresDiscrepancyNote =>
      errors?['requires_discrepancy_note'] == true || message.contains('discrepancy_note_required');

  int? get remainingOtpAttempts {
    final value = errors?['remaining_attempts'];
    if (value is int) return value;
    return null;
  }

  @override
  String toString() => message;
}
