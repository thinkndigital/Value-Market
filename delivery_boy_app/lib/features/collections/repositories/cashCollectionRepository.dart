import 'package:value_market_delivery_boy/core/localization/labelKeys.dart';
import 'package:value_market_delivery_boy/features/orders/models/cashCollection.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';
import '../../../core/api/apiService.dart';
import 'package:value_market_delivery_boy/core/configs/appConfig.dart';

class CashCollectionRepository {
Future<({List<CashCollection> transactions, int total, double balance})>
getCollections({
required String status,
int? offset,
}) async {
try {
Map<String, dynamic> queryParameters = {
ApiURL.statusApiKey: status,
ApiURL.limitApiKey: limit,
ApiURL.offsetApiKey: offset ?? 0,
};

final result = await Api.get(
url: ApiURL.deliveryBoyCashCollection,
useAuthToken: true,
queryParameters: queryParameters);
return (
transactions: ((result['data'] ?? []) as List)
.map((transaction) => CashCollection.fromJson(transaction ?? {}))
.toList(),
total: int.parse((result['total'] ?? 0).toString()),
balance: double.parse((result['balance'] ?? 0).toString()),
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
