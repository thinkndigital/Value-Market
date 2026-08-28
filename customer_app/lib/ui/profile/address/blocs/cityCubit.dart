import 'package:value_market_customer/ui/profile/address/repositories/addressRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/city.dart';

abstract class CityState {}

class CityInitial extends CityState {}

class CityFetchInProgress extends CityState {}

class CityFetchSuccess extends CityState {
  final int total;
  final List<City> cities;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  CityFetchSuccess({
    required this.cities,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  CityFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<City>? cities,
  }) {
    return CityFetchSuccess(
      cities: cities ?? this.cities,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class CityFetchFailure extends CityState {
  final String errorMessage;

  CityFetchFailure(this.errorMessage);
}

class CityCubit extends Cubit<CityState> {
  final AddressRepository _addressRepository = AddressRepository();

  CityCubit() : super(CityInitial());

  void getCities({String search = ''}) async {
    emit(CityFetchInProgress());
    try {
      final result = await _addressRepository.getCities(search: search);

      emit(CityFetchSuccess(
        cities: result.citylist,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
      ));
    } catch (e) {
      emit(CityFetchFailure(e.toString()));
    }
  }

  List<City> getCityList() {
    if (state is CityFetchSuccess) {
      return (state as CityFetchSuccess).cities;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is CityFetchSuccess) {
      return (state as CityFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is CityFetchSuccess) {
      return (state as CityFetchSuccess).cities.length <
          (state as CityFetchSuccess).total;
    }
    return false;
  }

  void loadMore({String search = ''}) async {
    if (state is CityFetchSuccess) {
      if ((state as CityFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as CityFetchSuccess).copyWith(fetchMoreInProgress: true));

        final moreCity = await _addressRepository.getCities(
            search: search, offset: (state as CityFetchSuccess).cities.length);

        final currentState = (state as CityFetchSuccess);

        List<City> cities = currentState.cities;

        cities.addAll(moreCity.citylist);

        emit(CityFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreCity.total,
          cities: cities,
        ));
      } catch (e) {
        emit((state as CityFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }
}
