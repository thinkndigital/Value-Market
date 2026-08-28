import 'dart:convert';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';

class AddBrandRepository {
  Future<String> addBrand({
    required String storeId,
    required Map<String, String> names,
    required String imagePath,
  }) async {
    final Map<String, dynamic> body = {
      'store_id': storeId,
      'brand_name': names['en'] ?? '',
      'image': imagePath,
      'translated_brand_name': jsonEncode(names..remove('en')),
    };
    final result = await Api.post(
      body: body,
      url: ApiURL.addBrands,
      useAuthToken: true,
    );

    return result[ApiURL.messageKey];
  }
}
