import 'package:eshoppro_deliveryboy/commons/models/systemSettings.dart';

class Settings {
  String? currency;
  String? termsConditions;
  String? privacyPolicy;
  String? decimalPoint;
  String? aboutUs;
  String? contactUs;
  String? supportedLocals;
  SystemSettings? systemSettings;
  Settings({
    this.currency,
    this.termsConditions,
    this.decimalPoint,
    this.supportedLocals,
    this.systemSettings,
    this.aboutUs,
    this.contactUs,
    this.privacyPolicy,
  });

  Settings.fromJson(Map<String, dynamic> json) {
    currency = json['currency'];
    decimalPoint = json['decimal_point'] ?? "";
    supportedLocals = json['supported_locals'] ?? "";
    if (json['data'] != null) {
      termsConditions = json['data']['delivery_boy_terms_and_conditions'] ?? "";
      privacyPolicy = json['data']['delivery_boy_privacy_policy'] ?? "";
      aboutUs = json['data']['about_us'] ?? "";
      contactUs = json['data']['contact_us'] ?? "";
    }
    systemSettings = SystemSettings.fromJson((json['system_settings'] ?? {}));
  }
}
