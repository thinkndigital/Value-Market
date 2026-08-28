import 'package:flutter_bloc/flutter_bloc.dart';

import '../repositories/authRepository.dart';

abstract class OtpVerificationState {}

class OtpVerificationInitial extends OtpVerificationState {}

class OtpVerificationProgress extends OtpVerificationState {
  OtpVerificationProgress();
}

class OtpVerificationSuccess extends OtpVerificationState {
  final String sucsessMessage;
  OtpVerificationSuccess(this.sucsessMessage);
}

class OtpVerificationFailure extends OtpVerificationState {
  final String errorMessage;

  OtpVerificationFailure(this.errorMessage);
}

class OtpVerificationCubit extends Cubit<OtpVerificationState> {
  final AuthRepository authRepository = AuthRepository();

  OtpVerificationCubit()
      : super(OtpVerificationInitial()); 

  void verifyOtp({required Map<String, dynamic> params}) {
    emit(OtpVerificationProgress());

    authRepository.verifyOtp(params: params).then((value) =>
   
        emit(OtpVerificationSuccess(value))).catchError((e) {
 
      emit(OtpVerificationFailure(e.toString()));
    });
  }
}
