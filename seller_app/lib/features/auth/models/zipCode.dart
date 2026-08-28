class Zipcode {
  int? id;
  String? zipcode;
  String? cityName;
  String? deliveryCharges;
  String? minimumFreeDeliveryOrderAmount;

  Zipcode({
    required this.id,
    required this.zipcode,
    required this.cityName,
    required this.deliveryCharges,
    required this.minimumFreeDeliveryOrderAmount,
  });
  Zipcode.fromJson(Map<String, dynamic> json) {
    id = json["id"];

    if (json.containsKey("zipcode")) zipcode = json["zipcode"] ?? "";
    if (json.containsKey("city_name")) {
      cityName = json["city_name"] ?? "";
    } else if (json.containsKey("name")) {
      cityName = json["name"] ?? "";
    }
    deliveryCharges = (json["delivery_charges"] ?? 0).toString();
    minimumFreeDeliveryOrderAmount =
        (json["minimum_free_delivery_order_amount"] ?? 0).toString();
  }
}
