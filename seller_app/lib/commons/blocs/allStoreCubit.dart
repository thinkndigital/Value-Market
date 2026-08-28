import 'package:eshopplus_seller/commons/models/store.dart';
import 'package:eshopplus_seller/commons/repositories/storeRepository.dart';

import 'package:flutter/material.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

abstract class AllStoresState {}

class AllStoresInitial extends AllStoresState {}

class AllStoresFetchInProgress extends AllStoresState {}

class AllStoresFetchSuccess extends AllStoresState {
  final List<Store> stores;

  AllStoresFetchSuccess({
    required this.stores,
  });
}

class AllStoresFetchFailure extends AllStoresState {
  final String errorMessage;

  AllStoresFetchFailure(this.errorMessage);
}

class AllStoresCubit extends Cubit<AllStoresState> {
  final StoreRepository _storeRepository;

  AllStoresCubit(
    this._storeRepository,
  ) : super(AllStoresInitial());

  void fetchAllStores(BuildContext context) async {
    emit(AllStoresFetchInProgress());
    _storeRepository.getStores(getAllStores: true).then((value) {
      emit(AllStoresFetchSuccess(stores: value));
    }).catchError((e) {
      emit(AllStoresFetchFailure(e.toString()));
    });
  }

  List<Store> getAllAllStores() {
    if (state is AllStoresFetchSuccess) {
      return (state as AllStoresFetchSuccess).stores;
    }

    return [];
  }

  resetDefaultStore() {
    if (state is AllStoresFetchSuccess) {
      _storeRepository.setDefaultStoreId(
          storeId: (state as AllStoresFetchSuccess)
              .stores
              .firstWhere((element) => element.isDefaultStore == 1,
                  orElse: () => (state as AllStoresFetchSuccess).stores.first)
              .id!);
    }
  }
}
