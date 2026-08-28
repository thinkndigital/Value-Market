import 'package:eshopplus_seller/core/constants/appConstants.dart';

import 'permissions.dart';



class Store {
  final int? id;
  final String name;
  final String description;
  final int? isSingleSellerOrderSystem;
  int? isDefaultStore;
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
  final int? storeStatus; // 1 -- approved 2--Not approved
  final String? productDeliverabilityType;
  final Permissions? permissions;
  final String? noteForUploadOtherDocuments;
  final List<CustomField> customFields;

  Store({
    this.id,
    this.permissions,
    required this.productDeliverabilityType,
    required this.backgroundColor,
    required this.name,
    required this.description,
    this.isSingleSellerOrderSystem,
    this.isDefaultStore,
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
    this.storeStatus,
    this.noteForUploadOtherDocuments,
    this.customFields = const [],
  });

  Store copyWith(
      {int? id,
      String? backgroundColor,
      String? productDeliverabilityType,
      String? name,
      String? description,
      int? isSingleSellerOrderSystem,
      int? isDefaultStore,
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
      int? storeStatus,
      Permissions? permission,
      String? noteForUploadOtherDocuments,
      List<CustomField>? customFields}) {
    return Store(
      productDeliverabilityType:
          productDeliverabilityType ?? this.productDeliverabilityType,
      backgroundColor: backgroundColor ?? this.backgroundColor,
      id: id ?? this.id,
      permissions: permission ?? this.permissions,
      name: name ?? this.name,
      description: description ?? this.description,
      isSingleSellerOrderSystem:
          isSingleSellerOrderSystem ?? this.isSingleSellerOrderSystem,
      isDefaultStore: isDefaultStore ?? this.isDefaultStore,
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
      storeStatus: storeStatus ?? this.storeStatus,
      noteForUploadOtherDocuments:
          noteForUploadOtherDocuments ?? this.noteForUploadOtherDocuments,
      customFields: customFields ?? this.customFields,
    );
  }

  Store.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int?,
        name = (json['name'] ?? "").toString(),
        description = (json['description'] ?? "").toString(),
        isSingleSellerOrderSystem =
            json['is_single_seller_order_system'] as int?,
        isDefaultStore = json['is_default_store'] ?? 0,
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
        storeStatus = json['store_status'] as int?,
        productDeliverabilityType =
            json["product_deliverability_type"] ?? zipcodeWiseDeliverability,
        permissions = json['permissions'] != null
            ? Permissions.fromJson(json['permissions'])
            : null,
        noteForUploadOtherDocuments =
            json['note_for_necessary_documents'] ?? '',
        customFields = (json['custom_fields'] as List<dynamic>?)
                ?.map((e) => CustomField.fromJson(e as Map<String, dynamic>))
                .toList() ??
            const [];

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'description': description,
        'is_single_seller_order_system': isSingleSellerOrderSystem,
        'is_default_store': isDefaultStore,
        'primary_color': primaryColor,
        'secondary_color': secondaryColor,
        'active_color': activeColor,
        'hover_color': hoverColor,
        'background_color': backgroundColor,
        'store_settings': storeSettings?.toJson(),
        'image': image,
        'banner_image': bannerImage,
        'on_boarding_image': onBoardingImage,
        'on_boarding_video': onBoardingVideo,
        'banner_image_for_most_selling_product':
            bannerImageForMostSellingProduct,
        'stack_image': stackImage,
        'login_image': loginImage,
        'status': status,
        "product_deliverability_type": productDeliverabilityType,
        "permissions": permissions,
        "custom_fields": customFields.map((e) => e.toJson()).toList(),
      };

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

  StoreSettings copyWith({
    String? storeStyle,
    String? productStyle,
    String? categorySectionTitle,
    String? categoryStyle,
    String? categoryCardStyle,
    String? brandStyle,
    String? offersStyle,
    String? offerSliderStyle,
  }) {
    return StoreSettings(
      storeStyle: storeStyle ?? this.storeStyle,
      productStyle: productStyle ?? this.productStyle,
      categorySectionTitle: categorySectionTitle ?? this.categorySectionTitle,
      categoryStyle: categoryStyle ?? this.categoryStyle,
      categoryCardStyle: categoryCardStyle ?? this.categoryCardStyle,
      brandStyle: brandStyle ?? this.brandStyle,
      offersStyle: offersStyle ?? this.offersStyle,
      offerSliderStyle: offerSliderStyle ?? this.offerSliderStyle,
    );
  }

  StoreSettings.fromJson(Map<String, dynamic> json)
      : storeStyle = json['store_style'] as String?,
        productStyle = json['product_style'] as String?,
        categorySectionTitle = json['category_section_title'] as String?,
        categoryStyle = json['category_style'] as String?,
        categoryCardStyle = json['category_card_style'] as String?,
        brandStyle = json['brand_style'] as String?,
        offersStyle = json['offers_style'] as String?,
        offerSliderStyle = json['offer_slider_style'] as String?;

  Map<String, dynamic> toJson() => {
        'store_style': storeStyle,
        'product_style': productStyle,
        'category_section_title': categorySectionTitle,
        'category_style': categoryStyle,
        'category_card_style': categoryCardStyle,
        'brand_style': brandStyle,
        'offers_style': offersStyle,
        'offer_slider_style': offerSliderStyle
      };
}
class CustomField {
  final int id;
  final String name;
  final String type;
  final int? fieldLength;
  final num? min;
  final num? max;
  final bool required;
  final bool active;
  final List<String> options;

  CustomField({
    required this.id,
    required this.name,
    required this.type,
    this.fieldLength,
    this.min,
    this.max,
    required this.required,
    required this.active,
    required this.options,
  });

  factory CustomField.fromJson(Map<String, dynamic> json) {
    return CustomField(
      id: json['id'],
      name: json['name'],
      type: json['type'],
      fieldLength: json['field_length'],
      min: json['min'],
      max: json['max'],
      required: json['required'] ?? false,
      active: json['active'] ?? true,
      options: (json['options'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          [],
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'type': type,
        'field_length': fieldLength,
        'min': min,
        'max': max,
        'required': required,
        'active': active,
        'options': options,
      };
}
