import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/core/configs/appConfig.dart';
import 'package:eshop_plus/ui/home/brand/brand.dart';

import 'package:eshop_plus/core/localization/labelKeys.dart';

class BrandRepository {
  Future<({List<Brand> brands, int total})> getBrands(
      {required int storeId, int? offset, String? brandIds}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getBrands,
          useAuthToken: false,
          queryParameters: {
            ApiURL.storeIdApiKey: storeId,
            ApiURL.offsetApiKey: offset ?? 0,
            ApiURL.limitApiKey: limit * 2,
            if (brandIds != null) ApiURL.idsApiKey: brandIds
          });

      return (
        brands: (((result[ApiURL.dataKey] ?? [])) as List)
            .map((e) => Brand.fromJson(e))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString())
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
