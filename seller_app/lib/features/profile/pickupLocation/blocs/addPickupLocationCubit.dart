import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../models/location.dart';

abstract class AddPickupLocationState {}

class AddPickupLocationInitial extends AddPickupLocationState {}

class AddPickupLocationProgress extends AddPickupLocationState {
  AddPickupLocationProgress();
}

class AddPickupLocationSuccess extends AddPickupLocationState {
  String successMsg;
  Location location;
  AddPickupLocationSuccess(this.successMsg, this.location);
}

class AddPickupLocationFailure extends AddPickupLocationState {
  final String errorMessage;
  AddPickupLocationFailure(this.errorMessage);
}

class AddPickupLocationCubit extends Cubit<AddPickupLocationState> {
  AddPickupLocationCubit() : super(AddPickupLocationInitial());

  addLocation(
    BuildContext context,
    Map<String, String?> parameter,
  ) {
    emit(AddPickupLocationProgress());
    addLocationProcess(context, parameter).then((value) {
      parameter['id'] = value[ApiURL.dataKey]['address']['id'].toString();
      Location location = Location.fromJson(parameter);
      emit(AddPickupLocationSuccess(value["message"], location));
    }).catchError((e) {
      emit(AddPickupLocationFailure(e.toString()));
    });
  }

  Future addLocationProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.post(
          url: ApiURL.addPickupLocation, useAuthToken: true, body: parameter);
      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
