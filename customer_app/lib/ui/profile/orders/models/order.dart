import 'package:eshop_plus/core/api/apiEndPoints.dart';

class Order {
  int? id;
  int? userId;
  int? storeId;
  int? addressId;
  String? mobile;
  double? total;
  double? itemTotal;
  double? deliveryCharge;
  String? isDeliveryChargeReturnable;
  double? walletBalance;
  String? promoCodeId;
  double? promoDiscount;
  double? discount;
  double? totalPayable;
  double? finalTotal;
  String? paymentMethod;
  String? latitude;
  String? longitude;
  String? address;
  String? deliveryTime;
  String? deliveryDate;
  String? otp;
  String? email;
  String? notes;
  String? isPosOrder;
  String? type;
  String? orderPaymentCurrencyId;
  String? orderPaymentCurrencyCode;
  String? baseCurrencyCode;
  String? orderPaymentCurrencyConversionRate;
  String? createdAt;
  String? updatedAt;
  String? username;
  String? countryCode;
  String? name;
  String? downloadAllowed;
  String? pickupLocation;
  String? orderRecipientPerson;
  String? specialPrice;
  String? price;
  String? sellerDeliveryCharge;
  String? sellerPromoDiscount;
  String? orderType;
  List<OrderAttachment>? attachments;
  String? courierAgency;
  String? trackingId;
  String? url;
  String? isShiprocketOrder;
  String? isReturnable;
  String? isCancelable;
  String? isAlreadyReturned;
  String? isAlreadyCancelled;
  String? returnRequestSubmitted;
  String? totalTaxPercent;
  String? totalTaxAmount;
  List<OrderItems>? orderItems;

  Order(
      {this.id,
      this.userId,
      this.storeId,
      this.addressId,
      this.mobile,
      this.total,
      this.itemTotal,
      this.deliveryCharge,
      this.isDeliveryChargeReturnable,
      this.walletBalance,
      this.promoCodeId,
      this.promoDiscount,
      this.discount,
      this.totalPayable,
      this.finalTotal,
      this.paymentMethod,
      this.latitude,
      this.longitude,
      this.address,
      this.deliveryTime,
      this.deliveryDate,
      this.otp,
      this.email,
      this.notes,
      this.isPosOrder,
      this.type,
      this.orderPaymentCurrencyId,
      this.orderPaymentCurrencyCode,
      this.baseCurrencyCode,
      this.orderPaymentCurrencyConversionRate,
      this.createdAt,
      this.updatedAt,
      this.username,
      this.countryCode,
      this.name,
      this.downloadAllowed,
      this.pickupLocation,
      this.orderRecipientPerson,
      this.specialPrice,
      this.price,
      this.sellerDeliveryCharge,
      this.sellerPromoDiscount,
      this.orderType,
      this.attachments,
      this.courierAgency,
      this.trackingId,
      this.url,
      this.isShiprocketOrder,
      this.isReturnable,
      this.isCancelable,
      this.isAlreadyReturned,
      this.isAlreadyCancelled,
      this.returnRequestSubmitted,
      this.totalTaxPercent,
      this.totalTaxAmount,
      this.orderItems});

  Order.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    userId = json['user_id'];
    storeId = json['store_id'];
    addressId = json['address_id'];
    mobile = json['mobile'];
    if (json['order_items'] != null) {
      orderItems = <OrderItems>[];
      json['order_items'].forEach((v) {
        orderItems!.add(new OrderItems.fromJson(v));
      });
    }
    total = double.tryParse((json[ApiURL.totalKey] ?? 0).toString().isEmpty
        ? "0"
        : (json[ApiURL.totalKey] ?? 0).toString());
    itemTotal =
        itemTotalCalculated; /*  double.tryParse((json['item_total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['item_total'] ?? 0).toString()); */
    deliveryCharge = double.tryParse(
        (json['delivery_charge'] ?? 0).toString().isEmpty
            ? "0"
            : (json['delivery_charge'] ?? 0).toString());
    isDeliveryChargeReturnable =
        json['is_delivery_charge_returnable'].toString();
    walletBalance = double.tryParse(
        (json['wallet_balance'] ?? 0).toString().isEmpty
            ? "0"
            : (json['wallet_balance'] ?? 0).toString());
    promoCodeId = json['promo_code_id'].toString();
    promoDiscount = double.tryParse(
        (json['promo_discount'] ?? 0).toString().isEmpty
            ? "0"
            : (json['promo_discount'] ?? 0).toString());

