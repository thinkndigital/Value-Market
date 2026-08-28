import 'package:eshopplus_seller/features/profile/chat/models/chatMessage.dart';
import 'package:eshopplus_seller/features/profile/chat/repositories/chatRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SendMessageState {}

class SendMessageInitial extends SendMessageState {}

class SendMessageInProgress extends SendMessageState {}

class SendMessageSuccess extends SendMessageState {
  final ChatMessage chatMessage;
  SendMessageSuccess({required this.chatMessage});
}

class SendMessageFailure extends SendMessageState {
  final String errorMessage;

  SendMessageFailure(this.errorMessage);
}

class SendMessageCubit extends Cubit<SendMessageState> {
  final ChatRepository _chatRepository = ChatRepository();

  SendMessageCubit() : super(SendMessageInitial());

  void sendMessage({required Map<String, dynamic> params}) {
    emit(SendMessageInProgress());
    _chatRepository
        .sendMessage(params: params)
        .then((value) => emit(SendMessageSuccess(chatMessage: value)))
        .catchError((e) {
      emit(SendMessageFailure(e.toString()));
    });
  }
}
