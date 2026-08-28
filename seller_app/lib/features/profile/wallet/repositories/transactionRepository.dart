import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/utils/utils.dart';

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
        ApiURL.userIdApiKey: userId,
        ApiURL.limitApiKey: limit,
        ApiURL.typeApiKey: type,
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
            ? ((result[ApiURL.dataKey] ?? []) as List)
                .map((transaction) =>
                    Transaction.fromWithdrawJson(Map.from(transaction ?? {})))
                .toList()
            : ((result[ApiURL.dataKey] ?? []) as List)
                .map((transaction) =>
                    Transaction.fromJson(Map.from(transaction ?? {})))
                .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
        balance: double.parse((result['balance'] ?? 0).toString()),
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future<({Transaction transaction, String message})> sendWithdrawalRequest(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.sendWithdrawalRequest, useAuthToken: true);

      return (
        transaction: Transaction.fromWithdrawJson(result[ApiURL.dataKey] ?? {}),
        message: result[ApiURL.messageKey].toString()
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }
}
