import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import '../models/faq.dart';

class FaqRepository {
  Future<({List<FAQ> faqs, int total})> getFaqs({
    required Map<String, dynamic> params,
    required String api,
  }) async {
    try {
      if (!params.containsKey(ApiURL.limitApiKey)) {
        params.addAll({ApiURL.limitApiKey: limit});
      }
      final result =
          await Api.get(url: api, useAuthToken: true, queryParameters: params);

      return (
        faqs: ((result[ApiURL.dataKey] ?? []) as List)
            .map((faq) => FAQ.fromJson(Map.from(faq ?? {})))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<({FAQ faq, String successMessage})> addProductFaq(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          url: ApiURL.addProductFfaqs, body: params, useAuthToken: true);

      return (
        faq: FAQ.fromJson(Map.from(result[ApiURL.dataKey] ?? {})),
        successMessage: result['message'].toString()
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
