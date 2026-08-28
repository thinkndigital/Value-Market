import 'package:value_market_customer/ui/profile/address/models/address.dart';
import 'package:value_market_customer/ui/profile/transaction/models/paymentMethod.dart';
import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/commons/product/models/productVariant.dart';
import 'package:value_market_customer/ui/profile/promoCode/models/promoCode.dart';
import 'package:get/get.dart';

class Cart {
  String? totalQuantity;
  double? subTotal;
  double? itemTotal;
  double? discount;

  double? deliveryCharge;
  double? taxPercentage;
  double? taxAmount;
  double? overallAmount;
  double? originalOverallAmount;
  double? totalArr;
  List<int>? variantId;
  List<CartProduct>? cartProducts;
  List<CartProduct>? saveForLaterProducts;
  List<CartProduct>? outOfStockProducts;
  List<PromoCode>? promoCodes;
  double? couponDiscount;
  PromoCode? promoCode;
  String? deliveryInstruction;
  String? emailAddress;
  PaymentModel? selectedPaymentMethod;
  Address? selectedAddress;
  bool? useWalletBalance = false;
  double? walletAmount;
  String? stripePayId;
  Map<int, String?>? attachments = {};
  Cart(
      {this.totalQuantity,
      this.subTotal,
      this.itemTotal,
      this.discount,
      this.couponDiscount,
      this.promoCode,
      this.deliveryCharge,
      this.taxPercentage,
      this.taxAmount,
      this.overallAmount,
      this.originalOverallAmount,
      this.totalArr,
      this.variantId,
      this.promoCodes,
      this.cartProducts,
      this.saveForLaterProducts,
      this.outOfStockProducts,
      this.deliveryInstruction,
      this.selectedPaymentMethod,
      this.emailAddress,
      this.selectedAddress,
      this.useWalletBalance,
      this.walletAmount,
      this.stripePayId,
      this.attachments});

