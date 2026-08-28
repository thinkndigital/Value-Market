import 'package:dio/dio.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/features/profile/chat/models/chatMessage.dart';
import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';

class ChatRepository {
  Future<({List<ChatMessage> messages, int total})> getMessages({
    required Map<String, dynamic> params,
  }) async {
    try {
      params.addAll({ApiURL.limitApiKey: limit});

      final result = await Api.post(
          url: ApiURL.chatifyFetchMessagesApi,
          useAuthToken: true,
          body: params);
      if (result[ApiURL.totalKey] == 0) {
        throw ApiException(dataNotAvailableKey);
      }
      return (
        messages: ((result['messages'] ?? []) as List)
            .map((msg) => ChatMessage.fromJson(Map.from(msg ?? {})))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
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

  Future<List<UserDetails>> getContacts({String? search}) async {
    try {
      String url = search != null && search.isNotEmpty
          ? ApiURL.chatifySearchApi
          : ApiURL.chatifyGetContactsApi;
      final result = await Api.get(
          url: url, useAuthToken: true, queryParameters: {'input': search});

      return url == ApiURL.chatifyGetContactsApi
          ? ((result['contacts'] ?? []) as List)
              .map((user) => UserDetails.fromJson(Map.from(user ?? {})))
              .toList()
          : ((result['records'] ?? []) as List)
              .map((user) => UserDetails.fromJson(Map.from(user ?? {})))
              .toList();
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

  Future<ChatMessage> sendMessage({
    required Map<String, dynamic> params,
  }) async {
    try {
      final result = await Api.post(
          url: ApiURL.chatifySendMessageApi, useAuthToken: true, body: params);
      if (result[ApiURL.errorKey]['status'] == 1) {
        throw ApiException(result[ApiURL.errorKey][ApiURL.messageKey]);
      }
      return ChatMessage.fromJson(Map.from(result[ApiURL.messageKey] ?? {}));
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

  Future<void> downloadFile(
      {required String url,
      required String savePath,
      required CancelToken cancelToken,
      required Function updateDownloadedPercentage}) async {
    try {
      await Api.download(
          cancelToken: cancelToken,
          url: url,
          savePath: savePath,
          updateDownloadedPercentage: updateDownloadedPercentage);
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future<void> makeSeenMessage({
    required Map<String, dynamic> params,
  }) async {
    try {
      await Api.post(
          url: ApiURL.chatifyMakeSeenApi, useAuthToken: true, body: params);
      ;
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
