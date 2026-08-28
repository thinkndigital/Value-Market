import 'dart:convert';
import 'dart:io';

import 'package:curl_logger_dio_interceptor/curl_logger_dio_interceptor.dart';
import 'package:dio/dio.dart';
import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/main.dart';
import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/ui/auth/repositories/authRepository.dart';
import 'package:eshop_plus/commons/repositories/settingsRepository.dart';

import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/commons/widgets/checkInterconnectiviy.dart';
import 'package:eshop_plus/utils/utils.dart';

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
    // Do NOT set Content-Type here; let Dio handle it for FormData
    // headers['Content-Type'] = "multipart/form-data";
    return headers;
  }

  static callOnUnauthorized(
    String url, {
    String? message,
  }) {
    if ([
      ApiURL.verifyUser,
      ApiURL.registerUser,
      ApiURL.updateFcm,
      ApiURL.updateUser
    ].contains(url)) {
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
      dio.interceptors.add(CurlLoggerDioInterceptor(
          printOnSuccess: true, convertFormData: true));
      final response = await dio.post(url,
          data: formData,
          queryParameters: queryParameters,
          cancelToken: cancelToken,
          onReceiveProgress: onReceiveProgress,
          onSendProgress: onSendProgress,
          options: Options(headers: headers(useAuthToken)));
      if ([
        ApiURL.checkCartProductsDelivarable,
        ApiURL.validatePromoCode,
        ApiURL.chatifyFetchMessagesApi,
        ApiURL.chatifySendMessageApi,
        ApiURL.chatifyMakeSeenApi
      ].contains(url)) {
        return Map.from(response.data);
      }
      if (url == ApiURL.chatifyAuthAPI) {
        return jsonDecode(response.data);
      }
      if (response.data[ApiURL.errorKey]) {
        if (response.data[ApiURL.codeKey] == 401) {
          callOnUnauthorized(url);
        }
        throw ApiException(
          SettingsRepository().getCurrentAppLanguage().code != null &&
                  SettingsRepository().getCurrentAppLanguage().code !=
                      englishLangCode
              ? response.data[ApiURL.languageMessageKey]
              : response.data[ApiURL.messageKey].toString(),
          errorCode: response.data[ApiURL.codeKey],
        );
      }
      if (SettingsRepository().getCurrentAppLanguage().code != null &&
          SettingsRepository().getCurrentAppLanguage().code !=
              englishLangCode &&
          response.data.containsKey(ApiURL.languageMessageKey)) {
        response.data[ApiURL.messageKey] =
            response.data[ApiURL.languageMessageKey];
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
          callOnUnauthorized(url, message: e.response!.data[ApiURL.messageKey]);
        }
      } else {
        // Something happened in setting up the request or an error occurred before the response
        throw ApiException(e.error is SocketException
            ? noInternetKey
            : e.response?.data[ApiURL.messageKey]);
      }
      json.encode(e.response?.data);

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data[ApiURL.messageKey]);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage, errorCode: e.errorCode);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
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
      final Dio dio = Dio();
      dio.interceptors.add(CurlLoggerDioInterceptor(
          printOnSuccess: true, convertFormData: true));
      final response = await dio.get(url,
          queryParameters: queryParameters,
          options: Options(headers: headers(useAuthToken)));

      if ([
        ApiURL.getUserCart,
        ApiURL.getProductRating,
        ApiURL.getComboProductRating,
        ApiURL.chatifySearchApi,
        ApiURL.chatifySearchApi,
        ApiURL.chatifyGetContactsApi
      ].contains(url)) {
        return Map.from(response.data);
      }

      if (response.data[ApiURL.errorKey]) {
        if (response.data[ApiURL.codeKey] == 401) {
          callOnUnauthorized(
            url,
          );
        }
        throw ApiException(
          SettingsRepository().getCurrentAppLanguage().code != null &&
                  SettingsRepository().getCurrentAppLanguage().code !=
                      englishLangCode
              ? response.data[ApiURL.languageMessageKey]
              : response.data[ApiURL.messageKey].toString(),
          errorCode: response.data[ApiURL.codeKey],
        );
      }
      if (SettingsRepository().getCurrentAppLanguage().code != null &&
          SettingsRepository().getCurrentAppLanguage().code !=
              englishLangCode &&
          response.data.containsKey(ApiURL.languageMessageKey)) {
        response.data[ApiURL.messageKey] =
            response.data[ApiURL.languageMessageKey];
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
          callOnUnauthorized(url, message: e.response!.data[ApiURL.messageKey]);
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
      if (response.data[ApiURL.errorKey]) {
        if (response.data[ApiURL.codeKey] == 401) {
          callOnUnauthorized(url);
        }
        throw ApiException(
            SettingsRepository().getCurrentAppLanguage().code != null &&
                    SettingsRepository().getCurrentAppLanguage().code !=
                        englishLangCode
                ? response.data[ApiURL.languageMessageKey]
                : response.data[ApiURL.messageKey].toString());
      }
      if (SettingsRepository().getCurrentAppLanguage().code != null &&
          SettingsRepository().getCurrentAppLanguage().code !=
              englishLangCode &&
          response.data.containsKey(ApiURL.languageMessageKey)) {
        response.data[ApiURL.messageKey] =
            response.data[ApiURL.languageMessageKey];
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
          callOnUnauthorized(url, message: e.response!.data[ApiURL.messageKey]);
        }
      }

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data[ApiURL.messageKey]);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage, errorCode: e.errorCode);
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

      if (response.data[ApiURL.errorKey]) {
        if (response.data[ApiURL.codeKey] == 401) {
          callOnUnauthorized(url);
        }
        throw ApiException(
            SettingsRepository().getCurrentAppLanguage().code != null &&
                    SettingsRepository().getCurrentAppLanguage().code !=
                        englishLangCode
                ? response.data[ApiURL.languageMessageKey]
                : response.data[ApiURL.messageKey].toString());
      }
      if (SettingsRepository().getCurrentAppLanguage().code != null &&
          SettingsRepository().getCurrentAppLanguage().code !=
              englishLangCode &&
          response.data.containsKey(ApiURL.languageMessageKey)) {
        response.data[ApiURL.messageKey] =
            response.data[ApiURL.languageMessageKey];
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
          callOnUnauthorized(url, message: e.response!.data[ApiURL.messageKey]);
        }
      }

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data[ApiURL.messageKey]);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage, errorCode: e.errorCode);
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
      if (e.response != null) {
        // The request was made and the server responded with a status code
        if (e.response!.statusCode == 500) {
          // Handle the 500 error
          throw ApiException(internalServerErrorMessageKey);
        }
        if (e.response!.statusCode == 401) {
          // Handle the 401 error
          callOnUnauthorized(url, message: e.response!.data[ApiURL.messageKey]);
        }
      }

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data[ApiURL.messageKey]);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage, errorCode: e.errorCode);
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
      }

      throw ApiException(e.error is SocketException
          ? noInternetKey
          : e.response?.data[ApiURL.messageKey]);
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage);
    } catch (e) {
      throw ApiException(defaultErrorMessageKey);
    }
  }
}
