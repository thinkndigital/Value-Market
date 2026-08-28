class Zipcode {
  int? id;
  String? zipcode;
  int? cityId;
  int? minimumFreeDeliveryOrderAmount;
  int? deliveryCharges;
  String? createdAt;
  String? updatedAt;

  Zipcode(
      {this.id,
      this.zipcode,
      this.cityId,
      this.minimumFreeDeliveryOrderAmount,
      this.deliveryCharges,
      this.createdAt,
      this.updatedAt});

  Zipcode.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    zipcode = json['zipcode'];
    cityId = json['city_id'];
    minimumFreeDeliveryOrderAmount = json['minimum_free_delivery_order_amount'];
    deliveryCharges = json['delivery_charges'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }



}
