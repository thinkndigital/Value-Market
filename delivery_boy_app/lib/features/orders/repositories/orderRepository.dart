import 'package:value_market_delivery_boy/core/constants/appConstants.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';
import '../../../core/api/apiService.dart';
import '../../../core/localization/labelKeys.dart';

class OrderRepository {
Future getOrders(Map<String, dynamic> params) async {
try {
String url = ApiURL.getOrders;
if (params.containsKey("active_status") &&
params["active_status"] == returnedStatusType) {
url = ApiURL.getReturnedOrderItems;
}
final result =
await Api.get(url: url, useAuthToken: true, queryParameters: params);

return result;
} catch (e) {
if (e is ApiException) {
throw ApiException(e.toString());
} else {
throw ApiException(defaultErrorMessageKey);
}
}
}
}