    discount = double.tryParse((json['discount'] ?? 0).toString().isEmpty
        ? "0"
        : (json['discount'] ?? 0).toString());
    totalPayable = double.tryParse(
        (json['total_payable'] ?? 0).toString().isEmpty
            ? "0"
            : (json['total_payable'] ?? 0).toString());

    finalTotal =
        finalTotalCalculated; /* double.tryParse((json['final_total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['final_total'] ?? 0).toString()); */

    paymentMethod = json['payment_method'].toString();
    latitude = json['latitude'].toString();
    longitude = json['longitude'].toString();
    address = json['address'].toString();
    deliveryTime = json['delivery_time'].toString();
    deliveryDate = json['delivery_date'];
    otp = json['otp'].toString();
    email = json['email'];
    notes = json['notes'];
    isPosOrder = json['is_pos_order'].toString();
    type = json['type'];
    orderPaymentCurrencyId = json['order_payment_currency_id'].toString();
    orderPaymentCurrencyCode = json['order_payment_currency_code'].toString();
    baseCurrencyCode = json['base_currency_code'].toString();
    orderPaymentCurrencyConversionRate =
        json['order_payment_currency_conversion_rate'].toString();
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    username = json['username'];
    countryCode = json['country_code'].toString();
    name = json['name'];
    downloadAllowed = (json['download_allowed'] ?? 0).toString();
    pickupLocation = json['pickup_location'];
    orderRecipientPerson = json['order_recipient_person'];
    specialPrice = json['special_price'].toString();
    price = json['price'].toString();
    sellerDeliveryCharge = json['seller_delivery_charge'].toString();
    sellerPromoDiscount = json['seller_promo_discount'].toString();
    orderType = json['order_type'];
    if (json['attachments'] != null && json['attachments'] is List) {
      attachments = (json['attachments'] as List)
          .map((e) => OrderAttachment.fromJson(e))
          .toList();
    }
    courierAgency = json['courier_agency'];
    trackingId = json['tracking_id'].toString();
    url = json['url'];
    isShiprocketOrder = json['is_shiprocket_order'].toString();
    isReturnable = json['is_returnable'].toString().toString();
    isCancelable = (json['is_cancelable'] ?? 0).toString();

    isAlreadyReturned = json['is_already_returned'];
    isAlreadyCancelled = json['is_already_cancelled'];
    returnRequestSubmitted = json['return_request_submitted'];
    totalTaxPercent = json['total_tax_percent'];
    totalTaxAmount = json['total_tax_amount'];
  }

  double get itemTotalCalculated {
    if (orderItems == null) return 0.0;
    return orderItems!.fold(0.0, (sum, item) {
      final subtotal = (item.subTotalMainPrice ?? 0);

      return sum + subtotal;
    });
  }

  double get finalTotalCalculated {
    if (orderItems == null) return 0.0;
    double itemsTotal = orderItems!.fold(0.0, (sum, item) {
      final subtotalMain = (item.subTotal ?? 0);
      return sum + subtotalMain;
    });
    double delivery = deliveryCharge ?? 0;
    double promo = promoDiscount ?? 0;
    double wallet = walletBalance ?? 0;
    return itemsTotal + delivery - promo - wallet;
  }
}

