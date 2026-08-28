import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/commons/models/store.dart';

import 'package:value_market_customer/core/constants/hiveConstants.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:hive/hive.dart';

class StoreRepository {
  Future<List<Store>> getStores() async {
    try {
      final result = await Api.get(url: ApiURL.getStores, useAuthToken: false);

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((store) => Store.fromJson(Map.from(store ?? {})))
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  int getDefaultStoreId() {
    return Hive.box(settingsBoxKey).get(defaultStoreIdKey) ?? 0;
  }

  Future<void> setDefaultStoreId({required int storeId}) async {
    Hive.box(settingsBoxKey).put(defaultStoreIdKey, storeId);
  }
}
