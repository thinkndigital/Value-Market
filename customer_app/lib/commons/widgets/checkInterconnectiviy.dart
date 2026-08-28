import 'package:connectivity_plus/connectivity_plus.dart';

class InternetConnectivity {
  static Future<bool> isUserOffline() async {
    final List<ConnectivityResult> connectivityResult =
        await Connectivity().checkConnectivity();
    if (connectivityResult.contains(ConnectivityResult.wifi) ||
        connectivityResult.contains(ConnectivityResult.mobile)) {
      return false;
    }
    return true;
  }
}