  Cart.fromJson(Map<String, dynamic> json) {
    totalQuantity = json['total_quantity'];
    subTotal = double.tryParse((json['sub_total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['sub_total'] ?? 0).toString());
    itemTotal = double.tryParse((json['item_total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['item_total'] ?? 0).toString());
    discount = double.tryParse((json['discount'] ?? 0).toString().isEmpty
        ? "0"
        : (json['discount'] ?? 0).toString());
    couponDiscount = double.tryParse(
        (json['coupon_discount'] ?? 0).toString().isEmpty
            ? "0"
            : (json['coupon_discount'] ?? 0).toString());
    deliveryCharge = double.tryParse(
        (json['delivery_charge'] ?? 0).toString().isEmpty
            ? "0"
            : (json['delivery_charge'] ?? 0).toString());

    taxPercentage = double.tryParse(
        (json['tax_percentage'] ?? 0).toString().isEmpty
            ? "0"
            : (json['tax_percentage'] ?? 0).toString());
    taxAmount = double.tryParse((json['tax_amount'] ?? 0).toString().isEmpty
        ? "0"
        : (json['tax_amount'] ?? 0).toString());

    overallAmount = double.tryParse(
            (json['overall_amount'] ?? 0).toString().isEmpty
                ? "0"
                : (json['overall_amount'] ?? 0).toString()) ??
        0;
    originalOverallAmount =
        overallAmount; // we will use this param to restore the original overall amount when remove promo code
    useWalletBalance = false;
    totalArr = double.tryParse((json['total_arr'] ?? 0).toString().isEmpty
        ? "0"
        : (json['total_arr'] ?? 0).toString());

    variantId =
        json['variant_id'] != null ? json['variant_id'].cast<int>() : [];
    cartProducts = <CartProduct>[];
    saveForLaterProducts = <CartProduct>[];
    outOfStockProducts = <CartProduct>[];
    if (json['cart'] != null) {
      json['cart'].forEach((v) {
        if (v['is_saved_for_later'] == 1) {
          saveForLaterProducts!.add(CartProduct.fromJson(v));
        } else {
          cartProducts!.add(CartProduct.fromJson(v));
        }
      });
    }
    if (json['out_of_stock_data'] != null) {
      json['out_of_stock_data'].forEach((v) {
        outOfStockProducts!.add(CartProduct.fromJson(v));
        
      });
    }
    if (json['promo_codes'] != null) {
      promoCodes = <PromoCode>[];
      json['promo_codes'].forEach((v) {
        promoCodes!.add(PromoCode.fromJson(v));
      });
    }
  }
  Cart copyWith({
    String? totalQuantity,
    double? subTotal,
    double? deliveryCharge,
    double? taxPercentage,
    double? taxAmount,
    double? overallAmount,
    double? totalArr,
    List<int>? variantId,
    List<CartProduct>? cartProducts,
    List<CartProduct>? saveForLaterProducts,
    List<CartProduct>? outOfStockProducts,
    List<PromoCode>? promoCodes,
  }) {
    return Cart(
      totalQuantity: totalQuantity ?? this.totalQuantity,
      subTotal: subTotal ?? this.subTotal,
      deliveryCharge: deliveryCharge ?? this.deliveryCharge,
      taxPercentage: taxPercentage ?? this.taxPercentage,
      taxAmount: taxAmount ?? this.taxAmount,
      overallAmount: overallAmount ?? this.overallAmount,
      totalArr: totalArr ?? this.totalArr,
      variantId: variantId ?? this.variantId,
      cartProducts: cartProducts ?? this.cartProducts,
      saveForLaterProducts: saveForLaterProducts ?? this.saveForLaterProducts,
      outOfStockProducts: outOfStockProducts ?? this.outOfStockProducts,
      promoCodes: promoCodes ?? this.promoCodes,
    );
  }

  updateProductDetails(int cartProductId, Product product) {
    if (cartProducts != null) {
      CartProduct? cartProduct = cartProducts!.firstWhereOrNull(
        (product) => product.id == cartProductId,
      );
      if (cartProduct != null) {
        cartProduct.productDetails![0] = product;
      }
    }
  }
}

class CartProduct {
  int? id;
  int? userId;
  int? storeId;
  int? productVariantId;
  String? productIds;
  int? qty;
  int? isSavedForLater;
  String? productType;
  String? createdAt;
  String? updatedAt;
  int? cartId;
  String? cartProductType;
  int? isPricesInclusiveTax;
  String? name;
  String? type;
  String? productSlug;
  String? image;
  String? shortDescription;
  int? sellerId;
  int? minimumOrderQuantity;
  int? quantityStepSize;
  String? pickupLocation;
  String? weight;
  int? totalAllowedQuantity;
  double? price;
  double? specialPrice;
  String? taxPercentage;
  int? minimumFreeDeliveryOrderQty;
  String? productDeliveryCharge;
  bool? removeProductInProgress;

  String? netAmount;
  String? taxAmount;
  double? subTotal;
  List<ProductVariant>? productVariants;
  List<Product>? productDetails;
  String? errorMessage;
  CartProduct(
      {this.id,
      this.userId,
      this.storeId,
      this.productVariantId,
      this.productIds,
      this.qty,
      this.isSavedForLater,
      this.productType,
      this.createdAt,
      this.updatedAt,
      this.cartId,
      this.cartProductType,
      this.isPricesInclusiveTax,
      this.name,
      this.type,
      this.productSlug,
      this.image,
      this.shortDescription,
      this.sellerId,
      this.minimumOrderQuantity,
      this.quantityStepSize,
      this.pickupLocation,
      this.weight,
      this.totalAllowedQuantity,
      this.price,
      this.specialPrice,
      this.taxPercentage,
      this.minimumFreeDeliveryOrderQty,
      this.productDeliveryCharge,
      this.netAmount,
      this.taxAmount,
      this.subTotal,
      this.productVariants,
      this.productDetails,
      this.removeProductInProgress,
      this.errorMessage});

  CartProduct.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    userId = json['user_id'];
    storeId = json['store_id'];
    productVariantId = json['product_variant_id'];
    productIds = json['product_ids'];
    qty = json['qty'];
    isSavedForLater = json['is_saved_for_later'];
    productType = json['product_type'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    cartId = json['cart_id'];
    cartProductType = json['cart_product_type'];
    isPricesInclusiveTax =
        int.tryParse(json['is_prices_inclusive_tax'].toString()) ?? 0;
    name = json['name'];
    type = json['type'];
    productSlug = json['product_slug'];
    image = json['image'];
    shortDescription = json['short_description'];
    sellerId = json['seller_id'];
    minimumOrderQuantity =
        int.tryParse(json['minimum_order_quantity'].toString()) ?? 1;
    quantityStepSize = int.tryParse(json['quantity_step_size'].toString()) ?? 1;
    pickupLocation = json['pickup_location'];
    weight = json['weight'].toString();
    totalAllowedQuantity =
        int.tryParse(json['total_allowed_quantity'].toString()) ?? 1;
    price = double.parse((json['price'] ?? 0.0).toString());
    specialPrice = double.parse((json['special_price'] ?? 0.0).toString());
    taxPercentage = json['tax_percentage'];
    minimumFreeDeliveryOrderQty =
        int.tryParse(json['minimum_free_delivery_order_qty'].toString()) ?? 1;
    productDeliveryCharge = json['product_delivery_charge'].toString();

    netAmount = json['net_amount'].toString();

    taxAmount = json['tax_amount'].toString();

    subTotal = double.parse((json['sub_total'] ?? 0.0).toString());

    if (json['product_variants'] != null && json['product_variants'] != '') {
      productVariants = <ProductVariant>[];
      json['product_variants'].forEach((v) {
        productVariants!.add(ProductVariant.fromJson(v));
      });
    }
    if (json['product_details'] != null) {
      productDetails = <Product>[];
      json['product_details'].forEach((v) {
        Product product = Product.fromJson(v);
        if (product.type == comboProductType ||
            (product.type != comboProductType &&
                product.variants != null &&
                product.variants!.isNotEmpty)) {
          productDetails!.add(product);
        }
      });
    }
    removeProductInProgress = false;
  }
  double getDiscoutPercentage() {
    if (specialPrice != 0.0) {
      return ((price! - specialPrice!) * 100) / price!;
    }
    return 0.0;
  }
}
