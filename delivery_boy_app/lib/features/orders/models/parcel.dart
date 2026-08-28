import 'package:value_market_delivery_boy/features/orders/models/deliveryBoy.dart';
import 'package:value_market_delivery_boy/features/orders/models/order.dart';

class Parcel {
  int? id;
  String? username;
  String? email;
  String? mobile;
  int? orderId;
  String? name;
  String? parcelName;
  String? longitude;
  String? latitude;
  String? createdDate;
  int? otp;
  int? sellerId;
  String? paymentMethod;
  String? userAddress;
  String? userProfile;
  double? total;
  double? totalUnitPrice;
  double? deliveryCharge;
  int? deliveryBoyId;
  double? walletBalance;
  double? discount;
  double? taxPercent;
  double? taxAmount;
  double? promoDiscount;
  double? totalPayable;
  double? finalTotal;
  String? notes;
  String? deliveryDate;
  String? deliveryTime;
  int? isCodCollected;
  int? isShiprocketOrder;
  String? activeStatus;
  List<StatusEntry>? status;
  TrackingDetails? trackingDetails;
  TrackingDetails? cancelledTrackingDetails;
  List<ParcelItems>? items;
  SellerData? sellerDetails;
  DeliveryBoy? deliveryBoyDetails;
  double? subTotal;
  OrderItems? orderItems;
  bool? isReturnOrder = false;
  Parcel(
      {this.id,
      this.username,
      this.email,
      this.mobile,
      this.orderId,
      this.name,
      this.parcelName,
      this.longitude,
      this.latitude,
      this.createdDate,
      this.otp,
      this.sellerId,
      this.paymentMethod,
      this.userAddress,
      this.userProfile,
      this.total,
      this.totalUnitPrice,
      this.deliveryCharge,
      this.deliveryBoyId,
      this.walletBalance,
      this.discount,
      this.taxPercent,
      this.taxAmount,
      this.promoDiscount,
      this.totalPayable,
      this.finalTotal,
      this.notes,
      this.deliveryDate,
      this.deliveryTime,
      this.isCodCollected,
      this.isShiprocketOrder,
      this.activeStatus,
      this.status,
      this.trackingDetails,
      this.cancelledTrackingDetails,
      this.items,
      this.sellerDetails,
      this.deliveryBoyDetails,
      this.subTotal,
      this.orderItems,
      this.isReturnOrder});

  Parcel.fromJson(Map<String, dynamic> json, bool returnedOrder) {
    id = int.parse(json['id'].toString());
    if (json['items'] != null) {
      items = <ParcelItems>[];
      json['items'].forEach((v) {
        items!.add(ParcelItems.fromJson(v));
      });
    }
    if (returnedOrder) {
      orderItems = OrderItems.fromJson(json);
      isReturnOrder = true;
    }
    username = json['username'];
    email = json['email'];
    mobile = json['mobile'];
    orderId = json['order_id'];
    name = json['name'];
    parcelName = json['parcel_name'] ?? json['product_name'];
    longitude = json['longitude'];
    latitude = json['latitude'];
    createdDate = json['created_date'] ?? json['created_at'];
    otp = json['otp'];
    sellerId = json['seller_id'];
    paymentMethod = json['payment_method'];
    userAddress = json['user_address'];
    userProfile = json['user_profile'];
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
    deliveryBoyId = json['delivery_boy_id'];
    walletBalance = double.tryParse(
        (json['wallet_balance'] ?? 0).toString().isEmpty
            ? "0"
            : (json['wallet_balance'] ?? 0).toString());
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

    promoDiscount = double.tryParse(
        (json['promo_discount'] ?? 0).toString().isEmpty
            ? "0"
            : (json['promo_discount'] ?? 0).toString());
    taxPercent = double.tryParse((json['tax_percent'] ?? 0).toString().isEmpty
        ? "0"
        : (json['tax_percent'] ?? 0).toString());
    taxAmount = double.tryParse((json['tax_amount'] ?? 0).toString().isEmpty
        ? "0"
        : (json['tax_amount'] ?? 0).toString());

    notes = json['notes'];
    deliveryDate = json['delivery_date'];
    deliveryTime = json['delivery_time'];
    isCodCollected = json['is_cod_collected'];
    isShiprocketOrder = json['is_shiprocket_order'];
    activeStatus = json['active_status'];
    if (json['status'] != null) {
      status = <StatusEntry>[];
      json['status'].forEach((v) {
        status!.add(StatusEntry.fromJson(v));
      });
    }
    trackingDetails = json['tracking_details'] != null
        ? TrackingDetails.fromJson(json['tracking_details'])
        : null;
    // cancelledTrackingDetails = json['cancelled_tracking_details'] != null
    //     ? TrackingDetails.fromJson(json['cancelled_tracking_details'])
    //     : null;
    if (returnedOrder) {
      subTotal = double.tryParse((json['sub_total'] ?? 0).toString().isEmpty
          ? "0"
          : (json['sub_total'] ?? 0).toString());
    } else {
      subTotal = itemTotalCalculated;
    }
    finalTotal = finalTotalCalculated;
    sellerDetails = json['seller_details'] != null
        ? SellerData.fromJson(json['seller_details'])
        : null;
    deliveryBoyDetails = json['delivery_boy_details'] != null
        ? DeliveryBoy.fromJson(json['delivery_boy_details'])
        : null;
  }
  double get itemTotalCalculated {
    if (items == null) return 0.0;
    return items!.fold(0.0, (sum, item) {
      if (item.orderData != null && item.orderData!.isNotEmpty) {
        final subtotal = (item.orderData!.first.subTotalMainPrice ?? 0);

        return sum + subtotal;
      }
      return 0.0;
    });
  }

