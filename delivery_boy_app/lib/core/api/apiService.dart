import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:value_market_delivery_boy/core/localization/labelKeys.dart';
import 'package:value_market_delivery_boy/features/auth/repositories/authRepository.dart';
import 'package:value_market_delivery_boy/commons/repositories/settingsRepository.dart';
import 'package:value_market_delivery_boy/main.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';
import 'package:value_market_delivery_boy/core/configs/appConfig.dart';

import '../routes/routes.dart';
import '../../utils/internetConnectivity.dart';
import '../../utils/utils.dart';

class ApiException implements Exception {
  String errorMessage;
  final List<Map<String, dynamic>>? errorData;
  final int? errorCode;

  ApiException(this.errorMessage, {this.errorData, this.errorCode});

  @override
  String toString() {
    return errorMessage;
  }
}

class Api {
  static Map<String, dynamic> headers(bool useAuthToken) {
    String token = AuthRepository.getToken();
    Map<String, dynamic> headers = {
      "Language-Id": SettingsRepository().getCurrentAppLanguage().id,
    };
    if (!useAuthToken || token.isEmpty) {
      return headers;
    }
    headers['Authorization'] = "Bearer $token";
    return headers;
  }

  static callOnUnauthorized(
    String url, {
    String? message,
  }) {
    if ([ApiURL.verifyUser, ApiURL.login, ApiURL.updateFcm, ApiURL.updateUser]
        .contains(url)) {
      Utils.showSnackBar(
          message: unauthenticatedWarningKey,
          context: navigatorKey.currentContext!);
      Utils.navigateToScreen(navigatorKey.currentContext!, Routes.loginScreen,
          replaceAll: true);
    }
  }

