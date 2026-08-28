import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/utils/utils.dart';

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
