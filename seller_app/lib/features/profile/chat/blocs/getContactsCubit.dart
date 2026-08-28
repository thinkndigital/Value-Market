import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/features/profile/chat/repositories/chatRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class GetContactsState {}

class GetContactsInitial extends GetContactsState {}

class GetContactsInProgress extends GetContactsState {}

class GetContactsSuccess extends GetContactsState {
  final List<UserDetails> users;

  GetContactsSuccess({required this.users});
}

class GetContactsFailure extends GetContactsState {
  final String errorMessage;

  GetContactsFailure(this.errorMessage);
}

class GetContactsCubit extends Cubit<GetContactsState> {
  final ChatRepository _chatRepository = ChatRepository();

  GetContactsCubit() : super(GetContactsInitial());

  void getContactss({
    String? search,
  }) {
    emit(GetContactsInProgress());
    _chatRepository
        .getContacts(search: search)
        .then((value) => emit(GetContactsSuccess(users: value)))
        .catchError((e) {
      emit(GetContactsFailure(e.toString()));
    });
  }
}
