import 'dart:async';
import 'dart:math';
import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/core/configs/appConfig.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:eshop_plus/commons/models/userDetails.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

/// Repository class handling all authentication related operations
class AuthRepository {
  // Constants
  static const String _chars =
      'AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz1234567890';

  // Dependencies
  final GoogleSignIn _googleSignIn = GoogleSignIn();
  final FirebaseAuth _firebaseAuth = FirebaseAuth.instance;
  final Random _rnd = Random();

  // Hive box getters
  Box get _authBox => Hive.box(authBoxKey);
  Box get _productsBox => Hive.box(productsBoxKey);
  Box get _promocodeBox => Hive.box(promocodeBoxKey);

  /// Signs out the current user and cleans up session data
  Future<void> signOutUser(BuildContext context, String userType) async {
    try {
      final fcm = await getFcmToken();
      await updateFcmId({ApiURL.fcmIdApiKey: fcm, ApiURL.isDeleteApiKey: 1});

      // Sign out from respective providers
      await _firebaseAuth.signOut();
      if (userType == googleLoginType) {
        await _googleSignIn.signOut();
      }

      await clearSessionData();
    } catch (e) {
      Utils.showSnackBar(message: e.toString(), context: context);
    }
  }

  /// Clears all session related data from Hive storage
  Future<void> clearSessionData() async {
    await Future.wait([
      _authBox.delete(isLogInKey),
      _authBox.delete(userDetailsKey),
      _authBox.delete(tokenKey),
      _authBox.delete(defaultStoreIdKey),
      // _settingsBox.clear(),
      _productsBox.clear(),
      _promocodeBox.clear(),
    ]);
  }

  /// Returns whether user is logged in
  static bool getIsLogIn() {
    return Hive.box(authBoxKey).get(isLogInKey) ?? false;
  }

  /// Sets the login status
  Future<void> setIsLogIn(bool value) async {
    return _authBox.put(isLogInKey, value);
  }

  /// Returns current user details
  static UserDetails getUserDetails() {
    final userMap = Hive.box(authBoxKey).get(userDetailsKey);
    return UserDetails.fromJson(Map<String, dynamic>.from(userMap ?? {}));
  }

  /// Saves user details to storage
  Future<void> setUserDetails(UserDetails value) async {
    return _authBox.put(userDetailsKey, value.toJson());
  }

  /// Saves authentication token
  Future<void> setToken(String value) async {
    return _authBox.put(tokenKey, value);
  }

  /// Returns current authentication token
  static String getToken() {
    return Hive.box(authBoxKey).get(tokenKey) ?? '';
  }

  /// Retrieves FCM token for push notifications
  static Future<String> getFcmToken() async {
    try {
      return await FirebaseMessaging.instance.getToken() ?? '';
    } catch (e) {
      debugPrint('Error getting FCM token: $e');
      return '';
    }
  }

