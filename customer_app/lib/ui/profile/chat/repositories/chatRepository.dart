import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/ui/profile/chat/models/chatMessage.dart';
import 'package:value_market_customer/commons/models/userDetails.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';

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
      if (result['error']['status'] == 1) {
        throw ApiException(result['error']['message']);
      }
      return ChatMessage.fromJson(Map.from(result['message'] ?? {}));
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

  Future<void> makeSeenMessage({
    required Map<String, dynamic> params,
  }) async {
    try {
      await Api.post(
          url: ApiURL.chatifyMakeSeenApi, useAuthToken: true, body: params);
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

  Future<({List<ChatMessage> messages, int total})> getTicketMessages({
    required Map<String, dynamic> params,
  }) async {
    try {
      params.addAll({ApiURL.limitApiKey: limit});

      final result = await Api.get(
          url: ApiURL.getMessagesApi,
          useAuthToken: true,
          queryParameters: params);
      if (result[ApiURL.totalKey] == 0) {
        throw ApiException(dataNotAvailableKey);
      }
      return (
        messages: ((result[ApiURL.dataKey] ?? []) as List)
            .map((msg) => ChatMessage.fromTicketJson(Map.from(msg ?? {})))
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

  Future<ChatMessage> sendTicketMessage({
    required Map<String, dynamic> params,
  }) async {
    try {
      final result = await Api.post(
          url: ApiURL.sendMessageApi, useAuthToken: true, body: params);
      return ChatMessage.fromTicketJson(Map.from(result[ApiURL.dataKey] ?? {}));
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
