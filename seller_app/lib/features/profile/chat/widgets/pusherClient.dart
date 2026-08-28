import 'dart:convert';

import 'package:eshopplus_seller/features/auth/repositories/authRepository.dart';
import 'package:eshopplus_seller/main.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getMessageCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/models/chatMessage.dart';
import 'package:eshopplus_seller/features/profile/chat/screens/chatScreen.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class PusherService {
  final PusherChannelsFlutter pusher = PusherChannelsFlutter.getInstance();

  late String socketId;
  PusherChannel? channel;

  Future<void> initPusher(
      BuildContext context, GetMessageCubit getMessageCubit) async {
    try {
      await pusher.init(
        apiKey: context.read<SettingsAndLanguagesCubit>().getPusherAppKey(),
        cluster: context.read<SettingsAndLanguagesCubit>().getPusherCluster(),
        onConnectionStateChange: (currentState, previousState) async {
          if (currentState == 'CONNECTED') {
            socketId = await pusher.getSocketId();
          }
        },
        onError: (message, code, exception) {},
        onSubscriptionSucceeded: (channelName, data) {},
        onEvent: (event) {
          if (event.eventName == 'messaging' &&
              jsonDecode(event.data)['from_id'].toString() ==
                  currentChatUserId.toString()) {
            getMessageCubit.emitSuccessState(ChatMessage.fromJson(
                jsonDecode(event.data)[ApiURL.messageKey]));
          }
        },
      );

      await pusher.connect();
      pusherChannel = await pusher.subscribe(
          channelName:
              context.read<SettingsAndLanguagesCubit>().getPusherChannerName());
    } catch (e) {}
  }

  disconnectPusher(String channelName) {
    pusher.unsubscribe(channelName: channelName);
    pusher.disconnect();
  }

  Future<void> sendMessage(String message) async {
    await Api.post(
        url: ApiURL.chatifySendMessageApi,
        body: {
          "from_id": AuthRepository.getUserId(),
          "id": 1,
          "type": "user",
          "message": message
        },
        useAuthToken: true);
  }
}
