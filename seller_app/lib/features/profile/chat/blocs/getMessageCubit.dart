import 'package:eshopplus_seller/features/auth/repositories/authRepository.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getContactsCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/models/chatMessage.dart';
import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/features/profile/chat/repositories/chatRepository.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class GetMessageState {}

class GetMessageInitial extends GetMessageState {}

class GetMessageInProgress extends GetMessageState {}

class GetMessageSuccess extends GetMessageState {
  final List<ChatMessage> messages;
  final int total;

  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  GetMessageSuccess({
    required this.messages,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  GetMessageSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<ChatMessage>? messages,
  }) {
    return GetMessageSuccess(
      messages: messages ?? this.messages,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class GetMessageFailure extends GetMessageState {
  final String errorMessage;

  GetMessageFailure(this.errorMessage);
}

class GetMessageCubit extends Cubit<GetMessageState> {
  final ChatRepository _chatRepository = ChatRepository();

  GetMessageCubit() : super(GetMessageInitial());

  void getMessages({required Map<String, dynamic> params}) {
    emit(GetMessageInProgress());
    _chatRepository
        .getMessages(params: params)
        .then((value) => emit(GetMessageSuccess(
              messages: value.messages,
              fetchMoreError: false,
              fetchMoreInProgress: false,
              total: value.total,
            )))
        .catchError((e) {
      emit(GetMessageFailure(e.toString()));
    });
  }

  emitSuccessState(ChatMessage chatMessage) {
    List<ChatMessage> chatMessageList = [];
    if (state is GetMessageSuccess) {
      chatMessageList = (state as GetMessageSuccess).messages;
    }
    chatMessageList.insert(0, chatMessage);
    emit((state as GetMessageSuccess)
        .copyWith(messages: chatMessageList, total: chatMessageList.length));
  }

  bool fetchMoreError() {
    if (state is GetMessageSuccess) {
      return (state as GetMessageSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is GetMessageSuccess) {
      return (state as GetMessageSuccess).messages.length <
          (state as GetMessageSuccess).total;
    }
    return false;
  }

  void loadMore({
    required Map<String, dynamic> params,
  }) async {
    if (state is GetMessageSuccess) {
      if ((state as GetMessageSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as GetMessageSuccess).copyWith(fetchMoreInProgress: true));
        params.addAll({
          ApiURL.offsetApiKey: (state as GetMessageSuccess).messages.length
        });
        final moreFAQ = await _chatRepository.getMessages(params: params);

        final currentState = (state as GetMessageSuccess);

        List<ChatMessage> messages = currentState.messages;

        messages.addAll(moreFAQ.messages);

        emit(GetMessageSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreFAQ.total,
          messages: messages,
        ));
      } catch (e) {
        emit((state as GetMessageSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  void updateUnreadCount(GetContactsCubit getContactsCubit, int userId,
      List<ChatMessage> unreadMessages,
      {bool replaceList = false}) async {
    List<UserDetails> users = getContactsCubit.state is GetContactsSuccess
        ? (getContactsCubit.state as GetContactsSuccess).users
        : [];
    if (getContactsCubit.state is GetContactsSuccess) {
      int index = users.indexWhere((element) => element.id == userId);
      if (index != -1) {
        if (replaceList) {
          users[index].messages = unreadMessages;
        } else {
          users[index].messages!.addAll(unreadMessages);
        }
        if (state is GetMessageSuccess) {
          emit((state as GetMessageSuccess)
              .copyWith(messages: users[index].messages!.reversed.toList()));
        } else {
          getMessages(params: {ApiURL.idApiKey: userId});
        }
      } else {
        try {
          UserDetails user = await AuthRepository().getUserDetails(params: {
            ApiURL.idApiKey: userId.toString(),
          });
          users.insert(0, user);
          getContactsCubit.emit(GetContactsSuccess(users: users));
        } catch (e) {}
      }
    }
  }
}
