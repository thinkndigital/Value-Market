import 'package:eshoppro_deliveryboy/commons/models/userDetails.dart';
import 'package:eshoppro_deliveryboy/features/auth/repositories/authRepository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

@immutable
abstract class UserDetailsState {}

class UserDetailsInitial extends UserDetailsState {}

class UserDetailsFetchInProgress extends UserDetailsState {}

class UserDetailsFetchSuccess extends UserDetailsState {
  final UserDetails userDetails;
  final String token;
  UserDetailsFetchSuccess({required this.userDetails, required this.token});
}

class UserDetailsFetchFailure extends UserDetailsState {
  final String errorMessage;
  final int? errorCode;
  UserDetailsFetchFailure(this.errorMessage, this.errorCode);
}

class UserDetailsCubit extends Cubit<UserDetailsState> {
  final AuthRepository authRepository = AuthRepository();
  UserDetailsCubit() : super(UserDetailsInitial());

//to fetch user details form remote
  void fetchUserDetails({required Map<String, dynamic> params}) async {
    emit(UserDetailsFetchInProgress());

    authRepository.getUserDetails(params: params).then((value) {
      emit(UserDetailsFetchSuccess(
          userDetails: value, token: AuthRepository.getToken()));
    }).catchError((e) {
      emit(UserDetailsFetchFailure(e.toString(), e.errorCode));
    });
  }

  getUserData(String returnValue, {dynamic defaultValue}) {
    if (state is UserDetailsFetchSuccess) {
      switch (returnValue) {
        case 'name':
          return (state as UserDetailsFetchSuccess).userDetails.username;
        case 'userId':
          return (state as UserDetailsFetchSuccess).userDetails.id ?? 0;

        case 'status':
          return (state as UserDetailsFetchSuccess).userDetails.active;

        case 'mobileNumber':
          return (state as UserDetailsFetchSuccess).userDetails.mobile;
        case 'email':
          return (state as UserDetailsFetchSuccess).userDetails.email;
        case 'profileUrl':
          return (state as UserDetailsFetchSuccess).userDetails.image;

        case 'type':
          return (state as UserDetailsFetchSuccess).userDetails.type;

        case 'referralCode':
          return (state as UserDetailsFetchSuccess).userDetails.referralCode;

        case 'userDetails':
          return (state as UserDetailsFetchSuccess).userDetails;
      }
    }

    return defaultValue ?? "";
  }

  String? getUserName() {
    return getUserData('name');
  }

  int getUserId() {
    return getUserData('userId', defaultValue: 0);
  }

  String getUserStatus() {
    return getUserData('status', defaultValue: '0');
  }

  String getUserMobile() {
    return getUserData('mobileNumber') ?? '';
  }

  String getUserEmail() {
    return getUserData('email');
  }

  String getuserDetailsPicture() {
    return getUserData('profileUrl');
  }

  UserDetails getuserDetails() {
    return getUserData('userDetails', defaultValue: UserDetails());
  }

  bool isNotificationOn() {
    if (state is UserDetailsFetchSuccess) {
      return (state as UserDetailsFetchSuccess).userDetails.isNotificationOn ==
          1;
    }
    return false;
  }

  void updateuserDetailsUrl(String profileUrl) {
    if (state is UserDetailsFetchSuccess) {
      final oldUserDetails = (state as UserDetailsFetchSuccess).userDetails;

      emitUserSuccessState(oldUserDetails.copyWith(image: profileUrl).toJson(),
          ((state as UserDetailsFetchSuccess).token));
    }
  }

  void updateuserDetails({String? name, String? mobile, String? email}) {
    if (state is UserDetailsFetchSuccess) {
      UserDetails oldUserDetails =
          (state as UserDetailsFetchSuccess).userDetails;
      UserDetails userDetails = oldUserDetails.copyWith(
        email: email,
        mobile: mobile,
        username: name,
      );

      emitUserSuccessState(
          userDetails.toJson(), ((state as UserDetailsFetchSuccess).token));
    }
  }

  emitUserSuccessState(Map userDetails, String token) {
    UserDetails currentuserDetails =
        UserDetails.fromJson(userDetails as Map<String, dynamic>);
    emit(
        UserDetailsFetchSuccess(userDetails: currentuserDetails, token: token));
  }
}
