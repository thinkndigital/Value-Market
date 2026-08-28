import 'package:eshop_plus/ui/profile/address/models/address.dart';
import 'package:eshop_plus/ui/profile/address/repositories/addressRepository.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

abstract class AddNewAddressState {}

class AddNewAddressInitial extends AddNewAddressState {}

class AddNewAddressFetchInProgress extends AddNewAddressState {}

class AddNewAddressFetchSuccess extends AddNewAddressState {
  final Address address;
  final String successMessage;
  AddNewAddressFetchSuccess(
      {required this.address, required this.successMessage});
}

class AddNewAddressFetchFailure extends AddNewAddressState {
  final String errorMessage;

  AddNewAddressFetchFailure(this.errorMessage);
}

class AddNewAddressCubit extends Cubit<AddNewAddressState> {
  final AddressRepository _addressRepository = AddressRepository();

  AddNewAddressCubit() : super(AddNewAddressInitial());

  Future<void> addAddress({required Map<String, dynamic> params}) async {
    emit(AddNewAddressFetchInProgress());

    await _addressRepository
        .addAddress(params: params)
        .then((value) => emit(AddNewAddressFetchSuccess(
            address: value.address, successMessage: value.successMessage)))
        .catchError((e) {
      emit(AddNewAddressFetchFailure(e.toString()));
    });
  }
}
