import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/utils/utils.dart';

class ReturnRequestRepository {
  Future<Map<String, dynamic>> getReturnRequests(Map<String, dynamic> params,
      {int? offset}) async {
    try {
      Map<String, dynamic> queryParameters = Map.from(params);
      if (offset != null) {
        queryParameters[ApiURL.offsetApiKey] = offset;
      }
      final result = await Api.get(
        url: ApiURL.getReturnRequests,
        useAuthToken: true,
        queryParameters: queryParameters,
      );
      return result;
    } catch (e) {
      Utils.throwApiException(e);
      rethrow; // Ensure function never returns null
    }
  }

  Future<Map<String, dynamic>> updateReturnRequestStatus({
    required int status,
    required int returnRequestId,
    required int orderItemId,
    int? deliverBy,
    String? remarks,
  }) async {
    try {
      final Map<String, dynamic> body = {
        ApiURL.statusApiKey: status.toString(),
        ApiURL.returnRequestIdApiKey: returnRequestId.toString(),
        ApiURL.orderItemIdApiKey: orderItemId.toString(),
      };
      if (deliverBy != null) {
        body[ApiURL.deliverByApiKey] = deliverBy.toString();
      }
      if (remarks != null && remarks.isNotEmpty) {
        body[ApiURL.updateRemarksApiKey] = remarks;
      }
      final result = await Api.put(
        url: ApiURL.updateReturnRequests,
        useAuthToken: true,
        queryParameters: body,
      );
      return result;
    } catch (e) {
      Utils.throwApiException(e);
      rethrow;
    }
  }
}