class OrderItems {
  int? id;
  int? userId;
  int? storeId;
  int? orderId;
  String? deliveryBoyId;
  int? sellerId;
  int? isCredited;
  int? otp;
  String? productName;
  String? variantName;
  int? productVariantId;
  int? quantity;
  String? price;
  String? discountedPrice;
  String? taxPercent;
  double? taxAmount;
  String? discount;
  double? subTotal; // subTotal of discountedPrice
  double? subTotalMainPrice; // subTotal of mainPrice
  String? deliverBy;
  String? updatedBy;
  List<StatusEntry>? status;
  List<String>? statusNameList;
  String? adminCommissionAmount;
  String? sellerCommissionAmount;
  String? activeStatus;
  String? hashLink;
  int? isSent;
  String? orderType;
  String? createdAt;
  String? updatedAt;
  int? productId;
  int? isCancelable;
  int? isPricesInclusiveTax;
  String? cancelableTill;
  String? productType;
  String? slug;
  int? downloadAllowed;
  String? downloadLink;
  String? storeName;
  String? sellerLongitude;
  String? sellerMobile;
  String? sellerAddress;
  String? sellerLatitude;
  String? deliveryBoyName;
  String? storeDescription;
  String? sellerRating;
  String? sellerProfile;
  String? courierAgency;
  String? trackingId;
  String? awbCode;
  String? url;
  String? sellerName;
  String? isReturnable;
  String? specialPrice;
  String? mainPrice;
  String? image;
  String? pickupLocation;
  double? weight;
  String? productRating;
  String? userRating;
  List<dynamic>? userRatingImages;
  String? userRatingComment;
  String? userRatingTitle;
  String? orderCounter;
  String? orderCancelCounter;
  String? orderReturnCounter;
  double? netAmount;
  String? varaintIds;
  String? variantValues;
  String? attrName;
  String? name;
  String? imageSm;
  String? imageMd;
  String? isAlreadyReturned;
  String? isAlreadyCancelled;
  String? returnRequestSubmitted;
  String? shiprocketOrderTrackingUrl;
  String? email;

  OrderItems(
      {this.id,
      this.userId,
      this.storeId,
      this.orderId,
      this.deliveryBoyId,
      this.sellerId,
      this.isCredited,
      this.otp,
      this.productName,
      this.variantName,
      this.productVariantId,
      this.quantity,
      this.price,
      this.discountedPrice,
      this.taxPercent,
      this.taxAmount,
      this.discount,
      this.subTotal,
      this.deliverBy,
      this.updatedBy,
      this.status,
      this.statusNameList,
      this.adminCommissionAmount,
      this.sellerCommissionAmount,
      this.activeStatus,
      this.hashLink,
      this.isSent,
      this.orderType,
      this.createdAt,
      this.updatedAt,
      this.productId,
      this.isCancelable,
      this.isPricesInclusiveTax,
      this.cancelableTill,
      this.productType,
      this.slug,
      this.downloadAllowed,
      this.downloadLink,
      this.storeName,
      this.sellerLongitude,
      this.sellerMobile,
      this.sellerAddress,
      this.sellerLatitude,
      this.deliveryBoyName,
      this.storeDescription,
      this.sellerRating,
      this.sellerProfile,
      this.courierAgency,
      this.trackingId,
      this.awbCode,
      this.url,
      this.sellerName,
      this.isReturnable,
      this.specialPrice,
      this.mainPrice,
      this.image,
      this.pickupLocation,
      this.weight,
      this.productRating,
      this.userRating,
      this.userRatingImages,
      this.userRatingComment,
      this.userRatingTitle,
      this.orderCounter,
      this.orderCancelCounter,
      this.orderReturnCounter,
      this.netAmount,
      this.varaintIds,
      this.variantValues,
      this.attrName,
      this.name,
      this.imageSm,
      this.imageMd,
      this.isAlreadyReturned,
      this.isAlreadyCancelled,
      this.returnRequestSubmitted,
      this.shiprocketOrderTrackingUrl,
      this.email});

