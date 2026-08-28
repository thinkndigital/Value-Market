import 'package:eshop_plus/ui/auth/repositories/authRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ResetPasswordState {}

class ResetPasswordInitial extends ResetPasswordState {}

class ResetPasswordInProgress extends ResetPasswordState {}

class ResetPasswordSuccess extends ResetPasswordState {
  final String successMessage;

  ResetPasswordSuccess({required this.successMessage});
}

class ResetPasswordFailure extends ResetPasswordState {
  final String errorMessage;

  ResetPasswordFailure(this.errorMessage);
}

class ResetPasswordCubit extends Cubit<ResetPasswordState> {
  final AuthRepository authRepository = AuthRepository();

  ResetPasswordCubit() : super(ResetPasswordInitial());

  void resetPassword({required Map<String, dynamic> params}) async {
    emit(ResetPasswordInProgress());
    authRepository
        .setNewPassword(params: params)
        .then((value) => emit(ResetPasswordSuccess(successMessage: value)))
        .catchError((e) {
      emit(ResetPasswordFailure(e.toString()));
    });
  }
}
