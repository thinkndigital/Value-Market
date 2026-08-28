import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/ui/home/categorySlider/categorySlider.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import '../models/category.dart';

class CategoryRepository {
  Future<({List<Category> categories, int total})> getCategories(
      {required int storeId,
      int? offset,
      String? search,
      int? categoryId,
      String? categoryIds}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getCategories,
          useAuthToken: true,
          queryParameters: {
            ApiURL.storeIdApiKey: storeId,
            ApiURL.limitApiKey: limit,
            ApiURL.offsetApiKey: offset ?? 0,
            if (categoryId != null) ApiURL.idApiKey: categoryId,
            if (search != null) ApiURL.searchApiKey: search,
            if (categoryIds != null) ApiURL.idsApiKey: categoryIds
          });
      List<Category> categories = ((result[ApiURL.dataKey] ?? []) as List)
          .map((category) => Category.fromJson(Map.from(category ?? {})))
          .toList();
      List<Category> uniqueCategories =
          categories.toSet().toList().fold<List<Category>>([], (list, current) {
        if (!list.any((item) => item.id == current.id)) {
          list.add(current);
        }
        return list;
      });
      int difference = categories.length - uniqueCategories.length;
      return (
        categories: uniqueCategories,
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()) - difference,
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString(), errorCode: e.errorCode);
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<List<CategorySlider>> getCategoriesSliders(
      {required int storeId}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getCategoriesSliders,
          useAuthToken: true,
          queryParameters: {ApiURL.storeIdApiKey: storeId});

      return ((result['slider_images'] ?? []) as List)
          .where((slider) => slider != null && slider['status'] == 1)
          .map((slider) =>
              CategorySlider.fromJson(Map<String, dynamic>.from(slider)))
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString(),
            errorCode: e
                .errorCode); // Re-throw the API exception with the backend message
      } else {
        // Handle any other exceptions
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }
}
