import 'package:eshop_plus/ui/auth/repositories/authRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class GenerateReferCodeState {}

class GenerateReferCodeInitial extends GenerateReferCodeState {}

class GenerateReferCodeFetchInProgress extends GenerateReferCodeState {}

class GenerateReferCodeFetchSuccess extends GenerateReferCodeState {
  final String referCode;

  GenerateReferCodeFetchSuccess(this.referCode);
}

class GenerateReferCodeFetchFailure extends GenerateReferCodeState {
  final String errorMessage;

  GenerateReferCodeFetchFailure(this.errorMessage);
}

class GenerateReferCodeCubit extends Cubit<GenerateReferCodeState> {
  final AuthRepository _authRepository = AuthRepository();

  GenerateReferCodeCubit() : super(GenerateReferCodeInitial());

  void getGenerateReferCode() {
    emit(GenerateReferCodeFetchInProgress());

    _authRepository
        .generateReferralCode()
        .then((value) => emit(GenerateReferCodeFetchSuccess(value)))
        .catchError((e) {
      emit(GenerateReferCodeFetchFailure(e.toString()));
    });
  }
}
