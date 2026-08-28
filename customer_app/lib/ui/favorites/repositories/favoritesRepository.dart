import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/ui/favorites/models/offlineFavorite.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:eshop_plus/commons/seller/repositories/sellerRepository.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:hive/hive.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/configs/appConfig.dart';

class FavoritesRepository {
  Future<String> addFavoriteProduct(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          url: ApiURL.addFavoriteProduct, body: params, useAuthToken: true);
      return result['message'];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<String> removeFavoriteProduct(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          url: ApiURL.removeFavoriteProduct, body: params, useAuthToken: true);
      return result['message'];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<
      ({
        List<Product> products,
        int productsTotal,
        List<Seller> sellers,
        int sellersTotal,
      })> getFavorites({
    required int storeId,
    int? productOffset,
    int? sellerOffset,
  }) async {
    try {
      final result = await Api.get(
          url: ApiURL.getFavorites,
          useAuthToken: true,
          queryParameters: {
            ApiURL.storeIdApiKey: storeId,
            ApiURL.productLimitApiKey: limit,
            ApiURL.sellerLimitApiKey: limit,
            ApiURL.productOffsetApiKey: productOffset ?? 0,
            ApiURL.sellerOffsetApiKey: sellerOffset ?? 0,
          });

      return (
        products: ((result['products'][ApiURL.dataKey] ?? []) as List)
            .map((product) => Product.fromJson(Map.from(product ?? {})))
            .where((product) =>
                product.type == comboProductType ||
                (product.type != comboProductType &&
                    product.variants != null &&
                    product.variants!
                        .isNotEmpty)) // Filter products with non-empty variants
            .toList(),
        productsTotal:
            int.parse((result['products'][ApiURL.totalKey] ?? 0).toString()),
        sellers: ((result['sellers'][ApiURL.dataKey] ?? []) as List)
            .map((seller) => Seller.fromJson(Map.from(seller ?? {})))
            .toList(),
        sellersTotal:
            int.parse((result['sellers'][ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<
      ({
        List<Product> products,
        int productsTotal,
        List<Seller> sellers,
        int sellersTotal,
      })> getOfflineFavorites(int storeId) async {
    var box = await Hive.openBox(favoritesBoxKey);

    // Separate into product and seller models
    List<Product> products = [];
    List<Seller> sellers = [];
    List<OfflineFavorite> allFavorites = [];
    List<int> productIds = [], comboProuctIds = [], sellerIds = [];
    // Retrieve all stored favorites
    box.toMap().forEach((key, value) {
      allFavorites.add(OfflineFavorite.fromMap(value));
    });
    for (var favorite in allFavorites) {
      if (favorite.type == 'product') {
        if (favorite.productType == comboType) {
          comboProuctIds.add(favorite.id);
        } else {
          productIds.add(favorite.id);
        }
      } else if (favorite.type == 'seller') {
        sellerIds.add(favorite.id);
      }
    }
    // Prepare a list of futures for all API calls
    List<Future<void>> futures = [];

    if (sellerIds.isNotEmpty) {
      try {
        futures.add(SellerRepository()
            .getSellers(storeId: storeId, sellerIds: sellerIds)
            .then((value) => sellers = value.sellers));
      } catch (e) {
        sellers = [];
      }
    }

    if (productIds.isNotEmpty) {
      try {
        futures.add(ProductRepository()
            .getProducts(storeId: storeId, productIds: productIds)
            .then((value) => products.addAll(value.products)));
      } catch (e) {}
    }
    if (comboProuctIds.isNotEmpty) {
      try {
        futures.add(ProductRepository()
            .getProducts(
                storeId: storeId,
                apiUrl: ApiURL.getComboProducts,
                isComboProduct: true,
                productIds: comboProuctIds)
            .then((value) => products.addAll(value.products)));
      } catch (e) {}
    }
    // Wait for all API calls to complete
    await Future.wait(futures);

    return (
      products: products,
      productsTotal: products.length,
      sellers: sellers,
      sellersTotal: sellers.length,
    );
  }

  getOfflineFavoriteIds() {
    var box = Hive.box(favoritesBoxKey);

    List<OfflineFavorite> allFavorites = [];
    List<int> productIds = [], comboProuctIds = [], sellerIds = [];
    // Retrieve all stored favorites
    box.toMap().forEach((key, value) {
      allFavorites.add(OfflineFavorite.fromMap(value));
    });
    for (var favorite in allFavorites) {
      if (favorite.type == 'product') {
        if (favorite.productType == comboType) {
          comboProuctIds.add(favorite.id);
        } else {
          productIds.add(favorite.id);
        }
      } else if (favorite.type == 'seller') {
        sellerIds.add(favorite.id);
      }
    }

    return [productIds, comboProuctIds, sellerIds];
  }
}
