import 'dart:async';

import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/core/configs/appConfig.dart';
import 'package:eshop_plus/commons/product/models/filterAttribute.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/product/models/productRating.dart';
import 'package:eshop_plus/ui/search/models/searchedProduct.dart';
import 'package:eshop_plus/ui/auth/repositories/authRepository.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:hive/hive.dart';

class ProductRepository {
  Future<
          ({
            List<Product> products,
            int total,
            List<FilterAttribute> filterAttributes,
            double minPrice,
            double maxPrice,
            String? categoryIds,
            String? brandIds
          })>
      getProducts(
          {required int storeId,
          String? apiUrl,
          int? productId,
          int? offset,
          String? sortBy,
          String? orderBy,
          int? topRatedProduct,
          int? sellerId,
          String? categoryIds,
          String? brandIds,
          List<int>? attributeValueIds,
          String? discount,
          String? rating,
          double? minPrice,
          double? maxPrice,
          List<int>? productIds,
          bool isComboProduct = false,
          String? zipcode,
          String? searchText}) async {
    try {
      Map<String, dynamic> queryParameters = {
        ApiURL.userIdApiKey: AuthRepository.getUserDetails().id,
        ApiURL.storeIdApiKey: storeId,
        ApiURL.productIdApiKey: productId,
        ApiURL.offsetApiKey: offset ?? 0,
        ApiURL.limitApiKey: limit,
        ApiURL.sortByApiKey: sortBy,
        ApiURL.orderByApiKey: orderBy,
        ApiURL.sellerIdApiKey: sellerId,
        ApiURL.categoryIdApiKey: categoryIds,
        ApiURL.brandIdApiKey: brandIds,
        ApiURL.attributeValuesIdsApiKey: attributeValueIds,
        ApiURL.discountApiKey: discount,
        ApiURL.ratingApiKey: rating,
        ApiURL.minPriceApiKey: minPrice,
        ApiURL.maxPriceApiKey: maxPrice,
      };
      if (isComboProduct &&
          queryParameters.containsKey(ApiURL.sortByApiKey) &&
          queryParameters[ApiURL.sortByApiKey] == "pv.price") {
        queryParameters.addAll({ApiURL.sortByApiKey: "p.price"});
      }
      if (searchText != null) {
        queryParameters.addAll({ApiURL.searchApiKey: searchText});
      }
      if (productIds != null && productIds.isNotEmpty) {
        queryParameters.addAll({ApiURL.productIdsApiKey: productIds.join(',')});
      }
      if (sortBy == null) {
        queryParameters.remove(ApiURL.sortByApiKey);
      }
      if (orderBy == null) {
        queryParameters.remove(ApiURL.orderByApiKey);
      }
      if (topRatedProduct != null) {
        queryParameters
            .addAll({ApiURL.productTypeApiKey: 'top_rated_products'});
      }
      if (sellerId == null) {
        queryParameters.remove(ApiURL.sellerIdApiKey);
      }

      if (categoryIds == null) {
        queryParameters.remove(ApiURL.categoryIdApiKey);
      }

      if (brandIds == null) {
        queryParameters.remove(ApiURL.brandIdApiKey);
      }

      if (attributeValueIds == null) {
        queryParameters.remove(ApiURL.attributeValuesIdsApiKey);
      }

      if (discount == null) {
        queryParameters.remove(ApiURL.discountApiKey);
      }
      if (rating == null) {
        queryParameters.remove(ApiURL.ratingApiKey);
      }

      if (minPrice == null) {
        queryParameters.remove(ApiURL.minPriceApiKey);
      }
      if (maxPrice == null) {
        queryParameters.remove(ApiURL.maxPriceApiKey);
      }
      if (zipcode != null) {
        queryParameters.addAll({ApiURL.zipCodeApiKey: zipcode});
      }

      final result = await Api.get(
          url: isComboProduct
              ? ApiURL.getComboProducts
              : apiUrl ?? ApiURL.getProducts,
          useAuthToken: false,
          queryParameters: queryParameters);

      return (
        products: ((result[ApiURL.dataKey] ?? []) as List)
            .map((product) => Product.fromJson(Map.from(product ?? {})))
            .where((product) =>
                product.type == comboProductType ||
                (product.type != comboProductType &&
                    product.variants != null &&
                    product.variants!
                        .isNotEmpty)) // Filter products with non-empty variants
            .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
        filterAttributes: (result['filters'] as List?)
                ?.map((filterAttributes) =>
                    FilterAttribute.fromJson(Map.from(filterAttributes ?? {})))
                .toList() ??
            List<FilterAttribute>.from([]),
        minPrice: double.parse(result['min_price']?.toString() ?? '0'),
        maxPrice: double.parse(result['max_price']?.toString() ?? '0'),
        categoryIds: result['category_ids']?.toString(),
        brandIds: result['brand_ids']?.toString(),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<List<Product>> getMostSellingProducts(
      {required int storeId, required int userId, String? zipcode}) async {
    try {
      Map<String, dynamic>? params = {
        ApiURL.storeIdApiKey: storeId,
        ApiURL.userIdApiKey: userId,
      };
      if (zipcode != null && zipcode.isNotEmpty) {
        params[ApiURL.zipCodeApiKey] = zipcode;
      }
      final result = await Api.get(
          url: ApiURL.getMostSellingProducts,
          useAuthToken: true,
          queryParameters: params);
      return ((result[ApiURL.dataKey] ?? []) as List).map((model) {
        return Product.fromMostSellingProductJson(model);
      }).where((product) {
        if (zipcode != null) {
          if (product.isDeliverable == true)
            return true;
          else
            return false;
        } else {
          return true;
        }
      }).toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<({ProductRating productRating, int total})> getProductRatings({
    required Map<String, dynamic> params,
    required String apiUrl,
  }) async {
    try {
      if (!params.containsKey(ApiURL.limitApiKey)) {
        params.addAll({ApiURL.limitApiKey: limit});
      }
      final result = await Api.get(
          url: apiUrl, useAuthToken: true, queryParameters: params);

      return (
        productRating: ProductRating.fromJson(Map.from(result)),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<List<SearchedProduct>> getSearchProducts(
      {required int storeId, required String query}) async {
    try {
      final result = await Api.post(
          url: ApiURL.searchProducts,
          useAuthToken: false,
          body: {
            ApiURL.storeIdApiKey: storeId,
            ApiURL.searchApiKey: query,
          });

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((product) => SearchedProduct.fromJson(Map.from(product ?? {})))
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<List<String>> getMostSearchedProducts({required int storeId}) async {
    try {
      final result = await Api.post(
          url: ApiURL.getMostSearchedHistory,
          useAuthToken: false,
          body: {
            ApiURL.storeIdApiKey: storeId,
          });

      return ((result[ApiURL.dataKey] ?? []) as List)
          .map((text) => text['search_term'].toString())
          .toList();
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<String> checkProductDeliverability(
      {required Map<String, dynamic> productParams,
      required Map<String, dynamic> sellerParams}) async {
    try {
      var result;
      result = await Api.post(
          url: ApiURL.isSellerDelivarable,
          useAuthToken: true,
          body: sellerParams);
  
      if (result['error']) {
        throw ApiException(result['message']);
      } else {
        result = await Api.post(
            url: ApiURL.isProductDelivarable,
            useAuthToken: true,
            body: productParams);
      }

      return result['message'];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<({RatingData productRating, String successMessage})> setProductReview(
      {required Map<String, dynamic> params, required String apiUrl}) async {
    try {
      final result =
          await Api.post(url: apiUrl, useAuthToken: true, body: params);
      var filteredRatings = (result[ApiURL.dataKey]['product_rating'] ?? [])
          .where((rating) =>
              rating['user_id'] == AuthRepository.getUserDetails().id)
          .toList();
      return (
        productRating: RatingData.fromJson(
            Map.from(filteredRatings.isNotEmpty ? filteredRatings.first : {})),
        successMessage: result['message'].toString(),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  addSearchInLocalHistory(String query) {
    final searchBox = Hive.box(searchBoxKey);
    List<String> currentHistory = searchBox.values.toList().cast<String>();

// Check if the search query is already in the history
    if (!currentHistory.contains(query)) {
      // If the history has reached the maximum limit, remove the oldest entry
      if (currentHistory.length >= maxSearchHistory) {
        searchBox.deleteAt(0);
      }

      // Add the new query to the beginning of the box
      searchBox.add(query);
    }
  }

  getSearchHistory() {
    return Hive.box(searchBoxKey).values.toList().reversed.toList();
  }

  clearSearchHistory() {
    Hive.box(searchBoxKey).clear();
  }
}
