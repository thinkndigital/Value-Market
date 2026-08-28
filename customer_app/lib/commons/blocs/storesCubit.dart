import 'package:value_market_customer/commons/models/store.dart';
import 'package:value_market_customer/commons/repositories/storeRepository.dart';
import 'package:value_market_customer/core/constants/hiveConstants.dart';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hive/hive.dart';

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

  void fetchStores({List<Store>? stores}) async {
    if (stores == null) {
      emit(StoresFetchInProgress());
    }
    _storeRepository.getStores().then((value) {
      setupStores(stores: value);
    }).catchError((e) {
      emit(StoresFetchFailure(e.toString()));
    });
  }

  void setupStores({required List<Store> stores}) {
    if (stores.isEmpty) {
      return;
    }

    ///[Default store set remotly by admin ]
    final defaultStoreByAdminId = stores.firstWhere(
        (element) => element.isStoreDefault(),
        orElse: () => stores.first);

    ///[Check if store has been selcetd as default by user or not]
    if (_storeRepository.getDefaultStoreId() == 0) {
      _storeRepository.setDefaultStoreId(
          storeId: defaultStoreByAdminId.id ?? 0);
      emit(StoresFetchSuccess(
          stores: stores, defaultStore: defaultStoreByAdminId));
    } else {
      ///[If default local store and default remote store is same]
      if (defaultStoreByAdminId.id == _storeRepository.getDefaultStoreId()) {
        emit(StoresFetchSuccess(
            stores: stores, defaultStore: defaultStoreByAdminId));
      } else {
        List<Store> updatedStores = List.from(stores);

        ///[Set defaultStore to 0 for all stores]
        for (var i = 0; i < updatedStores.length; i++) {
          updatedStores[i] = updatedStores[i].copyWith(isDefaultStore: 0);
        }

        ///[Find the new default store index]
        final newDefaultStoreIdIndex = updatedStores.indexWhere(
            (element) => element.id == _storeRepository.getDefaultStoreId());

        if (newDefaultStoreIdIndex != -1) {
          updatedStores[newDefaultStoreIdIndex] =
              updatedStores[newDefaultStoreIdIndex].copyWith(isDefaultStore: 1);
          emit(StoresFetchSuccess(
              stores: updatedStores,
              defaultStore: updatedStores[newDefaultStoreIdIndex]));
        } else {
          emit(StoresFetchSuccess(
              stores: stores, defaultStore: defaultStoreByAdminId));
        }
      }
    }
  }

  Store getDefaultStore() {
    if (state is StoresFetchSuccess) {
      List<Store> stores = (state as StoresFetchSuccess).stores;
      if (stores.isEmpty) {
        return Store.fromJson(Map.from({}));
      }
      return stores.where((element) {
        return element.isStoreDefault();
      }).first;
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
      Hive.box(favoritesBoxKey).clear();
      emit(StoresFetchSuccess(
          stores: updatedStores,
          defaultStore: updatedStores[newDefaultStoreIdIndex]));
    }
  }

  // resetState() {
  //   if (state is StoresFetchSuccess) {
  //     Hive.box(favoritesBoxKey).clear();
  //     changeDefaultStore(
  //         storeId: (state as StoresFetchSuccess)
  //             .stores
  //             .firstWhere((element) => element.panelDefaultStore == 1)
  //             .id!,
  //         stores: (state as StoresFetchSuccess).stores);
  //   }
  // }

  List<Store> getAllStores() {
    if (state is StoresFetchSuccess) {
      return (state as StoresFetchSuccess).stores;
    }

    return [];
  }
}
