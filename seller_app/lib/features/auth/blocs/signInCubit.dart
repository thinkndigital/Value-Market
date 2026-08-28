import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/features/auth/repositories/authRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SignInState {}

class SignInInitial extends SignInState {}

class SignInProgress extends SignInState {}

class SignInSuccess extends SignInState {
  final UserDetails userDetails;
  final String token;
  SignInSuccess({
    required this.userDetails,
    required this.token,
  });
}

class SignInFailure extends SignInState {
  final String errorMessage;

  SignInFailure(this.errorMessage);
}

class SignInCubit extends Cubit<SignInState> {
  final AuthRepository authRepository = AuthRepository();

  SignInCubit() : super(SignInInitial());

  void login({required Map<String, dynamic> params}) async {
    emit(SignInProgress());
    authRepository.loginUser(params: params).then((value) {
      emit(SignInSuccess(
          userDetails: value['userDetails'], token: value['token']));
    }).catchError((e) {
      emit(SignInFailure(e.toString()));
    });
  }
}
