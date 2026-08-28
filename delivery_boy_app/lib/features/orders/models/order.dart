class Order {
  int? id;
  int? userId;
  int? storeId;
  int? addressId;
  String? mobile;
  double? total;
  double? totalUnitPrice;
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
  List<dynamic>? attachments;
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
      this.totalUnitPrice,
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
    total = double.tryParse((json['total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['total'] ?? 0).toString());
    totalUnitPrice = double.tryParse(
        (json['total_unit_price'] ?? 0).toString().isEmpty
            ? "0"
            : (json['total_unit_price'] ?? 0).toString());
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

    finalTotal = double.tryParse((json['final_total'] ?? 0).toString().isEmpty
        ? "0"
        : (json['final_total'] ?? 0).toString());

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
    if (json['attachments'] != null) {
      attachments = <Null>[];
      json['attachments'].forEach((v) {
        attachments!.add(v);
      });
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
    if (json['order_items'] != null) {
      orderItems = <OrderItems>[];
      json['order_items'].forEach((v) {
        orderItems!.add(new OrderItems.fromJson(v));
      });
    }
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
  double? price;
  double? discountedPrice;
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
  double? specialPrice;
  String? mainPrice;
  String? image;
  String? pickupLocation;
  double? weight;
  String? productRating;
  String? userRating;
  List<dynamic>? userRatingImages;
  String? userRatingComment;
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
      this.subTotalMainPrice,
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
    otp = int.parse(json['otp'].toString());
    productName = json['product_name'];
    variantName = json['variant_name'];
    productVariantId = json['product_variant_id'];
    quantity = json['quantity'];
    price = double.tryParse((json['price'] ?? 0).toString().isEmpty
        ? "0"
        : (json['price'] ?? 0).toString());

    discountedPrice = double.tryParse(
        (json['discounted_price'] ?? 0).toString().isEmpty
            ? "0"
            : (json['discounted_price'] ?? 0).toString());
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
    specialPrice = double.tryParse(
        (json['special_price'] ?? 0).toString().isEmpty
            ? "0"
            : (json['special_price'] ?? 0).toString());
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

/*   Map<String, dynamic> toJson() {
final Map<String, dynamic> data = new Map<String, dynamic>();
data['id'] = this.id;
data['user_id'] = this.userId;
data['store_id'] = this.storeId;
data['order_id'] = this.orderId;
data['delivery_boy_id'] = this.deliveryBoyId;
data['seller_id'] = this.sellerId;
data['is_credited'] = this.isCredited;
data['otp'] = this.otp;
data['product_name'] = this.productName;
data['variant_name'] = this.variantName;
data['product_variant_id'] = this.productVariantId;
data['quantity'] = this.quantity;
data['price'] = this.price;
data['discounted_price'] = this.discountedPrice;
data['tax_percent'] = this.taxPercent;
data['tax_amount'] = this.taxAmount;
data['discount'] = this.discount;
data['sub_total'] = this.subTotal;
data['deliver_by'] = this.deliverBy;
data['updated_by'] = this.updatedBy;
if (this.status != null) {
data['status'] = this.status!.map((v) => v.toJson()).toList();
}
data['admin_commission_amount'] = this.adminCommissionAmount;
data['seller_commission_amount'] = this.sellerCommissionAmount;
data['active_status'] = this.activeStatus;
data['hash_link'] = this.hashLink;
data['is_sent'] = this.isSent;
data['order_type'] = this.orderType;
data['created_at'] = this.createdAt;
data['updated_at'] = this.updatedAt;
data['product_id'] = this.productId;
data['is_cancelable'] = this.isCancelable;
data['is_prices_inclusive_tax'] = this.isPricesInclusiveTax;
data['cancelable_till'] = this.cancelableTill;
data['product_type'] = this.productType;
data['slug'] = this.slug;
data['download_allowed'] = this.downloadAllowed;
data['download_link'] = this.downloadLink;
data['store_name'] = this.storeName;
data['seller_longitude'] = this.sellerLongitude;
data['seller_mobile'] = this.sellerMobile;
data['seller_address'] = this.sellerAddress;
data['seller_latitude'] = this.sellerLatitude;
data['delivery_boy_name'] = this.deliveryBoyName;
data['store_description'] = this.storeDescription;
data['seller_rating'] = this.sellerRating;
data['seller_profile'] = this.sellerProfile;
data['courier_agency'] = this.courierAgency;
data['tracking_id'] = this.trackingId;
data['awb_code'] = this.awbCode;
data['url'] = this.url;
data['seller_name'] = this.sellerName;
data['is_returnable'] = this.isReturnable;
data['special_price'] = this.specialPrice;
data['main_price'] = this.mainPrice;
data['image'] = this.image;
data['pickup_location'] = this.pickupLocation;
data['weight'] = this.weight;
data['product_rating'] = this.productRating;
data['user_rating'] = this.userRating;
if (this.userRatingImages != null) {
data['user_rating_images'] =
this.userRatingImages!.map((v) => v.toJson()).toList();
}
data['user_rating_comment'] = this.userRatingComment;
data['order_counter'] = this.orderCounter;
data['order_cancel_counter'] = this.orderCancelCounter;
data['order_return_counter'] = this.orderReturnCounter;
data['net_amount'] = this.netAmount;
data['varaint_ids'] = this.varaintIds;
data['variant_values'] = this.variantValues;
data['attr_name'] = this.attrName;
data['name'] = this.name;
data['image_sm'] = this.imageSm;
data['image_md'] = this.imageMd;
data['is_already_returned'] = this.isAlreadyReturned;
data['is_already_cancelled'] = this.isAlreadyCancelled;
data['return_request_submitted'] = this.returnRequestSubmitted;
data['shiprocket_order_tracking_url'] = this.shiprocketOrderTrackingUrl;
data['email'] = this.email;
return data;
}
*/
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
