import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../repositories/authRepository.dart';

abstract class SignUpState {}

class SignUpInitial extends SignUpState {}

class SignUpProgress extends SignUpState {
  SignUpProgress();
}

class SignUpSuccess extends SignUpState {
  final UserDetails userDetails;
  final String message;

  SignUpSuccess(this.userDetails, this.message);
}

class SignUpFailure extends SignUpState {
  final String errorMessage;

  SignUpFailure(this.errorMessage);
}

class SignUpCubit extends Cubit<SignUpState> {
  final AuthRepository authRepository = AuthRepository();

  SignUpCubit() : super(SignUpInitial());

  void signUpUser(
      {required Map<String, dynamic> params,
      required bool isEditProfileScreen}) {
    emit(SignUpProgress());

    authRepository
        .registerUser(params: params, isEditProfileScreen: isEditProfileScreen)
        .then((value) {
      emit(SignUpSuccess(
        value.userDetails,
        value.successMessage,
      ));
    }).catchError((e) {
      emit(SignUpFailure(e.toString()));
    });
  }
}