  OrderItems.fromJson(Map<String, dynamic> json) {
    statusNameList = <String>[];
    id = json['id'];
    userId = json['user_id'];
    storeId = json['store_id'];
    orderId = json['order_id'] as int?;
    deliveryBoyId = (json['delivery_boy_id'] ?? '').toString();
    sellerId = json['seller_id'];
    isCredited = json['is_credited'];
    otp = json['otp'];
    productName = json['product_name'];
    variantName = json['variant_name'];
    productVariantId = json['product_variant_id'];
    quantity = json['quantity'];
    price = json['price'].toString();
    discountedPrice = json['discounted_price'].toString();
    taxPercent = json['tax_percent'].toString();
    taxAmount = double.tryParse((json['tax_amount'] ?? 0).toString());
    discount = json['discount'].toString();
    subTotal = double.tryParse((json['sub_total'] ?? 0).toString());
    subTotalMainPrice =
        double.tryParse((json['sub_total_of_price'] ?? 0).toString());
    deliverBy = json['deliver_by'].toString();
    updatedBy = json['updated_by'].toString();
    if (json['status'] != null) {
      status = (json['status'] as List).map((e) {
        statusNameList!.add(e[0]);
        return StatusEntry.fromJson(e as List);
      }).toList();
    }
    adminCommissionAmount = json['admin_commission_amount'].toString();
    sellerCommissionAmount = json['seller_commission_amount'].toString();
    activeStatus = json['active_status'];
    hashLink = json['hash_link'];
    isSent = json['is_sent'];
    orderType = json['order_type'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    productId = json['product_id'];
    isCancelable = json['is_cancelable'];
    isPricesInclusiveTax = json['is_prices_inclusive_tax'];
    cancelableTill = json['cancelable_till'];
    productType = json['product_type'];
    slug = json['slug'];
    downloadAllowed = json['download_allowed'];
    downloadLink = json['download_link'];
    storeName = json['store_name'];
    sellerLongitude = json['seller_longitude'];
    sellerMobile = json['seller_mobile'];
    sellerAddress = json['seller_address'];
    sellerLatitude = json['seller_latitude'];
    deliveryBoyName = json['delivery_boy_name'];
    storeDescription = json['store_description'];
    sellerRating = json['seller_rating'];
    sellerProfile = json['seller_profile'];
    courierAgency = json['courier_agency'];
    trackingId = json['tracking_id'];
    awbCode = json['awb_code'];
    url = json['url'];
    sellerName = json['seller_name'];
    isReturnable = json['is_returnable'].toString();
    specialPrice = json['special_price'].toString();
    mainPrice = json['main_price'].toString();
    image = json['image'];
    pickupLocation = json['pickup_location'];
    weight = double.tryParse((json['weight'] ?? 0).toString());
    productRating = json['product_rating'];
    userRating = json['user_rating'].toString();
    if (json['user_rating_images'] != [] &&
        json['user_rating_images'] != null) {
      userRatingImages = <String>[];
      json['user_rating_images'].forEach((v) {
        userRatingImages!.add(v);
      });
    }
    userRatingComment = json['user_rating_comment'];
    userRatingTitle = json['user_rating_title'];
    orderCounter = json['order_counter'].toString();
    orderCancelCounter = json['order_cancel_counter'].toString();

    orderReturnCounter = json['order_return_counter'].toString();

    netAmount = double.tryParse((json['net_amount'] ?? 0).toString());
    varaintIds = json['varaint_ids'];
    variantValues = json['variant_values'];
    attrName = json['attr_name'];
    name = json['name'];
    imageSm = json['image_sm'];
    imageMd = json['image_md'];
    isAlreadyReturned = json['is_already_returned'].toString();
    isAlreadyCancelled = json['is_already_cancelled'].toString();
    returnRequestSubmitted = json['return_request_submitted'].toString();
    shiprocketOrderTrackingUrl = json['shiprocket_order_tracking_url'];
    email = json['email'];
  }
}

class StatusEntry {
  final String status;
  final String timestamp;

  StatusEntry({required this.status, required this.timestamp});

  factory StatusEntry.fromJson(List<dynamic> json) {
    return StatusEntry(
      status: json[0] as String,
      timestamp: json[1] as String,
    );
  }

  List<dynamic> toJson() {
    return [status, timestamp];
  }
}

class OrderAttachment {
  final int id;
  final String attachment;
  final int banktransferStatus;

  OrderAttachment({
    required this.id,
    required this.attachment,
    required this.banktransferStatus,
  });

  factory OrderAttachment.fromJson(Map<String, dynamic> json) {
    return OrderAttachment(
      id: int.tryParse(json['id'].toString()) ?? 0,
      attachment: json['attachment'] ?? '',
      banktransferStatus:
          int.tryParse(json['banktransfer_status'].toString()) ?? 0,
    );
  }
}