  static Future<Map<String, dynamic>> post({
    required Map<String, dynamic> body,
    required String url,
    required bool useAuthToken,
    Map<String, dynamic>? queryParameters,
    CancelToken? cancelToken,
    Function(int, int)? onSendProgress,
    Function(int, int)? onReceiveProgress,
  }) async {
    try {
      if (await InternetConnectivity.isUserOffline()) {
        throw ApiException(noInternetKey);
      }
      final Dio dio = Dio();
      final FormData formData =
          FormData.fromMap(body, ListFormat.multiCompatible);
      final response = await dio.post(url,
          data: formData,
          queryParameters: queryParameters,
          cancelToken: cancelToken,
          onReceiveProgress: onReceiveProgress,
          onSendProgress: onSendProgress,
          options: Options(headers: headers(useAuthToken)));

      if ([
        ApiURL.chatifyFetchMessagesApi,
        ApiURL.chatifySendMessageApi,
        ApiURL.chatifyMakeSeenApi
      ].contains(url)) {
        return Map.from(response.data);
      }
      if (url == ApiURL.chatifyAuthAPI) {
        return jsonDecode(response.data);
      }
      if (response.data['error']) {
        if (response.data['code'] == 401) {
          callOnUnauthorized(url);
        }
        throw ApiException(
          SettingsRepository().getCurrentAppLanguage().code != null &&
                  SettingsRepository().getCurrentAppLanguage().code != 'en'
              ? response.data['language_message_key'] ??
                  response.data['message']
              : response.data['message'].toString(),
          errorCode: response.data['code'],
        );
      }
      if (SettingsRepository().getCurrentAppLanguage().code != null &&
          SettingsRepository().getCurrentAppLanguage().code != 'en' &&
          response.data.containsKey('language_message_key')) {
        response.data['message'] = response.data['language_message_key'];
      }
      return Map.from(response.data);
    } on DioException catch (e) {
      if (e.response != null) {
// The request was made and the server responded with a status code
        if (e.response!.statusCode == 500) {
// Handle the 500 error
          throw ApiException(internalServerErrorMessageKey);
        }
        if (e.response!.statusCode == 401) {
// Handle the 401 error
          callOnUnauthorized(url, message: e.response!.data['message']);
        }
      } else {
// Something happened in setting up the request or an error occurred before the response
        throw ApiException(e.error is SocketException
            ? noInternetKey
            : e.response?.data['message']);
      }

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data['message']);
//throw ApiException(e.error is SocketException ? noInternetKey: e.response?.data['message']);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage, errorCode: e.errorCode);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }

  static errMsg(var getdata) {
    Map map = getdata;

    if (!map.containsKey("data") && !map.containsKey("message")) {
      return;
    }

    if (map.containsKey("data")) {
      if (map['data'].runtimeType == List && (map['data'] as List).isEmpty) {
        return map['messge'];
      }
      Map data = getdata['data'];
      if (data.containsKey('errors')) {
        return getApiMessage(data['errors']);
      } else if (getdata.containsKey('message')) {
        return getdata['message'];
      } else {
        return defaultErrorMessageKey;
      }
    } else if (map.containsKey("message")) {
      return map['message'];
    } else {
      return defaultErrorMessageKey;
    }
  }

  static String getApiMessage(var message, {bool withkey = false}) {
    String apimsg = '';
    if (message is String) {
      return apimsg = message;
    } else {
      message.forEach((k, v) {
        if (v is List<dynamic>) {
          apimsg = "$apimsg${withkey ? "$k: " : ""}${v.first}\n";
        } else {
          apimsg = "${apimsg + (withkey ? "$k: " : "") + v}\n";
        }
      });
    }
    return apimsg;
  }

  static Future<Map<String, dynamic>> get({
    required String url,
    required bool useAuthToken,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      if (await InternetConnectivity.isUserOffline()) {
        throw ApiException(noInternetKey);
      }
      final Dio dio = Dio(
        BaseOptions(
          baseUrl: databaseUrl,
          headers: headers(useAuthToken),
          contentType: 'application/json',
        ),
      );
      final response = await dio.get(url,
          queryParameters: queryParameters,
          options: Options(headers: headers(useAuthToken)));
      if ([
        ApiURL.getProductRating,
        ApiURL.chatifySearchApi,
        ApiURL.chatifyGetContactsApi
      ].contains(url)) {
        return Map.from(response.data);
      }
      if (response.data['error']) {
        if (response.data['code'] == 401) {
          callOnUnauthorized(url);
        }
        throw ApiException(SettingsRepository().getCurrentAppLanguage().code !=
                    null &&
                SettingsRepository().getCurrentAppLanguage().code != 'en'
            ? response.data['language_message_key'] ?? response.data['message']
            : response
                .data['message']); // response.data['message'].toString());
      }
      if (SettingsRepository().getCurrentAppLanguage().code != null &&
          SettingsRepository().getCurrentAppLanguage().code != 'en' &&
          response.data.containsKey('language_message_key')) {
        response.data['message'] = response.data['language_message_key'];
      }
      return Map.from(response.data);
    } on DioException catch (e) {
      if (e.response != null) {
// The request was made and the server responded with a status code
        if (e.response!.statusCode == 500) {
// Handle the 500 error
          throw ApiException(internalServerErrorMessageKey);
        }
        if (e.response!.statusCode == 401) {
// Handle the 401 error
          callOnUnauthorized(url, message: e.response!.data['message']);
        }
      }

      throw ApiException(
          e.error is SocketException ? noInternetKey : defaultErrorMessageKey);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage, errorCode: e.errorCode);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }

  static Future<Map<String, dynamic>> put({
    Map<String, dynamic>? body,
    required String url,
    required bool useAuthToken,
    Map<String, dynamic>? queryParameters,
    CancelToken? cancelToken,
    Function(int, int)? onSendProgress,
    Function(int, int)? onReceiveProgress,
  }) async {
    try {
      if (await InternetConnectivity.isUserOffline()) {
        throw const SocketException(noInternetKey);
      }
      final Dio dio = Dio();
      final FormData formData =
          FormData.fromMap(body ?? {}, ListFormat.multiCompatible);
      final response = await dio.put(url,
          data: formData,
          queryParameters: queryParameters,
          cancelToken: cancelToken,
          onReceiveProgress: onReceiveProgress,
          onSendProgress: onSendProgress,
          options: Options(headers: headers(useAuthToken)));
      if (response.data['error']) {
        if (response.data['code'] == 401) {
          callOnUnauthorized(url);
        }
        throw ApiException(SettingsRepository().getCurrentAppLanguage().code !=
                    null &&
                SettingsRepository().getCurrentAppLanguage().code != 'en'
            ? response.data['language_message_key'] ?? response.data['message']
            : response.data['message']); //response.data['message'].toString());
      }
      if (SettingsRepository().getCurrentAppLanguage().code != null &&
          SettingsRepository().getCurrentAppLanguage().code != 'en' &&
          response.data.containsKey('language_message_key')) {
        response.data['message'] =
            response.data['language_message_key'] ?? response.data['message'];
      }
      return Map.from(response.data);
    } on DioException catch (e) {
      if (e.response != null) {
// The request was made and the server responded with a status code
        if (e.response!.statusCode == 500) {
// Handle the 500 error
          throw ApiException(internalServerErrorMessageKey);
        }
        if (e.response!.statusCode == 401) {
// Handle the 401 error
          callOnUnauthorized(url, message: e.response!.data['message']);
        }
      }

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data['message']);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }

  static Future<Map<String, dynamic>> delete({
    required String url,
    required bool useAuthToken,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      if (await InternetConnectivity.isUserOffline()) {
        throw ApiException(noInternetKey);
      }
      final Dio dio = Dio();

      final response = await dio.delete(url,
          queryParameters: queryParameters,
          options: Options(headers: headers(useAuthToken)));
      if (response.data['error']) {
        if (response.data['code'] == 401) {
          callOnUnauthorized(url);
        }
        throw ApiException(SettingsRepository().getCurrentAppLanguage().code !=
                    null &&
                SettingsRepository().getCurrentAppLanguage().code != 'en'
            ? response.data['language_message_key'] ?? response.data['message']
            : response
                .data['message']); // response.data['message'].toString());
      }
      if (SettingsRepository().getCurrentAppLanguage().code != null &&
          SettingsRepository().getCurrentAppLanguage().code != 'en' &&
          response.data.containsKey('language_message_key')) {
        response.data['message'] = response.data['language_message_key'];
      }
      return Map.from(response.data);
    } on DioException catch (e) {
      if (e.response != null) {
// The request was made and the server responded with a status code
        if (e.response!.statusCode == 500) {
// Handle the 500 error
          throw ApiException(internalServerErrorMessageKey);
        }
        if (e.response!.statusCode == 401) {
// Handle the 401 error
          callOnUnauthorized(url, message: e.response!.data['message']);
        }
      }

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data['message']);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }

  static Future<void> download(
      {required String url,
      required CancelToken cancelToken,
      required String savePath,
      required Function updateDownloadedPercentage}) async {
    try {
      if (await InternetConnectivity.isUserOffline()) {
        throw const SocketException(noInternetKey);
      }
      final Dio dio = Dio();
      await dio.download(url, savePath, cancelToken: cancelToken,
          onReceiveProgress: ((count, total) {
        updateDownloadedPercentage((count / total) * 100);
      }));
    } on DioException catch (e) {
      throw ApiException(
          e.error is SocketException ? noInternetKey : defaultErrorMessageKey);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }

  static Future<String> getHtmlContent({
    required String url,
    required bool useAuthToken,
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      if (await InternetConnectivity.isUserOffline()) {
        throw ApiException(noInternetKey);
      }

//
      final Dio dio = Dio();
      final response = await dio.get(url,
          queryParameters: queryParameters,
          options: Options(headers: headers(useAuthToken)));

      return response.data;
    } on DioException catch (e) {
      if (e.response != null) {
// The request was made and the server responded with a status code
        if (e.response!.statusCode == 500) {
// Handle the 500 error
          throw ApiException(internalServerErrorMessageKey);
        }
        if (e.response!.statusCode == 401) {
// Handle the 401 error
          callOnUnauthorized(url, message: e.response!.data['message']);
        }
      }

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data['message']);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }
}
