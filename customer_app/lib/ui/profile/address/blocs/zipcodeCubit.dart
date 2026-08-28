import 'package:value_market_customer/ui/profile/address/repositories/addressRepository.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/zipcode.dart';

abstract class ZipcodeState {}

class ZipcodeInitial extends ZipcodeState {}

class ZipcodeFetchInProgress extends ZipcodeState {}

class ZipcodeFetchSuccess extends ZipcodeState {
  final int total;
  final List<Zipcode> zipcodes;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  ZipcodeFetchSuccess({
    required this.zipcodes,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  ZipcodeFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<Zipcode>? zipcodes,
  }) {
    return ZipcodeFetchSuccess(
      zipcodes: zipcodes ?? this.zipcodes,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class ZipcodeFetchFailure extends ZipcodeState {
  final String errorMessage;

  ZipcodeFetchFailure(this.errorMessage);
}

class ZipcodeCubit extends Cubit<ZipcodeState> {
  final AddressRepository _addressRepository = AddressRepository();

  ZipcodeCubit() : super(ZipcodeInitial());

  void getZipcodes({int? cityId, String search = ''}) async {
    emit(ZipcodeFetchInProgress());
    try {
      final result =
          await _addressRepository.getZipcodes(cityId: cityId, search: search);
      emit(ZipcodeFetchSuccess(
        zipcodes: result.zipcodes,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
      ));
    } catch (e) {
      emit(ZipcodeFetchFailure(e.toString()));
    }
  }

  List<Zipcode> getZipcodeList() {
    if (state is ZipcodeFetchSuccess) {
      return (state as ZipcodeFetchSuccess).zipcodes;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is ZipcodeFetchSuccess) {
      return (state as ZipcodeFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is ZipcodeFetchSuccess) {
      return (state as ZipcodeFetchSuccess).zipcodes.length <
          (state as ZipcodeFetchSuccess).total;
    }
    return false;
  }

  void loadMore({int? cityId, String search = ''}) async {
    if (state is ZipcodeFetchSuccess) {
      if ((state as ZipcodeFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit(
            (state as ZipcodeFetchSuccess).copyWith(fetchMoreInProgress: true));

        final moreZipcode = await _addressRepository.getZipcodes(
            cityId: cityId,
            search: search,
            offset: (state as ZipcodeFetchSuccess).zipcodes.length);

        final currentState = (state as ZipcodeFetchSuccess);

        List<Zipcode> zipcodes = currentState.zipcodes;

        zipcodes.addAll(moreZipcode.zipcodes);

        emit(ZipcodeFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreZipcode.total,
          zipcodes: zipcodes,
        ));
      } catch (e) {
        emit((state as ZipcodeFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }
}
