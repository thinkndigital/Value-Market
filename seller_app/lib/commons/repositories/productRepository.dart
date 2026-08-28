import 'dart:io';
import 'package:dio/dio.dart' as api;
import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/commons/models/product.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/api/apiService.dart';
import 'package:value_market_seller/core/configs/appConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/features/home/models/topSellingProduct.dart';
import 'package:value_market_seller/features/product/models/productRating.dart';
import 'package:value_market_seller/features/profile/addProduct/models/media.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:mime/mime.dart';
import 'package:http_parser/http_parser.dart';

class ProductRepository {
  Future<
          ({
            List<Product> products,
            int total,
          })>
      getProducts(
          {required int storeId,
          String? apiUrl,
          int? productId,
          int? offset,
          String? sortBy,
          String? orderBy,
          int? topRatedProduct,
          String? flag,
          String? type,
          List<int>? productIds,
          bool isComboProduct = false,
          int? showOnlyStockroducts,
          int? showOnlyActiveProducts,
          String? searchText}) async {
    try {
      Map<String, dynamic> queryParameters = {
        ApiURL.storeIdApiKey: storeId,
        ApiURL.offsetApiKey: offset ?? 0,
        ApiURL.limitApiKey: limit,
        ApiURL.sortByApiKey: sortBy,
        ApiURL.orderByApiKey: orderBy,
        'show_only_active_products': showOnlyActiveProducts ?? 0,
        ApiURL.showOnlyStockProductApiKey: showOnlyStockroducts ?? 0,
      };
      if (isComboProduct &&
          queryParameters.containsKey(ApiURL.sortByApiKey) &&
          queryParameters[ApiURL.sortByApiKey] == "pv.price") {
        queryParameters.addAll({ApiURL.sortByApiKey: "p.price"});
      }
      if (topRatedProduct != null) {
        queryParameters
            .addAll({ApiURL.productTypeApiKey: 'top_rated_products'});
      }
      if (flag != allKey && flag != null) {
        queryParameters.addAll({
          ApiURL.flagApiKey: flag,
        });
      }
      if (type != allKey && type != null) {
        queryParameters.addAll({
          ApiURL.typeApiKey: type,
        });
      }
      if (searchText != null && searchText.trim().isNotEmpty) {
        queryParameters.addAll({ApiURL.searchApiKey: searchText});
      }

      if (sortBy == null) {
        queryParameters.remove(ApiURL.sortByApiKey);
      }
      if (orderBy == null) {
        queryParameters.remove(ApiURL.orderByApiKey);
      }
      if (topRatedProduct == null) {
        queryParameters.remove(ApiURL.topRatedProductApiKey);
      }
      if (productId != null) {
        queryParameters.addAll({ApiURL.idApiKey: productId});
      }
      final result = await Api.get(
          url: isComboProduct
              ? ApiURL.getComboProducts
              : apiUrl ?? ApiURL.getProducts,
          useAuthToken: true,
          queryParameters: queryParameters);

      return (
        products: ((result[ApiURL.dataKey] ?? []) as List)
            .map((product) => Product.fromJson(Map.from(product ?? {}),
                isComboProduct: isComboProduct))
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
     return Utils.throwApiException(e);
    }
  }

  Future<({ProductRating productRating, int total})> getProductRatings({
    required Map<String, dynamic> params,
    required String apiUrl,
  }) async {
    try {
      params.addAll({ApiURL.limitApiKey: limit});

      final result = await Api.get(
          url: apiUrl, useAuthToken: true, queryParameters: params);

      return (
        productRating: ProductRating.fromJson(Map.from(result)),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
     return Utils.throwApiException(e);
    }
  }

  Future<String> updateProductStatus(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.put(
          queryParameters: params,
          url: ApiURL.updateProductStatus,
          useAuthToken: true);

      return result[ApiURL.messageKey].toString();
    } catch (e) {
    return  Utils.throwApiException(e);
    }
  }

  Future<({Product product, String message})> manageStock(
      {required Map<String, dynamic> params,
      required String apiUrl,
      required bool isComboProduct}) async {
    try {
      final result = await Api.put(
          queryParameters: params, url: apiUrl, useAuthToken: true);

      return (
        product: Product.fromJson(result[ApiURL.dataKey][0],
            isComboProduct: isComboProduct),
        message: result[ApiURL.messageKey].toString()
      );
    } catch (e) {
  return    Utils.throwApiException(e);
    }
  }

  Future<String> deleteProduct({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.delete(
          url: ApiURL.deleteProduct,
          useAuthToken: true,
          queryParameters: params);

      return result[ApiURL.messageKey];
    } catch (e) {
   return   Utils.throwApiException(e);
    }
  }

  Future<List<TopSellingProduct>> getTopSellingProducts(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.get(
          url: ApiURL.topSellingProducts,
          useAuthToken: true,
          queryParameters: params);

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((product) => TopSellingProduct.fromJson(Map.from(product ?? {})))
          .toList();
    } catch (e) {
     return Utils.throwApiException(e);
    }
  }

  Future<String> updateProductDeliverability({
    required Map<String, dynamic> params,
    required String apiUrl,
  }) async {
    try {
      final result =
          await Api.post(body: params, url: apiUrl, useAuthToken: true);

      return result[ApiURL.messageKey].toString();
    } catch (e) {
   return   Utils.throwApiException(e);
    }
  }

  Future uploadMedia(
      List<File> filelist, BuildContext context, String type) async {
    Map<String, dynamic> params = {
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id!.toString()
    };

    for (int i = 0; i < filelist.length; i++) {
      File file = filelist[i];
      var mimeType = lookupMimeType(file.path);
      params["documents[]"] = await api.MultipartFile.fromFile(file.path,
          contentType: MediaType.parse(mimeType ?? 'application/octet-stream'));
    }
    try {
      final result = await Api.post(
          body: params, url: ApiURL.uploadMedia, useAuthToken: true);
      List data = result[ApiURL.dataKey];
      Utils.showSnackBar(message: result[ApiURL.messageKey]);
      List<Media> medialist = [];
      for (var element in data) {
        Media media = Media();
        media.image = element["full_path"];
        media.relativePath = element["relative_path"];
        media.name = element["full_path"].toString().split("/").last;
        media.type = type;
        medialist.add(media);
      }
      return medialist;
    } catch (e) {
     return Utils.throwApiException(e);
    }
  }
}
