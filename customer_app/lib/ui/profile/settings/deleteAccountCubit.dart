import 'package:flutter_bloc/flutter_bloc.dart';

import '../../auth/repositories/authRepository.dart';

abstract class DeleteAccountState {}

class DeleteAccountInitial extends DeleteAccountState {}

class DeleteAccountProgress extends DeleteAccountState {}

class DeleteAccountFailure extends DeleteAccountState {
  final String errorMessage;
  DeleteAccountFailure(this.errorMessage);
}

class AccountDeleted extends DeleteAccountState {
  final String successMessage;
  AccountDeleted({required this.successMessage});
}

class DeleteAccountCubit extends Cubit<DeleteAccountState> {
  final AuthRepository authRepository = AuthRepository();
  DeleteAccountCubit() : super(DeleteAccountInitial());
  void deleteUserAccount(
      {required bool isSocialLogin,
      required Map<String, dynamic> params}) async {
    emit(DeleteAccountProgress());
    var result;
    try {
      if (isSocialLogin) {
        result = await authRepository.deleteSocialAccount();
      } else {
        result = await authRepository.deleteAccount(params: params);
      }
      authRepository.clearSessionData();
      emit(AccountDeleted(successMessage: result));
    } catch (e) {
      emit(DeleteAccountFailure(e.toString()));
    }
  }
}
