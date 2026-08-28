import 'package:flutter_bloc/flutter_bloc.dart';

import '../repositories/authRepository.dart';

abstract class RegisterUserState {}

class RegisterUserInitial extends RegisterUserState {}

class RegisterUserProgress extends RegisterUserState {
  RegisterUserProgress();
}

class RegisterUserSuccess extends RegisterUserState {
  final String sucsessMessage;
  RegisterUserSuccess(this.sucsessMessage);
}

class RegisterUserFailure extends RegisterUserState {
  final String errorMessage;

  RegisterUserFailure(this.errorMessage);
}

class RegisterUserCubit extends Cubit<RegisterUserState> {
  final AuthRepository authRepository = AuthRepository();

  RegisterUserCubit() : super(RegisterUserInitial()); //cubit initialization

  void registerUser({required Map<String, dynamic> params}) {
    emit(RegisterUserProgress());

    authRepository.registerUser(params: params).then((value) =>
     
        emit(RegisterUserSuccess(value))).catchError((e) {
   
      emit(RegisterUserFailure(e.toString()));
    });
  }
}
