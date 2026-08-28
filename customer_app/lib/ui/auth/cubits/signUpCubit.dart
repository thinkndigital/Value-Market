import 'package:eshop_plus/commons/models/userDetails.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../repositories/authRepository.dart';

abstract class SignUpState {}

class SignUpInitial extends SignUpState {}

class SignUpProgress extends SignUpState {
  String loginType;
  SignUpProgress({required this.loginType});
}

class SignUpSuccess extends SignUpState {
  final UserDetails userDetails;
  final String token;
  SignUpSuccess({
    required this.userDetails,
    required this.token,
  });
}

class SignUpFailure extends SignUpState {
  final String errorMessage;

  SignUpFailure(this.errorMessage);
}

class SignUpCubit extends Cubit<SignUpState> {
  final AuthRepository authRepository = AuthRepository();

  SignUpCubit() : super(SignUpInitial());

  void signUpUser(String loginType) {
    emit(SignUpProgress(loginType: loginType));

    authRepository
        .signUp(loginType)
        .then((value) => emit(
            SignUpSuccess(userDetails: value.userDetails, token: value.token)))
        .catchError((e) {
      emit(SignUpFailure(e.toString()));
    });
  }
}
