import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/ui/home/featuredSection/fesaturedSection.dart';

import 'package:value_market_customer/ui/auth/repositories/authRepository.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';

class SectionRepository {
  Future<List<FeaturedSection>> getSections(
      {required int storeId, String? zipcode}) async {
    try {
      Map<String, dynamic>? params = {
        ApiURL.storeIdApiKey: storeId,
        ApiURL.userIdApiKey: AuthRepository.getUserDetails().id,
      };
      if (zipcode != null && zipcode.isNotEmpty) {
        params[ApiURL.zipCodeApiKey] = zipcode;
      }
      final result = await Api.get(
          url: ApiURL.getSections, useAuthToken: true, queryParameters: params);

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((section) => FeaturedSection.fromJson(
              Map.from(section ?? {}), zipcode != null ? true : false))
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
