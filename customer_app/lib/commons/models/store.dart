class Store {
  final int? id;
  final String name;
  final String description;
  final int? isSingleSellerOrderSystem;
  final int? isDefaultStore;
  final int? panelDefaultStore;
  final String primaryColor;
  final String secondaryColor;
  final String activeColor;
  final String hoverColor;
  final String backgroundColor;
  final StoreSettings? storeSettings;
  final String image;
  final String bannerImage;
  final String? onBoardingImage;
  final String? onBoardingVideo;
  final String bannerImageForMostSellingProduct;
  final String stackImage;
  final String loginImage;
  final int? status;
  String? deliveryChargeType;
  int? deliveryChargeAmount;
  int? minimumFreeDeliveryAmount;
  String? productDeliverabilityType;

  Store(
      {this.id,
      required this.backgroundColor,
      required this.name,
      required this.description,
      this.isSingleSellerOrderSystem,
      this.isDefaultStore,
      this.panelDefaultStore,
      required this.primaryColor,
      required this.secondaryColor,
      required this.activeColor,
      required this.hoverColor,
      this.storeSettings,
      required this.image,
      required this.bannerImage,
      this.onBoardingImage,
      this.onBoardingVideo,
      required this.bannerImageForMostSellingProduct,
      required this.stackImage,
      required this.loginImage,
      this.status,
      this.deliveryChargeType,
      this.deliveryChargeAmount,
      this.minimumFreeDeliveryAmount,
      this.productDeliverabilityType});

  Store copyWith({
    int? id,
    String? backgroundColor,
    String? name,
    String? description,
    int? isSingleSellerOrderSystem,
    int? isDefaultStore,
    int? panelDefaultStore,
    String? primaryColor,
    String? secondaryColor,
    String? activeColor,
    String? hoverColor,
    StoreSettings? storeSettings,
    String? image,
    String? bannerImage,
    String? onBoardingImage,
    String? onBoardingVideo,
    String? bannerImageForMostSellingProduct,
    String? stackImage,
    String? loginImage,
    int? status,
    String? deliveryChargeType,
    int? deliveryChargeAmount,
    int? minimumFreeDeliveryAmount,
    String? productDeliverabilityType,
  }) {
    return Store(
      backgroundColor: backgroundColor ?? this.backgroundColor,
      id: id ?? this.id,
      name: name ?? this.name,
      description: description ?? this.description,
      isSingleSellerOrderSystem:
          isSingleSellerOrderSystem ?? this.isSingleSellerOrderSystem,
      isDefaultStore: isDefaultStore ?? this.isDefaultStore,
      panelDefaultStore: panelDefaultStore ?? this.panelDefaultStore,
      primaryColor: primaryColor ?? this.primaryColor,
      secondaryColor: secondaryColor ?? this.secondaryColor,
      activeColor: activeColor ?? this.activeColor,
      hoverColor: hoverColor ?? this.hoverColor,
      storeSettings: storeSettings ?? this.storeSettings,
      image: image ?? this.image,
      bannerImage: bannerImage ?? this.bannerImage,
      onBoardingImage: onBoardingImage ?? this.onBoardingImage,
      onBoardingVideo: onBoardingVideo ?? this.onBoardingVideo,
      bannerImageForMostSellingProduct: bannerImageForMostSellingProduct ??
          this.bannerImageForMostSellingProduct,
      stackImage: stackImage ?? this.stackImage,
      loginImage: loginImage ?? this.loginImage,
      status: status ?? this.status,
      deliveryChargeType: deliveryChargeType ?? this.deliveryChargeType,
      deliveryChargeAmount: deliveryChargeAmount ?? this.deliveryChargeAmount,
      minimumFreeDeliveryAmount:
          minimumFreeDeliveryAmount ?? this.minimumFreeDeliveryAmount,
      productDeliverabilityType:
          productDeliverabilityType ?? this.productDeliverabilityType,
    );
  }

  Store.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int?,
        name = (json['name'] ?? "").toString(),
        description = (json['description'] ?? "").toString(),
        isSingleSellerOrderSystem =
            json['is_single_seller_order_system'] as int?,
        isDefaultStore = json['is_default_store'] ?? 0,
        panelDefaultStore = json['is_default_store'] ?? 0,
        primaryColor = json['primary_color'] ?? '',
        secondaryColor = json['secondary_color'] ?? '',
        activeColor = json['active_color'] ?? '',
        backgroundColor = json['background_color'] ?? '',
        hoverColor = json['hover_color'] ?? '',
        storeSettings =
            (json['store_settings'] as Map<String, dynamic>?) != null
                ? StoreSettings.fromJson(
                    json['store_settings'] as Map<String, dynamic>)
                : null,
        image = json['image'] ?? '',
        bannerImage = json['banner_image'] ?? '',
        onBoardingImage = json['on_boarding_image'] as String?,
        onBoardingVideo = json['on_boarding_video'] as String?,
        bannerImageForMostSellingProduct =
            json['banner_image_for_most_selling_product'] ?? '',
        stackImage = json['stack_image'] ?? '',
        loginImage = json['login_image'] ?? '',
        status = json['status'] as int?,
        deliveryChargeType = json['delivery_charge_type'],
        deliveryChargeAmount = json['delivery_charge_amount'],
        minimumFreeDeliveryAmount = json['minimum_free_delivery_amount'],
        productDeliverabilityType = json['product_deliverability_type'];

  bool isStoreDefault() {
    return isDefaultStore == 1;
  }
}

class StoreSettings {
  final String? storeStyle;
  final String? productStyle;
  final String? categorySectionTitle;
  final String? categoryStyle;
  final String? categoryCardStyle;
  final String? brandStyle;
  final String? offersStyle;
  final String? offerSliderStyle;

  StoreSettings({
    this.storeStyle,
    this.productStyle,
    this.categorySectionTitle,
    this.categoryStyle,
    this.categoryCardStyle,
    this.brandStyle,
    this.offersStyle,
    this.offerSliderStyle,
  });

  StoreSettings.fromJson(Map<String, dynamic> json)
      : storeStyle = json['store_style'] as String?,
        productStyle = json['product_style'] as String?,
        categorySectionTitle = json['category_section_title'] as String?,
        categoryStyle = json['category_style'] as String?,
        categoryCardStyle = json['category_card_style'] as String?,
        brandStyle = json['brand_style'] as String?,
        offersStyle = json['offers_style'] as String?,
        offerSliderStyle = json['offer_slider_style'] as String?;
}
