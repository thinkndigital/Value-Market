import 'package:eshop_plus/core/configs/appConfig.dart';
import 'package:eshop_plus/core/constants/appConstants.dart';

class SystemSettings {
  final String? appName;
  final String? supportNumber;
  final String? supportEmail;
  final String? logo;
  final String? favicon;
  final List<String>? onBoardingImage;
  final List<String>? onBoardingVideo;
  final String? storageType;
  final String? onBoardingMediaType;
  final String? currentVersionOfAndroidApp;
  final String? currentVersionOfIosApp;
  final int? orderDeliveryOtpSystem;
  final String? systemTimezone;
  final String? minimumCartAmount;
  final String? maximumItemAllowedInCart;
  final String? lowStockLimit;
  final String? maxDaysToReturnItem;
  final String? deliveryBoyBonus;
  final int? enableCartButtonOnProductListView;
  final int? versionSystemStatus;
  final int? expandProductImage;
  final String? taxName;
  final String? taxNumber;
  final int? google;
  final int? facebook;
  final int? apple;
  final int? referAndEarnStatus;
  final String? minimumReferAndEarnAmount;
  final String? minimumReferAndEarnBonus;
  final String? referAndEarnMethod;
  final String? maxReferAndEarnAmount;
  final String? numberOfTimesBonusGivenToCustomer;
  final int? walletBalanceStatus;
  final String? walletBalanceAmount;
  final String? authenticationMethod;

  final String? supportedLocals;
  final String? storeCurrency;
  final String? decimalPoint;
  final int? singleSellerOrderSystem;
  final int? customerAppMaintenanceStatus;
  final int? sellerAppMaintenanceStatus;
  final int? deliveryBoyAppMaintenanceStatus;
  final String? messageForCustomerApp;
  final String? messageForSellerApp;
  final String? messageForDeliveryBoyApp;
  final int? navbarFixed;
  final int? themeMode;
  final CurrencySetting? currencySetting;
  final String? playStoreLinkForCustomerApp;
  final String? appStoreLinkForCustomerApp;

  SystemSettings({
    this.appName,
    this.supportNumber,
    this.supportEmail,
    this.logo,
    this.favicon,
    this.onBoardingImage,
    this.onBoardingVideo,
    this.storageType,
    this.onBoardingMediaType,
    this.currentVersionOfAndroidApp,
    this.currentVersionOfIosApp,
    this.orderDeliveryOtpSystem,
    this.systemTimezone,
    this.minimumCartAmount,
    this.maximumItemAllowedInCart,
    this.lowStockLimit,
    this.maxDaysToReturnItem,
    this.deliveryBoyBonus,
    this.enableCartButtonOnProductListView,
    this.versionSystemStatus,
    this.expandProductImage,
    this.taxName,
    this.taxNumber,
    this.google,
    this.facebook,
    this.apple,
    this.referAndEarnStatus,
    this.minimumReferAndEarnAmount,
    this.minimumReferAndEarnBonus,
    this.referAndEarnMethod,
    this.maxReferAndEarnAmount,
    this.numberOfTimesBonusGivenToCustomer,
    this.walletBalanceStatus,
    this.walletBalanceAmount,
    this.authenticationMethod,
    this.supportedLocals,
    this.storeCurrency,
    this.decimalPoint,
    this.singleSellerOrderSystem,
    this.customerAppMaintenanceStatus,
    this.sellerAppMaintenanceStatus,
    this.deliveryBoyAppMaintenanceStatus,
    this.messageForCustomerApp,
    this.messageForSellerApp,
    this.messageForDeliveryBoyApp,
    this.navbarFixed,
    this.themeMode,
    this.currencySetting,
    this.playStoreLinkForCustomerApp,
    this.appStoreLinkForCustomerApp,
  });

