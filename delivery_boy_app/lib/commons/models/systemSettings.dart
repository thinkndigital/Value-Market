class SystemSettings {
  final String? appName;
  final String? supportNumber;
  final String? supportEmail;
  final String? logo;
  final String? favicon;

  String? currentVersionOfAndroidAppForDeliveryBoy;
  String? currentVersionOfIosAppForDeliveryBoy;
  final int? orderDeliveryOtpSystem;
  final int? versionSystemStatus;
  final int? deliveryBoyAppMaintenanceStatus;
  final String? systemTimezone;
  final String? deliveryBoyBonus;
  final String? messageForDeliveryBoyApp;
  CurrencySetting? currencySetting;
  final String? playStoreLinkForDeliveryBoyApp;
  final String? appStoreLinkForDeliveryBoyApp;
  SystemSettings({
    this.appName,
    this.supportNumber,
    this.deliveryBoyAppMaintenanceStatus,
    this.versionSystemStatus,
    this.supportEmail,
    this.logo,
    this.favicon,
    this.currentVersionOfAndroidAppForDeliveryBoy,
    this.currentVersionOfIosAppForDeliveryBoy,
    this.orderDeliveryOtpSystem,
    this.systemTimezone,
    this.deliveryBoyBonus,
    this.messageForDeliveryBoyApp,
    this.currencySetting,
    this.playStoreLinkForDeliveryBoyApp,
    this.appStoreLinkForDeliveryBoyApp,
  });

  SystemSettings.fromJson(Map<String, dynamic> json)
    : appName = json['app_name'] as String?,
      supportNumber = json['support_number'] as String?,
      supportEmail = json['support_email'] as String?,
      logo = json['logo'] as String?,
      favicon = json['favicon'] as String?,

      orderDeliveryOtpSystem = json['order_delivery_otp_system'] as int?,
      systemTimezone = json['system_timezone'] as String?,
      deliveryBoyBonus = json['delivery_boy_bonus'] as String?,
      currentVersionOfAndroidAppForDeliveryBoy =
          json['current_version_of_android_app_for_delivery_boy'] as String?,
      currentVersionOfIosAppForDeliveryBoy =
          json['current_version_of_ios_app_for_delivery_boy'] as String?,
      messageForDeliveryBoyApp =
          json['message_for_delivery_boy_app'] as String?,
      versionSystemStatus = json["version_system_status"] ?? 0,
      deliveryBoyAppMaintenanceStatus =
          json["delivery_boy_app_maintenance_status"] ?? 0,
      currencySetting =
          (json['currency_setting'] as Map<String, dynamic>?) != null
              ? CurrencySetting.fromJson(
                json['currency_setting'] as Map<String, dynamic>,
              )
              : null,

      playStoreLinkForDeliveryBoyApp =
          json['play_store_link_for_delivery_boy_app'] as String?,
      appStoreLinkForDeliveryBoyApp =
          json['app_store_link_for_delivery_boy_app'] as String?;

  Map<String, dynamic> toJson() => {
    'app_name': appName,
    'support_number': supportNumber,
    'support_email': supportEmail,
    'message_for_delivery_boy_app': messageForDeliveryBoyApp,
    'logo': logo,
    'favicon': favicon,

    'order_delivery_otp_system': orderDeliveryOtpSystem,
    'system_timezone': systemTimezone,
    'delivery_boy_bonus': deliveryBoyBonus,
    'version_system_status': versionSystemStatus,
    'delivery_boy_app_maintenance_status': deliveryBoyAppMaintenanceStatus,
    'currency_setting': currencySetting?.toJson(),
  };
}

class CurrencySetting {
  final int? id;
  final String? name;
  final String? code;
  final String? symbol;
  final String? exchangeRate;
  final int? isDefault;
  final int? status;
  final String? createdAt;
  final String? updatedAt;

  CurrencySetting({
    this.id,
    this.name,
    this.code,
    this.symbol,
    this.exchangeRate,
    this.isDefault,
    this.status,
    this.createdAt,
    this.updatedAt,
  });

  CurrencySetting copyWith({
    int? id,
    String? name,
    String? code,
    String? symbol,
    String? exchangeRate,
    int? isDefault,
    int? status,
    String? createdAt,
    String? updatedAt,
  }) {
    return CurrencySetting(
      id: id ?? this.id,
      name: name ?? this.name,
      code: code ?? this.code,
      symbol: symbol ?? this.symbol,
      exchangeRate: exchangeRate ?? this.exchangeRate,
      isDefault: isDefault ?? this.isDefault,
      status: status ?? this.status,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  CurrencySetting.fromJson(Map<String, dynamic> json)
    : id = json['id'] as int?,
      name = json['name'] as String?,
      code = json['code'] as String?,
      symbol = json['symbol'] as String?,
      exchangeRate = json['exchange_rate'] as String?,
      isDefault = json['is_default'] as int?,
      status = json['status'] as int?,
      createdAt = json['created_at'] as String?,
      updatedAt = json['updated_at'] as String?;

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'code': code,
    'symbol': symbol,
    'exchange_rate': exchangeRate,
    'is_default': isDefault,
    'status': status,
    'created_at': createdAt,
    'updated_at': updatedAt,
  };
}
