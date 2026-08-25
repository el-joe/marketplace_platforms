import 'package:dio/dio.dart';

import '../auth/token_storage.dart';
import 'api_exception.dart';

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
          if (!options.path.contains('/auth/login') && !options.path.contains('/auth/refresh-token')) {
            final token = await TokenStorage.instance.accessToken;
            if (token != null) {
              options.headers['Authorization'] = 'Bearer $token';
            }
          }
          return handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401 && !error.requestOptions.path.contains('/auth/')) {
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

  static const String baseUrl = 'https://api.noon.codefanz.com/api/carrier/v1';

  late final Dio _dio;
  Dio get dio => _dio;

  Future<String?> _refreshToken() async {
    final refreshToken = await TokenStorage.instance.refreshToken;
    if (refreshToken == null) return null;

    final freshDio = Dio(BaseOptions(baseUrl: baseUrl, headers: {'Accept': 'application/json'}));
    final response = await freshDio.post(
      '/auth/refresh-token',
      options: Options(headers: {'Authorization': 'Bearer $refreshToken'}),
    );
    final newAccessToken = response.data['data']?['access_token'] as String?;
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

  ApiException _toApiException(DioException e) {
    final data = e.response?.data;
    String message = 'Something went wrong. Please try again.';
    Map<String, dynamic>? errors;

    if (data is Map<String, dynamic>) {
      message = (data['message'] as String?) ?? message;
      final rawErrors = data['errors'] ?? data['data'];
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
