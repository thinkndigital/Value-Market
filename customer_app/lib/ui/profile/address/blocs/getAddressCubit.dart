import 'package:value_market_customer/ui/profile/address/models/address.dart';
import 'package:value_market_customer/ui/profile/address/repositories/addressRepository.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

abstract class GetAddressState {}

class GetAddressInitial extends GetAddressState {}

class GetAddressFetchInProgress extends GetAddressState {}

class GetAddressFetchSuccess extends GetAddressState {
  final List<Address> addresses;

  GetAddressFetchSuccess(this.addresses);
}

class GetAddressFetchFailure extends GetAddressState {
  final String errorMessage;

  GetAddressFetchFailure(this.errorMessage);
}

class GetAddressCubit extends Cubit<GetAddressState> {
  final AddressRepository _addressRepository = AddressRepository();

  GetAddressCubit() : super(GetAddressInitial());

  Future<void> getAddress() async {
    emit(GetAddressFetchInProgress());
    try {
      final response = await _addressRepository.getAddress();
      emit(GetAddressFetchSuccess(response));
    } catch (e) {
      emit(GetAddressFetchFailure(e.toString()));
    }
  }

  emitSuccessState(List<Address> addresses) {
    emit(GetAddressFetchSuccess(addresses));
  }

  List<Address> getAddressList() {
    if (state is GetAddressFetchSuccess) {
      return (state as GetAddressFetchSuccess).addresses;
    }
    return [];
  }
}
