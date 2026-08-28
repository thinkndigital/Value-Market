
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../commons/models/userDetails.dart';
import '../../auth/repositories/authRepository.dart';

abstract class UpdateUserState {}

class UpdateUserInitial extends UpdateUserState {}

class UpdateUserFetchInProgress extends UpdateUserState {}

class UpdateUserFetchSuccess extends UpdateUserState {
  final UserDetails userDetails;
  final String successMessage;
  UpdateUserFetchSuccess(
      {required this.userDetails, required this.successMessage});
}

class UpdateUserFetchFailure extends UpdateUserState {
  final String errorMessage;

  UpdateUserFetchFailure(this.errorMessage);
}

class UpdateUserCubit extends Cubit<UpdateUserState> {
  final AuthRepository authRepository = AuthRepository();

  UpdateUserCubit() : super(UpdateUserInitial());

  void updateUser({required Map<String, dynamic> params}) {
    emit(UpdateUserFetchInProgress());
    authRepository
        .updateUser(params: params)
        .then((value) => emit(UpdateUserFetchSuccess(
            userDetails: value.userDetails,
            successMessage: value.successMessage)))
        .catchError((e) {
      emit(UpdateUserFetchFailure(e.toString()));
    });
  }
}
