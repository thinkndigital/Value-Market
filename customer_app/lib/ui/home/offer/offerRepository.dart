import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/ui/home/offer/offerSlider.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/ui/home/slider/slider.dart';

class OfferRepository {
  Future<List<OfferSlider>> getOfferSliders({required int storeId}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getOfferSliders,
          useAuthToken: false,
          queryParameters: {ApiURL.storeIdApiKey: storeId});

      return ((result['slider_images'] ?? []) as List)
          .map((offer) => OfferSlider.fromJson(Map.from(offer ?? {})))
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<List<Sliders>> getSliders({required int storeId}) async {
    try {
      final result = await Api.get(
          url: ApiURL.getSliderImages,
          useAuthToken: true,
          queryParameters: {ApiURL.storeIdApiKey: storeId});

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((slider) => Sliders.fromJson(Map.from(slider ?? {})))
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
