import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/core/configs/appConfig.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/ui/auth/repositories/authRepository.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';

class SellerRepository {
  Future<({List<Seller> sellers, int total})> getSellers(
      {required int storeId, int? offset, List<int>? sellerIds}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getSellers,
          useAuthToken: false,
          queryParameters: {
            ApiURL.userIdApiKey: AuthRepository.getUserDetails().id,
            ApiURL.storeIdApiKey: storeId,
            ApiURL.offsetApiKey: offset ?? 0,
            ApiURL.limitApiKey: limit,
            if (sellerIds != []) ApiURL.sellerIdsApiKey: sellerIds?.join(','),
          });

      return (
        sellers: ((result[ApiURL.dataKey] ?? []) as List)
            .map((product) => Seller.fromJson(Map.from(product ?? {})))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString())
      );
    } catch (e, _) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<List<Seller>> getBestSellers(
      {required int storeId, int? offset}) async {
    try {
      final result = await Api.get(
          url: ApiURL.bestSellers,
          useAuthToken: false,
          queryParameters: {
            ApiURL.userIdApiKey: AuthRepository.getUserDetails().id,
            ApiURL.storeIdApiKey: storeId,
            ApiURL.offsetApiKey: offset ?? 0,
            ApiURL.limitApiKey: limit,
          });

      return (((result[ApiURL.dataKey] ?? []) as List)
          .map((product) => Seller.fromJson(Map.from(product ?? {})))
          .toList());
    } catch (e, _) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<List<Seller>> getFeaturedSellers({required int storeId}) async {
    try {
      final result = await Api.get(
          url: ApiURL.topSellers,
          useAuthToken: true,
          queryParameters: {
            ApiURL.userIdApiKey: AuthRepository.getUserDetails().id,
            ApiURL.storeIdApiKey: storeId
          });

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((seller) => Seller.fromJson(Map.from(seller ?? {})))
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
