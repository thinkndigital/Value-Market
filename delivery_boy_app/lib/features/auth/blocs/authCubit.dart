import 'package:eshoppro_deliveryboy/commons/models/userDetails.dart';
import 'package:eshoppro_deliveryboy/features/auth/repositories/authRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class AuthState {}

class AuthInitial extends AuthState {}

class Unauthenticated extends AuthState {}

class Authenticated extends AuthState {
  final String userMobile;
  final String token;

  Authenticated({required this.userMobile, required this.token});
}

class AuthCubit extends Cubit<AuthState> {
  final AuthRepository authRepository;

  AuthCubit(this.authRepository) : super(AuthInitial()) {
    _checkIsAuthenticated();
  }

  void _checkIsAuthenticated() {
    if (AuthRepository.getIsLogIn()) {
      emit(
        Authenticated(
            userMobile: AuthRepository.getUserMobile(),
            token: AuthRepository.getToken()),
      );
    } else {
      emit(Unauthenticated());
    }
  }

  void authenticateUser(
      {required UserDetails userDetails, required String token}) {
    authRepository.setToken(token);
    authRepository.setUserMobile(userDetails.mobile!);
    authRepository.setUserId(userDetails.id!);
    authRepository.setIsLogIn(true);

    emit(Authenticated(userMobile: userDetails.mobile!, token: token));
  }

  void signOut() {
    if (state is Authenticated) {
      authRepository.signOutUser();
      emit(Unauthenticated());
    }
  }

  String getUserMobile() {
    if (state is Authenticated) {
      return (state as Authenticated).userMobile;
    }

    return '';
  }
}
