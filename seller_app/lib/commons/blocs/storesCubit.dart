import 'package:eshopplus_seller/commons/models/store.dart';
import 'package:eshopplus_seller/commons/repositories/storeRepository.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

abstract class StoresState {}

class StoresInitial extends StoresState {}

class StoresFetchInProgress extends StoresState {}

class StoresFetchSuccess extends StoresState {
  final List<Store> stores;
  final Store defaultStore;
  StoresFetchSuccess({required this.stores, required this.defaultStore});
}

class StoresFetchFailure extends StoresState {
  final String errorMessage;

  StoresFetchFailure(this.errorMessage);
}

class StoresCubit extends Cubit<StoresState> {
  final StoreRepository _storeRepository;

  StoresCubit(
    this._storeRepository,
  ) : super(StoresInitial());

  void setupStores({required List<Store> stores}) {
    if (stores.isEmpty) {
      return;
    }

    // Filter out stores with status 2
    List<Store> activeStores =
        stores.where((store) => store.storeStatus != 2).toList();

    // Return if no active stores are available
    if (activeStores.isEmpty) {
      emit(StoresFetchFailure(emptyAciveStoreErrorMsgKey));
      return;
    }

    ///[Default store set remotely by admin]
    final defaultStoreByAdminId = activeStores.firstWhere(
        (element) => element.isDefaultStore == 1,
        orElse: () => activeStores.first);

    ///[Check if store has been selected as default by user or not]
    if (_storeRepository.getDefaultStoreId() == 0 ||
        _storeRepository.getDefaultStoreId() == defaultStoreByAdminId.id &&
            defaultStoreByAdminId.storeStatus == 2) {
      // Set default store to the admin default or the first active store if admin's store is inactive
      _storeRepository.setDefaultStoreId(
          storeId: defaultStoreByAdminId.id ?? 0);
      activeStores.forEach((e) => e.isDefaultStore = 0);
      defaultStoreByAdminId.isDefaultStore = 1;
      emit(StoresFetchSuccess(
          stores: activeStores, defaultStore: defaultStoreByAdminId));
    } else {
      ///[If the default local store is inactive, set another store as default]
      final currentDefaultStoreId = _storeRepository.getDefaultStoreId();
      final currentDefaultStoreIndex =
          activeStores.indexWhere((store) => store.id == currentDefaultStoreId);

      if (currentDefaultStoreIndex == -1 ||
          activeStores[currentDefaultStoreIndex].storeStatus == 2) {
        // Set the new default store if the current default store is inactive
        _storeRepository.setDefaultStoreId(
            storeId: defaultStoreByAdminId.id ?? 0);
        activeStores.forEach((e) => e.isDefaultStore = 0);
        defaultStoreByAdminId.isDefaultStore = 1;
        emit(StoresFetchSuccess(
            stores: activeStores, defaultStore: defaultStoreByAdminId));
      } else {
        // Set the current default store as it is
        _storeRepository.setDefaultStoreId(
            storeId: activeStores[currentDefaultStoreIndex].id!);
        activeStores.forEach((e) => e.isDefaultStore = 0);
        activeStores[currentDefaultStoreIndex].isDefaultStore = 1;
        emit(StoresFetchSuccess(
            stores: activeStores,
            defaultStore: activeStores[currentDefaultStoreIndex]));
      }
    }
  }

  void fetchStores({List<Store>? stores}) {
    if (stores == null) emit(StoresFetchInProgress());
    _storeRepository.getStores().then((value) {
      setupStores(stores: value);
    }).catchError((e) {
      emit(StoresFetchFailure(e.toString()));
    });
  }

  Store getDefaultStore() {
    if (state is StoresFetchSuccess) {
      return (state as StoresFetchSuccess).defaultStore;
    }

    return Store.fromJson(Map.from({}));
  }

  changeDefaultStore(
      {required int storeId, required List<Store> stores}) async {
    _storeRepository.setDefaultStoreId(storeId: storeId);
    List<Store> updatedStores = List.from(stores);

    ///[Set defaultStore to 0 for all stores]
    for (var i = 0; i < stores.length; i++) {
      updatedStores[i] = updatedStores[i].copyWith(isDefaultStore: 0);
    }

    ///[Find the new default store index]
    final newDefaultStoreIdIndex =
        updatedStores.indexWhere((element) => element.id == storeId);

    if (newDefaultStoreIdIndex != -1) {
      updatedStores[newDefaultStoreIdIndex] =
          updatedStores[newDefaultStoreIdIndex].copyWith(isDefaultStore: 1);
      emit(StoresFetchSuccess(
          stores: updatedStores,
          defaultStore: updatedStores[newDefaultStoreIdIndex]));
    }
  }

  List<Store> getAllStores() {
    if (state is StoresFetchSuccess) {
      return (state as StoresFetchSuccess).stores;
    }

    return [];
  }

  resetState() {
    emit(StoresInitial());
  }
}
