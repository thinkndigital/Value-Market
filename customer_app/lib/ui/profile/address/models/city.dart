class City {
  int? id;
  String? name;
  int? minimumFreeDeliveryOrderAmount;
  int? deliveryCharges;

  City(
      {this.id,
      this.name,
      this.minimumFreeDeliveryOrderAmount,
      this.deliveryCharges});

  City.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    name = json['name'];
    minimumFreeDeliveryOrderAmount = json['minimum_free_delivery_order_amount'];
    deliveryCharges = json['delivery_charges'];
  }


}
