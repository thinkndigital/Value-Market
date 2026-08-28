import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/api/apiService.dart';
import 'package:value_market_seller/utils/utils.dart';

class AttributeRepository {
  Future getAttributeListProcess(Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getAttributes,
          useAuthToken: true,
          queryParameters: parameter);
      return result;
    } catch (e) {
      Utils.throwApiException(e);
    }
  }
}
