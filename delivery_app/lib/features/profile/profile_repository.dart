import 'dart:io';

import 'package:dio/dio.dart';

import '../../core/api/api_client.dart';
import '../../shared/models/agent.dart';
import '../../shared/models/document.dart';

class ProfileRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Agent> getProfile() {
    return _client.request<Agent>(
      (dio) => dio.get('/profile'),
      parse: (data) => Agent.fromJson(data as Map<String, dynamic>),
    );
  }

  Future<Agent> updateProfile({String? vehicleType, String? vehiclePlate}) {
    return _client.request<Agent>(
      (dio) => dio.put('/profile', data: {
        if (vehicleType != null) 'vehicle_type': vehicleType,
        if (vehiclePlate != null) 'vehicle_plate': vehiclePlate,
      }),
      parse: (data) => Agent.fromJson(data as Map<String, dynamic>),
    );
  }

  Future<void> updatePassword({required String currentPassword, required String password}) {
    return _client.request<void>(
      (dio) => dio.put('/profile/password', data: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': password,
      }),
      parse: (_) {},
    );
  }

  Future<List<AgentDocument>> documents() {
    return _client.request<List<AgentDocument>>(
      (dio) => dio.get('/profile/documents'),
      parse: (data) => (data as List).map((e) => AgentDocument.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }

  Future<void> reuploadDocument(String type, File file) async {
    final formData = FormData.fromMap({'file': await MultipartFile.fromFile(file.path)});
    return _client.request<void>(
      (dio) => dio.post('/profile/documents/$type/reupload', data: formData),
      parse: (_) {},
    );
  }
}
