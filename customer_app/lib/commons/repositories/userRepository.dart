import 'package:eshop_plus/commons/models/userDetails.dart';
import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';

class UserRepository {
  Future<({String token, UserDetails userDetails})> verifyUser(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.verifyUser, useAuthToken: false);

      return (
        token: result['token'].toString(),
        userDetails: UserDetails.fromJson(Map.from(result['user'] ?? {}))
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString(),
            errorCode: e
                .errorCode); // Re-throw the API exception with the backend message
      } else {
        // Handle any other exceptions
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