  Future<String> registerUser({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.registerUser, useAuthToken: false);

      return result[ApiURL.messageKey];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<({String token, UserDetails userDetails})> signUp(
      String loginType) async {
    try {
      String fcm = await AuthRepository.getFcmToken();
      User? user;
      if (loginType == googleLoginType) {
        user = await signInWithGoogle();
      } else {
        user = await signInWithApple();
      }
      if (user != null) {
        final result = await Api.post(
          url: ApiURL.signUp,
          useAuthToken: false,
          body: {
            ApiURL.nameApiKey: user.displayName ?? '',
            ApiURL.emailApiKey:
                user.email ?? user.providerData.first.email ?? '',
            ApiURL.imageApiKey: user.photoURL ?? '',
            ApiURL.mobileApiKey: user.phoneNumber ?? '',
            ApiURL.fcmIdApiKey: fcm,
            ApiURL.typeApiKey: loginType
          },
        );
        return (
          token: result[ApiURL.tokenKey].toString(),
          userDetails:
              UserDetails.fromJson(Map.from(result[ApiURL.dataKey] ?? {}))
        );
      }
      throw ApiException(defaultErrorMessageKey);
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<Map<String, dynamic>> loginUser(
      {required Map<String, dynamic> params}) async {
    try {
      final result =
          await Api.post(body: params, url: ApiURL.login, useAuthToken: false);

      return {
        'token': result[ApiURL.tokenKey],
        'userDetails': UserDetails.fromJson(Map.from(result['user'] ?? {}))
      };
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
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
            UserDetails.fromJson(Map.from(result[ApiURL.dataKey][0] ?? {}))
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<String> verifyOtp({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.verifyOtp, useAuthToken: false);

      return result[ApiURL.messageKey];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  /// Resends OTP for verification
  Future<String> resendOtp({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
        body: params,
        url: ApiURL.resendOtp,
        useAuthToken: false,
      );
      return result[ApiURL.messageKey] ?? '';
    } on ApiException {
      rethrow;
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  /// Sets new password for user
  Future<String> setNewPassword({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
        body: params,
        url: ApiURL.resetPassword,
        useAuthToken: false,
      );
      return result[ApiURL.messageKey] ?? '';
    } on ApiException {
      rethrow;
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  /// Handles Google Sign In process
  Future<User?> signInWithGoogle() async {
    try {
      final googleAccount = await _googleSignIn.signIn();
      if (googleAccount == null) return null;

      final googleAuth = await googleAccount.authentication;
      final credential = GoogleAuthProvider.credential(
        accessToken: googleAuth.accessToken,
        idToken: googleAuth.idToken,
      );

      final userCredential =
          await _firebaseAuth.signInWithCredential(credential);
      return userCredential.user;
    } on FirebaseAuthException catch (e) {
      switch (e.code) {
        case 'account-exists-with-different-credential':
          throw ApiException(accountExistGoogleErrorMessageKey);
        case 'invalid-credential':
          throw ApiException(errorInCredentialsErrorMessageKey);
        default:
          throw ApiException(errorInSiginnGoogleErrorMessageKey);
      }
    } catch (e) {
      throw ApiException(errorInSiginnGoogleErrorMessageKey);
    }
  }

  /// Handles Apple Sign In process
  Future<User?> signInWithApple() async {
    try {
      final appleCredential = await SignInWithApple.getAppleIDCredential(
        scopes: [
          AppleIDAuthorizationScopes.email,
          AppleIDAuthorizationScopes.fullName,
        ],
      );

      final oauthCredential = OAuthProvider('apple.com').credential(
        idToken: appleCredential.identityToken,
        accessToken: appleCredential.authorizationCode,
      );

      final userCredential =
          await _firebaseAuth.signInWithCredential(oauthCredential);

      if (userCredential.additionalUserInfo?.isNewUser ?? false) {
        final user = userCredential.user;
        if (user != null) {
          final displayName = [
            appleCredential.givenName ?? '',
            appleCredential.familyName ?? ''
          ].where((name) => name.isNotEmpty).join(' ');

          if (displayName.isNotEmpty) {
            await user.updateDisplayName(displayName);
            await user.reload();
          }
        }
      }

      return _firebaseAuth.currentUser;
    } on SignInWithAppleAuthorizationException catch (e) {
      switch (e.code) {
        case AuthorizationErrorCode.canceled:
          throw ApiException(appleLoginCancelledErrorMsg);
        default:
          throw ApiException(defaultErrorMessageKey);
      }
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }

  /// Updates FCM token for push notifications
  Future<void> updateFcmId(Map<String, dynamic> params) async {
    try {
      await Api.put(
        url: ApiURL.updateFcm,
        queryParameters: params,
        useAuthToken: true,
      );
    } on ApiException {
      // Silently handle API exceptions for FCM updates
      debugPrint('Failed to update FCM token');
    } catch (e) {
      if (e is ApiException) {
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  /// Deletes user account
  Future<String> deleteAccount({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
        url: ApiURL.deleteUserAccount,
        useAuthToken: true,
        body: params,
      );
      return result[ApiURL.messageKey] ?? '';
    } on ApiException {
      rethrow;
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  /// Deletes social media linked account
  Future<String> deleteSocialAccount() async {
    final currentUser = _firebaseAuth.currentUser;
    if (currentUser == null) {
      throw ApiException(requireRecentLoginErrorMessageKey);
    }

    try {
      await currentUser.delete();
      final result = await Api.delete(
        url: ApiURL.deleteSocialAccount,
        useAuthToken: true,
      );

      return result[ApiURL.messageKey] ?? '';
    } on FirebaseAuthException catch (e) {
      if (e.code == 'requires-recent-login') {
        throw ApiException(requireRecentLoginErrorMessageKey);
      }
      throw ApiException(defaultErrorMessageKey);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }

  /// Generates a unique referral code
  Future<String> generateReferralCode() async {
    const int maxAttempts = 5;
    const int codeLength = 8;
    int attempts = 0;

    while (attempts < maxAttempts) {
      try {
        final referCode = _generateRandomString(codeLength);
        final isValid = await validateReferal(referCode: referCode);

        if (!isValid) {
          return referCode;
        }
        attempts++;
      } on TimeoutException {
        throw ApiException(defaultErrorMessageKey);
      }
    }
    throw ApiException(
        'Failed to generate unique referral code after $maxAttempts attempts');
  }

  /// Generates a random string of specified length
  String _generateRandomString(int length) => String.fromCharCodes(
        Iterable.generate(
          length,
          (_) => _chars.codeUnitAt(_rnd.nextInt(_chars.length)),
        ),
      );

  /// Validates if a referral code is available
  static Future<bool> validateReferal({
    required String referCode,
  }) async {
    final result = await Api.post(
        url: ApiURL.validateReferCode,
        useAuthToken: true,
        body: {
          ApiURL.referralCodeApiKey: referCode,
        });
    return result[ApiURL.errorKey];
  }
}
