import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/core/constants/hiveConstants.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'package:hive_flutter/hive_flutter.dart';

class AuthRepository {
  Future<void> signOutUser(BuildContext context) async {
    String fcm = await AuthRepository.getFcmToken();
    try {
      await updateFcmId({ApiURL.fcmIdApiKey: fcm, 'is_delete': 1});

      removeSessionData();
    } catch (e) {
      Utils.showSnackBar(message: e.toString());
    }
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
    return Hive.box(authBoxKey).get(userIdKey) ?? 0;
  }

  removeSessionData() {
    Hive.box(authBoxKey).clear();
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
        'userDetails':
            UserDetails.fromJson(Map.from(result[ApiURL.dataKey] ?? {}))
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
        successMessage: result[ApiURL.messageKey].toString(),
        userDetails:
            UserDetails.fromJson(Map.from(result[ApiURL.dataKey] ?? {}))
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
        userDetails:
            UserDetails.fromJson(Map.from(result[ApiURL.dataKey] ?? {}))
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

      return UserDetails.fromJson(Map.from(result[ApiURL.dataKey] ?? {}));
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
        successMessage: result[ApiURL.messageKey].toString(),
        userDetails:
            UserDetails.fromJson(Map.from(result[ApiURL.dataKey] ?? {}))
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
          url: ApiURL.deleteSellerAccount,
          useAuthToken: true,
          queryParameters: params);

      return result[ApiURL.messageKey];
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
    } catch (e) {}
  }

  Future<String> setNewPassword({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.resetPassword, useAuthToken: false);

      return result[ApiURL.messageKey];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}

Future<String> setNewPassword({required Map<String, dynamic> params}) async {
  try {
    final result = await Api.post(
        body: params, url: ApiURL.resetPassword, useAuthToken: false);

    return result[ApiURL.messageKey];
  } catch (e) {
    if (e is ApiException) {
      throw ApiException(e.toString());
    } else {
      throw ApiException(defaultErrorMessageKey);
    }
  }
}
