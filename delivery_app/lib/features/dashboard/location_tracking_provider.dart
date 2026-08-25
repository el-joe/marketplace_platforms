import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import 'dashboard_repository.dart';

/// Location updates are rate-limited server-side to 1 per 30s — this
/// notifier never sends more often than that.
const _updateInterval = Duration(seconds: 30);

class LocationTrackingNotifier extends StateNotifier<String?> {
  final DashboardRepository _repository;
  StreamSubscription<Position>? _positionSub;
  DateTime? _lastSentAt;

  LocationTrackingNotifier(this._repository) : super(null);

  Future<bool> _ensurePermission() async {
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.deniedForever) {
      state = 'Location permission permanently denied. Enable it from app settings to go on shift.';
      return false;
    }
    if (permission == LocationPermission.denied) {
      state = 'Location permission is required to go on shift.';
      return false;
    }
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      state = 'Location services are disabled. Please enable GPS.';
      return false;
    }
    return true;
  }

  Future<void> start() async {
    if (_positionSub != null) return;
    final granted = await _ensurePermission();
    if (!granted) return;

    state = null;
    _positionSub = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, distanceFilter: 25),
    ).listen((position) => _onPosition(position), onError: (_) {
      state = 'Unable to read device location.';
    });
  }

  void _onPosition(Position position) {
    final now = DateTime.now();
    if (_lastSentAt != null && now.difference(_lastSentAt!) < _updateInterval) return;
    _lastSentAt = now;
    _repository.updateLocation(position.latitude, position.longitude).catchError((_) {});
  }

  void stop() {
    _positionSub?.cancel();
    _positionSub = null;
    _lastSentAt = null;
  }

  @override
  void dispose() {
    _positionSub?.cancel();
    super.dispose();
  }
}

final locationTrackingProvider = StateNotifierProvider<LocationTrackingNotifier, String?>(
  (ref) => LocationTrackingNotifier(DashboardRepository()),
);
