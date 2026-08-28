import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/ui/cart/models/cart.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';

class CartRepository {
  Future<Cart> fetchUserCart(
      {required Map<String, dynamic> params,
      bool isCallForSavedForLater = true}) async {
    try {
      final result = await Api.get(
          queryParameters: params, url: ApiURL.getUserCart, useAuthToken: true);
      Cart cart;
      if (result['error'] == true) {
        cart = Cart();
      } else {
        cart = Cart.fromJson(Map.from(result));
      }
      if (isCallForSavedForLater) {
        // Update params to fetch "Save for Later" items
        params[ApiURL.isSavedForLaterApiKey] = 1;
        final savedForLaterResponse = await Api.get(
            queryParameters: params,
            url: ApiURL.getUserCart,
            useAuthToken: true);
        if (result['error'] == true && savedForLaterResponse['error'] == true) {
          throw ApiException(result['message']);
        }
        // Check if the response contains "cart" data
        if (savedForLaterResponse['cart'] != null) {
          // Ensure saveForLaterProducts is initialized
          cart.saveForLaterProducts = [];

          // Iterate over the items and add them to the saveForLaterProducts list
          Map.from(savedForLaterResponse)['cart'].forEach((v) {
            cart.saveForLaterProducts!.add(CartProduct.fromJson(v));
          });
        }
      }

      return cart;
    } catch (e) {
      // If an error occurs, check if it's an ApiException or a different type of error
      if (e is ApiException) {
        throw ApiException(
          e.toString(),
        ); // Re-throw the API exception with the backend message
      } else {
        // Handle any other exceptions
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<Cart> manageUserCart({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.manageCart, useAuthToken: true);

      return Cart.fromJson(Map.from(result['data']));
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<String> clearCart() async {
    try {
      final result =
          await Api.post(body: {}, url: ApiURL.clearCart, useAuthToken: true);
      return result['message'];
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<Cart> manageCart({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.manageCart, useAuthToken: true);

      return Cart.fromJson(Map.from(result));
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
        String? totalQuantity,
        double? subTotal,
        double? itemTotal,
        double? discount,
        double? deliveryCharge,
        double? taxAmount,
        double? overallAmount,
        String? successMessage
      })> removeProductFromCart({required Map<String, dynamic> params}) async {
    try {
      final result = await Api.delete(
          queryParameters: params,
          url: ApiURL.removeFromCart,
          useAuthToken: true);
      if ((result['data'] ?? []).isEmpty) {
        return (
          totalQuantity: '0',
          deliveryCharge: 0.0,
          subTotal: 0.0,
          taxAmount: 0.0,
          overallAmount: 0.0,
          itemTotal: 0.0,
          discount: 0.0,
          successMessage: result['message'].toString()
        );
      }

      return (
        totalQuantity: result['data']['total_quantity'].toString(),
        subTotal: double.tryParse(result['data']['sub_total']),
        deliveryCharge:
            double.tryParse(result['data']['delivery_charge'].toString()),
        taxAmount: double.tryParse(result['data']['tax_amount'].toString()),
        overallAmount:
            double.tryParse(result['data']['overall_amount'].toString()),
        itemTotal: double.tryParse(result['data']['item_total'].toString()),
        discount: double.tryParse(result['data']['discount'].toString()),
        successMessage: result['message'].toString()
      );

      // return Cart.fromJson(Map.from(result));
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<String> checkDeliverability(
      {required int storeId, required int addressId}) async {
    try {
      final result = await Api.post(body: {
        ApiURL.storeIdApiKey: storeId,
        ApiURL.addressIdApiKey: addressId,
      }, url: ApiURL.checkCartProductsDelivarable, useAuthToken: true);
      if (result['error'] == true) {
        List<Map<String, dynamic>> nonDeliverableItems = [];

        if (result['data'] != null) {
          List<dynamic> data = result['data'];
          for (var item in data) {
            if (item['is_deliverable'] == false) {
              nonDeliverableItems.add(Map<String, dynamic>.from(item));
            }
          }
        }

        throw ApiException(result['message'], errorData: nonDeliverableItems);
      } else {
        return result['message'];
      }
    } catch (e) {
      if (e is ApiException) {
        throw e;
      } else {
        throw ApiException(e.toString());
      }
    }
  }

  Future<({int orderId, double finalTotal, double walletBalance})> placeOrder(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.placeOrder, useAuthToken: true);

      return (
        orderId: int.parse(result['order_id'].toString()),
        finalTotal: double.parse(result['final_total'].toString()),
        walletBalance: double.parse(result['balance'][0]['balance'].toString())
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
