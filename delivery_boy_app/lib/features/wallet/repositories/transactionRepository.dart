import 'package:eshoppro_deliveryboy/core/constants/appConstants.dart';
import 'package:eshoppro_deliveryboy/core/api/apiEndPoints.dart';

import '../../../core/api/apiService.dart';
import 'package:eshoppro_deliveryboy/core/configs/appConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../models/transaction.dart';

class TransactionRepository {
Future<({List<Transaction> transactions, int total, double balance})>
getTransactions({
required int userId,
int? offset,
String? type,
}) async {
try {
Map<String, dynamic> queryParameters = {
ApiURL.limitApiKey: limit,
ApiURL.offsetApiKey: offset ?? 0,
};

final result = await Api.get(
url: type == debitType
? ApiURL.getWithdrawalRequest
: ApiURL.getTransactions,
useAuthToken: true,
queryParameters: queryParameters);
return (
transactions: type == debitType
? ((result['data'] ?? []) as List)
.map((transaction) => Transaction.fromWithdrawJson(transaction))
.toList()
: ((result['data'] ?? []) as List)
.map((transaction) => Transaction.fromJson(transaction ?? {}))
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

Future sendWithdrawalRequest({required Map<String, dynamic> params}) async {
try {
final result = await Api.post(
body: params, url: ApiURL.sendWithdrawalRequest, useAuthToken: true);

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
