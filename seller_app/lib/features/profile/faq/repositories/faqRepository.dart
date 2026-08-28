import 'package:eshopplus_seller/core/configs/appConfig.dart';

import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import '../models/faq.dart';

class FaqRepository {
  Future<({List<FAQ> faqs, int total})> getFaqs({
    required Map<String, dynamic> params,
    required String api,
  }) async {
    try {
      params.addAll({ApiURL.limitApiKey: limit});
      final result =
          await Api.get(url: api, useAuthToken: true, queryParameters: params);

      return (
        faqs: ((result[ApiURL.dataKey] ?? []) as List)
            .map((faq) => FAQ.fromJson(Map.from(faq ?? {})))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future<({FAQ faq, String successMessage})> addProductFaq(
      {required Map<String, dynamic> params, required bool isEditFaq}) async {
    try {
      var result;
      if (isEditFaq) {
        result = await Api.put(
            url: ApiURL.editProductFaqs,
            queryParameters: params,
            useAuthToken: true);
      } else {
        result = await Api.post(
            url: ApiURL.addProductFaqs, body: params, useAuthToken: true);
      }
      return (
        faq: FAQ.fromJson(Map.from(result[ApiURL.dataKey] ?? {})),
        successMessage: result[ApiURL.messageKey].toString()
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future deleteFAQ({required int faqId, required String type}) async {
    try {
      final result = await Api.delete(
          url: ApiURL.deleteProductFaq,
          useAuthToken: true,
          queryParameters: {ApiURL.idApiKey: faqId, ApiURL.typeApiKey: type});

      return result[ApiURL.messageKey];
    } catch (e) {
      Utils.throwApiException(e);
    }
  }
}
