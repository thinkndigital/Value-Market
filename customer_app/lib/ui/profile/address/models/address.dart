class Address {
  int? id;
  int? userId;
  String? name;
  String? type;
  String? mobile;
  String? alternateMobile;
  String? address;
  String? landmark;
  String? areaId;
  int? cityId;
  String? city;
  String? area;
  String? pincode;
  int? systemPincode;
  int? countryCode;
  String? state;
  String? country;
  double? latitude;
  double? longitude;
  int? isDefault;
  String? updatedAt;
  String? createdAt;
  int? minimumFreeDeliveryOrderAmount;
  int? deliveryCharges;
  bool? deleteInProgress;

  Address({
    this.id,
    this.userId,
    this.name,
    this.type,
    this.mobile,
    this.alternateMobile,
    this.address,
    this.landmark,
    this.areaId,
    this.cityId,
    this.city,
    this.area,
    this.pincode,
    this.systemPincode,
    this.countryCode,
    this.state,
    this.country,
    this.latitude,
    this.longitude,
    this.isDefault,
    this.updatedAt,
    this.createdAt,
    this.minimumFreeDeliveryOrderAmount,
    this.deliveryCharges,
    this.deleteInProgress,
  });

  Address.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    userId = json['user_id'];
    name = json['name'] ?? '';
    type = json['type'];
    mobile = json['mobile'] ?? '';
    alternateMobile = json['alternate_mobile'];
    address = json['address'] ?? '';
    landmark = json['landmark'] ?? '';
    areaId = json['area_id'];
    cityId = (json['city_id'] == null || json['city_id'] == '')
        ? null
        : json['city_id'];
    city = json['city'] ?? '';
    area = json['area'] ?? '';
    pincode = json['pincode'] ?? '';
    systemPincode = json['system_pincode'];
    countryCode = json['country_code'];
    state = json['state'] ?? '';
    country = json['country'] ?? '';
    latitude = double.tryParse((json['latitude'] ?? 0).toString().isEmpty
        ? "0"
        : (json['latitude'] ?? 0).toString());
    longitude = double.tryParse((json['longitude'] ?? 0).toString().isEmpty
        ? "0"
        : (json['longitude'] ?? 0).toString());

    isDefault = json['is_default'];
    updatedAt = json['updated_at'];
    createdAt = json['created_at'];
    minimumFreeDeliveryOrderAmount = json['minimum_free_delivery_order_amount'];
    deliveryCharges = json['delivery_charges'];
    deleteInProgress = false;
  }
  Address copyWith({
    int? id,
    int? userId,
    String? name,
    String? type,
    String? mobile,
    String? alternateMobile,
    String? address,
    String? landmark,
    String? areaId,
    int? cityId,
    String? city,
    String? area,
    String? pincode,
    int? systemPincode,
    int? countryCode,
    String? state,
    String? country,
    double? latitude,
    double? longitude,
    int? isDefault,
    String? updatedAt,
    String? createdAt,
    int? minimumFreeDeliveryOrderAmount,
    int? deliveryCharges,
    bool? deleteInProgress,
  }) {
    return Address(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      name: name ?? this.name,
      type: type ?? this.type,
      mobile: mobile ?? this.mobile,
      alternateMobile: alternateMobile ?? this.alternateMobile,
      address: address ?? this.address,
      landmark: landmark ?? this.landmark,
      areaId: areaId ?? this.areaId,
      cityId: cityId ?? this.cityId,
      city: city ?? this.city,
      area: area ?? this.area,
      pincode: pincode ?? this.pincode,
      systemPincode: systemPincode ?? this.systemPincode,
      countryCode: countryCode ?? this.countryCode,
      state: state ?? this.state,
      country: country ?? this.country,
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      isDefault: isDefault ?? this.isDefault,
      updatedAt: updatedAt ?? this.updatedAt,
      createdAt: createdAt ?? this.createdAt,
      minimumFreeDeliveryOrderAmount:
          minimumFreeDeliveryOrderAmount ?? this.minimumFreeDeliveryOrderAmount,
      deliveryCharges: deliveryCharges ?? this.deliveryCharges,
      deleteInProgress: deleteInProgress ?? this.deleteInProgress,
    );
  }
}
