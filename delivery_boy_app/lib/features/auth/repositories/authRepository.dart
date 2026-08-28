import 'package:value_market_delivery_boy/core/constants/hiveConstants.dart';
import 'package:value_market_delivery_boy/commons/models/userDetails.dart';
import 'package:value_market_delivery_boy/core/localization/labelKeys.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';
import 'package:hive_flutter/hive_flutter.dart';

import '../../../core/api/apiService.dart';

class AuthRepository {
  Future<void> signOutUser() async {
    removeSessionData();
  }

  static bool getIsLogIn() {
    return Hive.box(authBoxKey).get(isLogInKey) ?? false;
  }

  Future<void> setIsLogIn(bool value) async {
    return Hive.box(authBoxKey).put(isLogInKey, value);
  }

  Future<void> setToken(String value) async {
    return Hive.box(authBoxKey).put(tokenKey, value);
  }

  static String getToken() {
    return Hive.box(authBoxKey).get(tokenKey) ?? '';
  }

  Future<void> setUserMobile(String value) async {
    return Hive.box(authBoxKey).put(userMobileKey, value);
  }

  static String getUserMobile() {
    return Hive.box(authBoxKey).get(userMobileKey) ?? '';
  }

  Future<void> setUserId(int value) async {
    return Hive.box(authBoxKey).put(userIdKey, value);
  }

  static int getUserId() {
    return Hive.box(authBoxKey).get(userIdKey) ?? '';
  }

  removeSessionData() {
    Hive.box(authBoxKey).delete(isLogInKey);
    Hive.box(authBoxKey).delete(userDetailsKey);
    Hive.box(authBoxKey).delete(tokenKey);
    Hive.box(authBoxKey).delete(defaultStoreIdKey);
    Hive.box(settingsBoxKey).delete(pushNotificationHiveKey);
    Hive.box(settingsBoxKey).delete(defaultStoreIdKey);
  }

  static Future<String> getFcmToken() async {
    try {
      return (await FirebaseMessaging.instance.getToken()) ?? "";
    } catch (e) {
      return "";
    }
  }

  Future<Map<String, dynamic>> loginUser(
      {required Map<String, dynamic> params}) async {
    try {
      final result =
          await Api.post(body: params, url: ApiURL.login, useAuthToken: false);

      return {
        'token': result['token'],
        'userDetails': UserDetails.fromJson(Map.from(result['data'] ?? {}))
      };
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<({String successMessage, UserDetails userDetails})> registerUser(
      {required Map<String, dynamic> params,
      required bool isEditProfileScreen}) async {
    try {
      var result;
      if (isEditProfileScreen) {
        result = await Api.post(
            body: params, url: ApiURL.updateUser, useAuthToken: true);
      } else {
        result = await Api.post(
            body: params, url: ApiURL.register, useAuthToken: false);
      }
      return (
        successMessage: result['message'].toString(),
        userDetails: UserDetails.fromJson(Map.from(result['data'] ?? {}))
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<({String token, UserDetails userDetails})> verifyUser(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.get(
          queryParameters: params, url: ApiURL.verifyUser, useAuthToken: false);

      return (
        token: result['token'].toString(),
        userDetails: UserDetails.fromJson(Map.from(result['data'] ?? {}))
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

  Future<UserDetails> getUserDetails(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.get(
          queryParameters: params,
          url: ApiURL.getUserDetails,
          useAuthToken: true);

      return UserDetails.fromJson(result['data'] ?? {});
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

  Future<({String successMessage, UserDetails userDetails})> updateUser(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.updateUser, useAuthToken: true);

      return (
        successMessage: result['message'].toString(),
        userDetails: UserDetails.fromJson(result['data'].runtimeType is List
            ? Map.from(result['data'][0] ?? {})
            : Map.from(result['data'] ?? {}))
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<String> deleteAccount({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.delete(
          url: ApiURL.deleteUserAccount,
          useAuthToken: true,
          queryParameters: params);

      return result['message'];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<void> updateFcmId(Map<String, dynamic> params) async {
    try {
      await Api.put(
          url: ApiURL.updateFcm, queryParameters: params, useAuthToken: true);
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<String> setNewPassword({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.resetPassword, useAuthToken: false);

      return result['message'];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

/*
Future<UserDetails> loginUser(
{required String email, required String password}) async {
try {
final result = await Api.post(body: {
"email": email,
"password": password,
"login_type": appLoginType,
"fcm_token": await getFcmToken(),
}, url: ApiURL.login, useAuthToken: false);

if (result.containsKey("data")) {
return UserDetails.fromJson(Map.from(result['data'] ?? {}));
}
throw ApiException(result['message']);
} on ApiException catch (e) {
throw ApiException(e.toString());
} catch (e) {
throw ApiException(defaultErrorMessageKey);
}
}

Future<User?> verifyFirebaseOTP(
{required AuthCredential authCredential}) async {
try {
return (await FirebaseAuth.instance.signInWithCredential(authCredential))
.user;
} on FirebaseAuthException catch (e) {
throw ApiException(e.code == "invalid-verification-code"
? incorrectOTPKey
: defaultErrorMessageKey);
} catch (e) {
throw ApiException(defaultErrorMessageKey);
}
}

Future<void> setNewPassword(
{required String email, required String password}) async {
try {
await Api.post(
body: {"email": email, "new_password": password},
url: ApiURL.setNewPassword,
useAuthToken: false);
} catch (e) {
throw ApiException(e.toString());
}
}

Future<void> changePassword(
{required String currentPassword,
required String newPassword,
required String newConfirmPassword}) async {
try {
await Api.post(body: {
"current_password": currentPassword,
"new_password": newPassword,
"new_confirm_password": newConfirmPassword,
}, url: ApiURL.setNewPassword, useAuthToken: true);
} catch (e) {
throw ApiException(e.toString());
}
}

Future<Map<String, dynamic>> checkUserExist(
{required String email, required String mobile}) async {
try {
final userExist = await Api.get(
url: ApiURL.checkUserExist,
queryParameters: {"email": email, "mobile": mobile},
useAuthToken: false);

return {
"userExist": userExist['is_user_exist'],
"emailTaken": userExist['email_taken'] ?? false,
"mobileTaken": userExist['mobile_taken'] ?? false
};
} catch (e) {
throw ApiException(e.toString());
}
}



Future<void> logout() async {
try {
await Api.get(
url: ApiURL.logout,
useAuthToken: true,
queryParameters: {"login_type": appLoginType});
} catch (e) {
throw ApiException(e.toString());
}
}

Future<void> deleteAccount() async {
try {
await Api.get(url: ApiURL.deleteUserAccount, useAuthToken: true);
} catch (e) {
throw ApiException(e.toString());
}
}

*/
}