  double get finalTotalCalculated {
    if (items == null) return 0.0;
    double itemsTotal = items!.fold(0.0, (sum, item) {
      if (item.orderData != null && item.orderData!.isNotEmpty) {
        final subtotalMain = (item.orderData!.first.subTotal ?? 0);
        return sum + subtotalMain;
      }
      return 0.0;
    });
    double delivery = deliveryCharge ?? 0;
    double promo = promoDiscount ?? 0;
    double wallet = walletBalance ?? 0;
    return itemsTotal + delivery - promo - wallet;
  }
}

class SellerData {
  String? storeName;
  String? sellerName;
  String? address;
  String? mobile;
  String? storeImage;
  String? latitude;
  String? longitude;

  SellerData(
      {this.storeName,
      this.sellerName,
      this.address,
      this.mobile,
      this.storeImage,
      this.latitude,
      this.longitude});

  SellerData.fromJson(Map<String, dynamic> json) {
    storeName = json['store_name'];
    sellerName = json['seller_name'];
    address = json['address'];
    mobile = json['mobile'];
    storeImage = json['store_image'];
    latitude = json['latitude'];
    longitude = json['longitude'];
  }
}

class TrackingDetails {
  int? id;
  int? orderId;
  int? shiprocketOrderId;
  int? shipmentId;
  int? courierCompanyId;
  String? awbCode;
  int? pickupStatus;
  String? pickupScheduledDate;
  String? pickupTokenNumber;
  int? status;
  String? others;
  String? pickupGeneratedDate;
  String? data;
  String? date;
  int? isCanceled;
  String? manifestUrl;
  String? labelUrl;
  String? invoiceUrl;
  String? orderItemId;
  String? courierAgency;
  String? trackingId;
  int? parcelId;
  String? url;
  String? createdAt;
  String? updatedAt;

  TrackingDetails(
      {this.id,
      this.orderId,
      this.shiprocketOrderId,
      this.shipmentId,
      this.courierCompanyId,
      this.awbCode,
      this.pickupStatus,
      this.pickupScheduledDate,
      this.pickupTokenNumber,
      this.status,
      this.others,
      this.pickupGeneratedDate,
      this.data,
      this.date,
      this.isCanceled,
      this.manifestUrl,
      this.labelUrl,
      this.invoiceUrl,
      this.orderItemId,
      this.courierAgency,
      this.trackingId,
      this.parcelId,
      this.url,
      this.createdAt,
      this.updatedAt});

  TrackingDetails.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    orderId = json['order_id'];
    shiprocketOrderId = json['shiprocket_order_id'];
    shipmentId = json['shipment_id'];
    courierCompanyId = json['courier_company_id'];
    awbCode = json['awb_code'];
    pickupStatus = json['pickup_status'];
    pickupScheduledDate = json['pickup_scheduled_date'];
    pickupTokenNumber = json['pickup_token_number'];
    status = json['status'];
    others = json['others'];
    pickupGeneratedDate = json['pickup_generated_date'];
    data = json['data'];
    date = json['date'];
    isCanceled = json['is_canceled'];
    manifestUrl = json['manifest_url'];
    labelUrl = json['label_url'];
    invoiceUrl = json['invoice_url'];
    orderItemId = json['order_item_id'];
    courierAgency = json['courier_agency'];
    trackingId = json['tracking_id'];
    parcelId = json['parcel_id'];
    url = json['url'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }
}

class ParcelItems {
  int? id;
  int? productVariantId;
  int? orderItemId;
  double? unitPrice;
  int? quantity;
  int? total;
  List<OrderItems>? orderData;
  List<OrderDetails>? orderDetails;

  ParcelItems({
    this.id,
    this.productVariantId,
    this.orderItemId,
    this.unitPrice,
    this.quantity,
    this.total,
    this.orderData,
    this.orderDetails,
  });

  ParcelItems.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    productVariantId = json['product_variant_id'];
    orderItemId = json['order_item_id'];
    unitPrice = double.tryParse((json['unit_price'] ?? 0).toString().isEmpty
        ? "0"
        : (json['unit_price'] ?? 0).toString());
    quantity = json['quantity'];
    total = json['total'];
    orderData = [];
    if (json['order_data'] != null) {
      orderData = <OrderItems>[];
      json['order_data'].forEach((v) {
        orderData!.add(OrderItems.fromJson(v));
      });
    }
    if (json['order_details'] != null) {
      orderDetails = <OrderDetails>[];
      json['order_details'].forEach((v) {
        orderDetails!.add(OrderDetails.fromJson(v));
      });
    }
  }
}

class OrderDetails {
  String? isReturnable;
  String? isCancelable;
  String? isAlreadyReturned;
  String? isAlreadyCancelled;
  String? returnRequestSubmitted;
  String? username;
  String? totalTaxPercent;
  String? totalTaxAmount;

  OrderDetails(
      {this.isReturnable,
      this.isCancelable,
      this.isAlreadyReturned,
      this.isAlreadyCancelled,
      this.returnRequestSubmitted,
      this.username,
      this.totalTaxPercent,
      this.totalTaxAmount});

  OrderDetails.fromJson(Map<String, dynamic> json) {
    isReturnable = json['is_returnable'];
    isCancelable = json['is_cancelable'];
    isAlreadyReturned = json['is_already_returned'];
    isAlreadyCancelled = json['is_already_cancelled'];
    returnRequestSubmitted = json['return_request_submitted'];
    username = json['username'];
    totalTaxPercent = json['total_tax_percent'];
    totalTaxAmount = json['total_tax_amount'];
  }
}
