import 'package:dio/dio.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/features/orders/models/parcel.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';

import 'package:eshopplus_seller/utils/utils.dart';

class OrderRepository {
  Future getOrders(Map<String, dynamic> params) async {
    try {
      final result = await Api.get(
          url: ApiURL.getOrders, useAuthToken: true, queryParameters: params);

      return result;
    } catch (e) {
      Utils.throwApiException(e);
    }
  }

  Future<String> getOrderInvoice(
      {required int id, required String apiUrl}) async {
    try {
      final result = await Api.get(
          url: apiUrl,
          useAuthToken: true,
          queryParameters: apiUrl == ApiURL.downloadOrderInvoice
              ? {ApiURL.orderIdApiKey: id}
              : {ApiURL.idApiKey: id});

      return result['invoice_url'];
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future<({List<Parcel> parcels, int total})> getParcels(
      {required Map<String, dynamic> queryParameters}) async {
    try {
      queryParameters.addAll({ApiURL.limitApiKey: limit});

      final result = await Api.get(
          url: ApiURL.getAllParcels,
          useAuthToken: true,
          queryParameters: queryParameters);

      return (
        parcels: ((result[ApiURL.dataKey] ?? []) as List)
            .map((parcel) => Parcel.fromJson(Map.from(parcel ?? {})))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future<({Parcel parcel, String message})> createOrderParcel(
      Map<String, dynamic> params) async {
    try {
      final result = await Api.post(
          url: ApiURL.createOrderParcel, useAuthToken: true, body: params);

      return (
        parcel: Parcel.fromJson(Map.from(result[ApiURL.dataKey][0] ?? {})),
        message: result[ApiURL.messageKey].toString()
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future deleteOrderParcel({required int parcelId}) async {
    try {
      final result = await Api.delete(
          url: ApiURL.deleteOrderParcel,
          useAuthToken: true,
          queryParameters: {ApiURL.idApiKey: parcelId});

      return result[ApiURL.messageKey];
    } catch (e) {
      Utils.throwApiException(e);
    }
  }

  Future<void> downloadFile(
      {required String url,
      required String savePath,
      required CancelToken cancelToken,
      required Function updateDownloadedPercentage}) async {
    try {
      await Api.download(
          cancelToken: cancelToken,
          url: url,
          savePath: savePath,
          updateDownloadedPercentage: updateDownloadedPercentage);
    } catch (e) {
      Utils.throwApiException(e);
    }
  }
}
