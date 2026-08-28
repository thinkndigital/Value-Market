import 'dart:convert';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/utils/utils.dart';

class AddCategoryRepository {
  Future<String> addCategory({
    required String storeId,
    required Map<String, String> names,
    required String imagePath,
    required String bannerPath,
    String? parentId,
  }) async {
    final Map<String, dynamic> data = {
      ApiURL.storeIdApiKey: storeId,
      'name': names['en'] ?? '',
      'category_image': imagePath,
      'banner': bannerPath,
      if (parentId != null) 'parent_id': parentId
    };

    // Prepare translated_category_name
    final translated = Map.of(names)..remove('en');
    if (translated.isNotEmpty) {
      data['translated_category_name'] = jsonEncode(translated);
    }
    try {
      final result = await Api.post(
          body: data, url: ApiURL.addCategories, useAuthToken: true);
      return result[ApiURL.messageKey];
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }
}
