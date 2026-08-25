import 'package:dio/dio.dart';

import '../auth/token_storage.dart';
import 'api_exception.dart';

/// Thin Dio wrapper for the read-only Partner API.
///
/// Response envelope is always `{ success, message, data, ... }`. `request`
/// unwraps `data` for callers; `requestEnvelope` hands back the raw decoded
/// body for the few endpoints (e.g. notifications list) whose pagination
/// `meta` sits alongside `data` rather than nested inside it.
class ApiClient {
  ApiClient._internal() {
    _dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {'Accept': 'application/json'},
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          if (!options.path.contains('/auth/login')) {
            final token = await TokenStorage.instance.accessToken;
            if (token != null) {
              options.headers['Authorization'] = 'Bearer $token';
            }
          }
          return handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401 &&
              !error.requestOptions.path.contains('/auth/login') &&
              !error.requestOptions.path.contains('/auth/refresh-token')) {
            try {
              final refreshed = await _refreshToken();
              if (refreshed != null) {
                final retryOptions = error.requestOptions;
                retryOptions.headers['Authorization'] = 'Bearer $refreshed';
                final response = await _dio.fetch(retryOptions);
                return handler.resolve(response);
              }
            } catch (_) {
              // fall through to error
            }
          }
          return handler.next(error);
        },
      ),
    );
  }

  static final ApiClient instance = ApiClient._internal();

  static const String baseUrl = 'https://api.noon.codefanz.com/api/partner/v1';

  late final Dio _dio;
  Dio get dio => _dio;

  Future<String?> _refreshToken() async {
    final current = await TokenStorage.instance.accessToken;
    if (current == null) return null;

    final freshDio = Dio(BaseOptions(baseUrl: baseUrl, headers: {'Accept': 'application/json'}));
    final response = await freshDio.post(
      '/auth/refresh-token',
      options: Options(headers: {'Authorization': 'Bearer $current'}),
    );
    final newAccessToken = response.data['access_token'] as String?;
    if (newAccessToken != null) {
      await TokenStorage.instance.saveAccessToken(newAccessToken);
    }
    return newAccessToken;
  }

  Future<T> request<T>(
    Future<Response> Function(Dio dio) call, {
    required T Function(dynamic data) parse,
  }) async {
    try {
      final response = await call(_dio);
      return parse(response.data['data']);
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  /// Returns the full decoded JSON body (not just `data`) for endpoints
  /// whose `meta` lives as a sibling of `data` instead of nested in it.
  Future<T> requestEnvelope<T>(
    Future<Response> Function(Dio dio) call, {
    required T Function(Map<String, dynamic> body) parse,
  }) async {
    try {
      final response = await call(_dio);
      return parse(response.data as Map<String, dynamic>);
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  ApiException _toApiException(DioException e) {
    final data = e.response?.data;
    String message = 'Something went wrong. Please try again.';
    Map<String, dynamic>? errors;

    if (data is Map<String, dynamic>) {
      message = (data['message'] as String?) ?? message;
      final rawErrors = data['errors'];
      if (rawErrors is Map<String, dynamic>) errors = rawErrors;
    } else if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout) {
      message = 'Connection timed out.';
    } else if (e.type == DioExceptionType.connectionError) {
      message = 'No internet connection.';
    }

    return ApiException(message, statusCode: e.response?.statusCode, errors: errors);
  }
}
