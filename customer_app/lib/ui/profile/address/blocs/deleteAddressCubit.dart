import 'package:eshop_plus/ui/profile/address/repositories/addressRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/address.dart';

abstract class DeleteAddressState {}

class DeleteAddressInitial extends DeleteAddressState {}

class DeleteAddressInProgress extends DeleteAddressState {
  final List<Address> addresses;
  DeleteAddressInProgress(this.addresses);
}

class DeleteAddressSuccess extends DeleteAddressState {
  final int id;
  final String successMessage;
  DeleteAddressSuccess({required this.id, required this.successMessage});
}

class DeleteAddressFailure extends DeleteAddressState {
  final int id;
  final String errorMessage;
  DeleteAddressFailure({required this.id, required this.errorMessage});
}

class DeleteAddressCubit extends Cubit<DeleteAddressState> {
  final AddressRepository _addressRepository = AddressRepository();
  DeleteAddressCubit() : super(DeleteAddressInitial());

  void deleteAddress(
      {required int addressId, required List<Address> addresses}) {
    addresses
        .firstWhere((element) => element.id == addressId)
        .deleteInProgress = true;
    emit(DeleteAddressInProgress(addresses));
    _addressRepository.deleteAddress(addressId: addressId).then((value) {
      emit(DeleteAddressSuccess(id: addressId, successMessage: value));
    }).catchError((e) {
      emit(DeleteAddressFailure(id: addressId, errorMessage: e.toString()));
    });
  }

  resetState() {
    emit(DeleteAddressInitial());
  }
}
