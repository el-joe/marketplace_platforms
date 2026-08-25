import 'dart:io';

import 'package:dio/dio.dart';

import '../../core/api/api_client.dart';
import '../../shared/models/assignment.dart';

class AssignmentsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<AssignmentsDashboard> list() {
    return _client.request<AssignmentsDashboard>(
      (dio) => dio.get('/assignments'),
      parse: (data) => AssignmentsDashboard.fromJson(data as Map<String, dynamic>),
    );
  }

  Future<Assignment> show(int id) {
    return _client.request<Assignment>(
      (dio) => dio.get('/assignments/$id'),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }

  Future<Assignment> accept(int id) {
    return _client.request<Assignment>(
      (dio) => dio.post('/assignments/$id/accept'),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }

  Future<Assignment> pickedUp(int id, {required double latitude, required double longitude}) {
    return _client.request<Assignment>(
      (dio) => dio.post('/assignments/$id/picked-up', data: {'latitude': latitude, 'longitude': longitude}),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }

  Future<Assignment> verifyOtp(int id, String otpCode) {
    return _client.request<Assignment>(
      (dio) => dio.post('/assignments/$id/verify-otp', data: {'otp_code': otpCode}),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }

  Future<Assignment> deliver(
    int id, {
    required String otpCode,
    required double latitude,
    required double longitude,
    File? proofImage,
    int? codAmountCollected,
    String? discrepancyNote,
  }) async {
    final formData = FormData.fromMap({
      'otp_code': otpCode,
      'latitude': latitude,
      'longitude': longitude,
      if (codAmountCollected != null) 'cod_amount_collected': codAmountCollected,
      if (discrepancyNote != null) 'discrepancy_note': discrepancyNote,
      if (proofImage != null) 'proof_image': await MultipartFile.fromFile(proofImage.path),
    });

    return _client.request<Assignment>(
      (dio) => dio.post('/assignments/$id/deliver', data: formData),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }

  Future<Assignment> fail(
    int id, {
    required String failureReason,
    String? failureNotes,
    required double latitude,
    required double longitude,
    String? customerRejectionReason,
  }) {
    return _client.request<Assignment>(
      (dio) => dio.post('/assignments/$id/fail', data: {
        'failure_reason': failureReason,
        if (failureNotes != null) 'failure_notes': failureNotes,
        'latitude': latitude,
        'longitude': longitude,
        if (customerRejectionReason != null) 'customer_rejection_reason': customerRejectionReason,
      }),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }
}
