class Permissions {
  int? requireProductsApproval;
  int? customerPrivacy;
  int? viewOrderOtp;
  int? assignDeliveryBoy;

  Permissions(
      {requireProductsApproval,
      customerPrivacy,
      viewOrderOtp,
      assignDeliveryBoy});

  Permissions.fromJson(Map<String, dynamic> json) {
    requireProductsApproval = json['require_products_approval'];
    customerPrivacy = json['customer_privacy'];
    viewOrderOtp = json['view_order_otp'];
    assignDeliveryBoy = json['assign_delivery_boy'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['require_products_approval'] = requireProductsApproval;
    data['customer_privacy'] = customerPrivacy;
    data['view_order_otp'] = viewOrderOtp;
    data['assign_delivery_boy'] = assignDeliveryBoy;
    return data;
  }
}