  SystemSettings.fromJson(Map<String, dynamic> json)
      : appName = json['app_name'] as String?,
        supportNumber = json['support_number'] as String?,
        supportEmail = json['support_email'] as String?,
        logo = json['logo'] as String?,
        favicon = json['favicon'] as String?,
        onBoardingImage = json['on_boarding_image'] == ''
            ? []
            : (json['on_boarding_image'] as List?)
                ?.map((dynamic e) => e as String)
                .toList(),
        onBoardingVideo = json['on_boarding_video'] == ''
            ? []
            : (json['on_boarding_video'] as List?)
                ?.map((dynamic e) => e as String)
                .toList(),
        storageType = json['storage_type'] as String?,
        onBoardingMediaType = json['on_boarding_media_type'] as String?,
        currentVersionOfAndroidApp =
            json['current_version_of_android_app'] as String?,
        currentVersionOfIosApp = json['current_version_of_ios_app'] as String?,
        orderDeliveryOtpSystem = json['order_delivery_otp_system'] as int?,
        systemTimezone = json['system_timezone'] as String?,
        minimumCartAmount = json['minimum_cart_amount'] as String?,
        maximumItemAllowedInCart =
            json['maximum_item_allowed_in_cart'] as String?,
        lowStockLimit = json['low_stock_limit'] as String?,
        maxDaysToReturnItem = (json['max_days_to_return_item'] ?? 1).toString(),
        deliveryBoyBonus = json['delivery_boy_bonus'] as String?,
        enableCartButtonOnProductListView =
            json['enable_cart_button_on_product_list_view'] as int?,
        versionSystemStatus = json['version_system_status'] as int?,
        expandProductImage = json['expand_product_image'] as int?,
        taxName = json['tax_name'] as String?,
        taxNumber = json['tax_number'] as String?,
        google = json['google'] as int?,
        facebook = json['facebook'] as int?,
        apple = json['apple'] as int?,
        referAndEarnStatus = json['refer_and_earn_status'] as int?,
        minimumReferAndEarnAmount =
            json['minimum_refer_and_earn_amount'] as String?,
        minimumReferAndEarnBonus =
            json['minimum_refer_and_earn_bonus'] as String?,
        referAndEarnMethod = json['refer_and_earn_method'] as String?,
        maxReferAndEarnAmount = json['max_refer_and_earn_amount'] as String?,
        numberOfTimesBonusGivenToCustomer =
            json['number_of_times_bonus_given_to_customer'] as String?,
        walletBalanceStatus = json['wallet_balance_status'] as int?,
        walletBalanceAmount = json['wallet_balance_amount'] as String?,
        authenticationMethod = json['authentication_method'] as String?,
        supportedLocals = json['supported_locals'] as String?,
        storeCurrency = json['store_currency'] as String?,
        decimalPoint = json['decimal_point'] as String?,
        singleSellerOrderSystem = json['single_seller_order_system'] as int?,
        customerAppMaintenanceStatus =
            json['customer_app_maintenance_status'] ?? 0,
        sellerAppMaintenanceStatus = json['seller_app_maintenance_status'] ?? 0,
        deliveryBoyAppMaintenanceStatus =
            json['delivery_boy_app_maintenance_status'] ?? 0,
        messageForCustomerApp = json['message_for_customer_app'] as String?,
        messageForSellerApp = json['message_for_seller_app'] as String?,
        messageForDeliveryBoyApp =
            json['message_for_delivery_boy_app'] as String?,
        navbarFixed = json['navbar_fixed'] as int?,
        themeMode = json['theme_mode'] as int?,
        currencySetting =
            (json['currency_setting'] as Map<String, dynamic>?) != null
                ? CurrencySetting.fromJson(
                    json['currency_setting'] as Map<String, dynamic>)
                : null,
        playStoreLinkForCustomerApp = (json[
                    'play_store_link_for_customer_app'] ??
                'https://play.google.com/store/apps/details?id=$androidPackageName')
            .toString(),
        appStoreLinkForCustomerApp =
            json['app_store_link_for_customer_app'].toString();

  bool showVideosInOnBoardingScreen() {
    return onBoardingMediaType == videoMediaType;
  }

  bool showImagesInOnBoardingScreen() {
    return onBoardingMediaType == imageMediaType;
  }
}

class CurrencySetting {
  final int? id;
  final String? name;
  final String? code;
  final String? symbol;
  final String? exchangeRate;
  final String? isDefault;
  final String? status;
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

  CurrencySetting.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int?,
        name = json['name'] as String?,
        code = json['code'] as String?,
        symbol = json['symbol'] as String?,
        exchangeRate = json['exchange_rate'].toString(),
        isDefault = json['is_default'].toString(),
        status = json['status'].toString(),
        createdAt = json['created_at'] as String?,
        updatedAt = json['updated_at'] as String?;
}
