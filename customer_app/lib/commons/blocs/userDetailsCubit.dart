import 'package:eshop_plus/commons/repositories/userRepository.dart';
import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/commons/models/userDetails.dart';

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
  final UserRepository userRepository = UserRepository();
  UserDetailsCubit() : super(UserDetailsInitial());

  //to fetch user details form remote
  void fetchUserDetails({required Map<String, dynamic> params}) async {
    emit(UserDetailsFetchInProgress());
    userRepository.verifyUser(params: params).then((value) {
      if (value.token.isEmpty) {
        resetUserDetailsState();
        return;
      }
      emit(UserDetailsFetchSuccess(
          userDetails: value.userDetails, token: value.token));
    }).catchError((e) {
      emit(UserDetailsFetchFailure(
          e.toString(), e is ApiException ? e.errorCode : 200));
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

  String? getUserType() {
    return getUserData('type');
  }

  UserDetails getuserDetails() {
    return getUserData('userDetails', defaultValue: UserDetails());
  }

  String getReferalCode() {
    return getUserData('referralCode') ?? '';
  }

  void updateuserWalletBalance(double balance) {
    if (state is UserDetailsFetchSuccess) {
      final oldUserDetails = (state as UserDetailsFetchSuccess).userDetails;
      oldUserDetails.balance = balance;
      emitUserSuccessState(
          oldUserDetails.toJson(), ((state as UserDetailsFetchSuccess).token));
    }
  }

  bool isGuestUser() {
    if (state is UserDetailsFetchSuccess) {
      return (state as UserDetailsFetchSuccess).userDetails.username == null;
    }
    return true;
  }

  bool isNotificationOn() {
    if (state is UserDetailsFetchSuccess) {
      return (state as UserDetailsFetchSuccess).userDetails.isNotificationOn ==
          1;
    }
    return false;
  }

  resetUserDetailsState() {
    final userDetails = UserDetails(
        id: 0,
        username: null,
        email: '',
        mobile: '',
        image: '',
        balance: 0,
        activationSelector: '',
        activationCode: '',
        forgottenPasswordSelector: '',
        forgottenPasswordCode: '',
        forgottenPasswordTime: '',
        rememberSelector: '',
        rememberCode: '',
        createdOn: '',
        lastLogin: '',
        active: 1,
        company: '',
        address: '',
        bonus: '',
        cashReceived: 0,
        dob: '',
        countryCode: 0,
        city: '',
        area: '',
        street: '',
        pincode: '',
        apikey: '',
        referralCode: '',
        friendsCode: '',
        fcmId: '',
        latitude: '',
        longitude: '',
        createdAt: '',
        type: '');
    emitUserSuccessState(userDetails.toJson(), '');
  }

  emitUserSuccessState(Map userDetails, String token) {
    UserDetails currentuserDetails =
        UserDetails.fromJson(userDetails as Map<String, dynamic>);
    emit(
        UserDetailsFetchSuccess(userDetails: currentuserDetails, token: token));
  }
}
