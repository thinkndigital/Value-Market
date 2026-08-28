import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/ui/profile/promoCode/models/promoCode.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';

class PromoCodeRepository {
  Future<List<PromoCode>> getPromoCodes({required int storeId}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getPromoCodes,
          useAuthToken: true,
          queryParameters: {ApiURL.storeIdApiKey: storeId});

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((code) => PromoCode.fromJson(Map.from(code ?? {})))
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e
            .toString()); // Re-throw the API exception with the backend message
      } else {
        // Handle any other exceptions
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<PromoCode> validatePromoCode(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.validatePromoCode, useAuthToken: true);
      if (!result['error']) {
        return PromoCode.fromJson(Map.from(result[ApiURL.dataKey][0] ?? {}));
      }
      throw ApiException(result['message'],
          errorData: [result[ApiURL.dataKey]]);
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString(),
            errorData: e
                .errorData); // Re-throw the API exception with the backend message
      } else {
        // Handle any other exceptions
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
