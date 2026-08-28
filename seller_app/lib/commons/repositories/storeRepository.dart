import 'package:eshopplus_seller/commons/models/store.dart';
import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/core/constants/hiveConstants.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:hive/hive.dart';

class StoreRepository {
  //will call same method for both all stores and seller stores
  Future<List<Store>> getStores({bool getAllStores = false}) async {
    try {
      final result = await Api.get(
          url: getAllStores ? ApiURL.getStores : ApiURL.getSellerStores,
          useAuthToken: getAllStores ? false : true);

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((store) => Store.fromJson(Map.from(store ?? {})))
          .toList();
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future<({String successMessage, UserDetails userDetails})> addSellerStore({
    required Map<String, dynamic> params,
  }) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.addSellerStore, useAuthToken: true);

      return (
        successMessage: result[ApiURL.messageKey].toString(),
        userDetails:
            UserDetails.fromJson(Map.from(result[ApiURL.dataKey] ?? {}))
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  int getDefaultStoreId() {
    return Hive.box(settingsBoxKey).get(defaultStoreIdKey) ?? 0;
  }

  Future<void> setDefaultStoreId({required int storeId}) async {
    Hive.box(settingsBoxKey).put(defaultStoreIdKey, storeId);
  }
}
