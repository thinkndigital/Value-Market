import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_customer/commons/product/models/attribute.dart';
import 'package:value_market_customer/commons/product/models/productMinMaxPrice.dart';
import 'package:value_market_customer/commons/product/models/productVariant.dart';
import 'package:value_market_customer/ui/favorites/repositories/favoritesRepository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

const String simpleProductType = "simple_product";
const String variableProductType = "variable_product";
const String digitalProductType = "digital_product";
const String comboProductType = "combo-product";

class Product {
  final int? id;
  final int? storeId;
  final int? categoryId;
  final int? sellerId;
  final String? tax;
  final int? rowOrder;

  ///[simple_product, variable_product, digital_product,combo-product]
  final String? type;

  ///[0,1,2]
  ///[0=For simple product]
  ///[1 = For variable product check for product]
  ///[2 = Also for variable product but at a variant vise]
  final String? stockType;

  final String? name;
  final String? shortDescription;
  final String? slug;
  final String? productIds;
  final String? productVariantIds;

  ///[0,1,2] [0 - none, 1 - veg, 2 - non veg]

  final int? codAllowed;
  final int? downloadAllowed;
  final String? downloadType;
  final String? downloadLink;
  final int? minimumOrderQuantity;
  final int? quantityStepSize;
  final int? totalAllowedQuantity;
  final int? isPricesInclusiveTax;
  final int? isReturnable;
  final int? isCancelable;
  final String? cancelableTill;
  final String? image;
  final List<String>? otherImages;
  final String? videoType;
  final String? video;
  final List<String>? tags;

  final String? madeIn;

  final String? brand;
  final String? sku;
  final String? stock;

  ///[""= Not set, 0 = Out of stock, 1 = In stock]
  final String? availability;
  final String? rating;
  final int? noOfRatings;
  final String? description;
  final String? extraDescription;
  final int? deliverableType;
  final String? deliverableZipcodes;
  final int? cityDeliverableType;
  final String? deliverableCities;
  final String? pickupLocation;
  final int? status;
  final int? minimumFreeDeliveryOrderQty;
  final double? deliveryCharges;
  final String? createdAt;
  final String? updatedAt;
  final String? brandName;
  final String? brandSlug;
  final String? sellerRating;
  final String? sellerSlug;
  final String? sellerNoOfRatings;
  final String? sellerProfile;
  final String? storeName;
  final String? storeDescription;
  final String? sellerName;
  final String? categoryName;
  final String? categorySlug;
  final String? taxPercentage;
  final String? taxId;
  final String? attrValueIds;
  final String? productType;
  final String? relativePath;
  final String? videoRelativePath;
  final String? attributeValueIds;
  final String? deliverableZipcodesIds;
  final bool? isDeliverable;
  String? isFavorite;
  final String? imageMd;
  final String? imageSm;
  final List<String>? otherImagesSm;
  final List<String>? otherImagesMd;
  final String? totalStock;
  final List<Attribute>? attributes;
  final List<ProductVariant>? variants;
  final ProductMinMaxPrice? minMaxPrice;
  ProductVariant? selectedVariant;
  List<ProductVariant>? availableVariants;
  //below 3 params are used for the combo product only
  List<Product>? productDetails;
  final String? price;
  final String? specialPrice;
  String? productName;
  String? totalQuantitySold;
  String? totalSales;
  bool? removeFavoriteInProgress;
  int? isAttachmentRequired;
  final bool? bestSeller;
  final bool? newArrival;
  final List<Map<String, dynamic>>? customFields;

  Product(
      {this.id,
      this.attributes,
      this.storeId,
      this.variants,
      this.categoryId,
      this.sellerId,
      this.tax,
      this.rowOrder,
      this.minMaxPrice,
      this.type,
      this.stockType,
      this.name,
      this.shortDescription,
      this.slug,
      this.productIds,
      this.productVariantIds,
      this.codAllowed,
      this.downloadAllowed,
      this.downloadType,
      this.downloadLink,
      this.minimumOrderQuantity,
      this.quantityStepSize,
      this.totalAllowedQuantity,
      this.isPricesInclusiveTax,
      this.isReturnable,
      this.isCancelable,
      this.cancelableTill,
      this.image,
      this.otherImages,
      this.videoType,
      this.video,
      this.tags,
      this.madeIn,
      this.brand,
      this.sku,
      this.stock,
      this.availability,
      this.rating,
      this.noOfRatings,
      this.description,
      this.extraDescription,
      this.deliverableType,
      this.deliverableZipcodes,
      this.cityDeliverableType,
      this.deliverableCities,
      this.pickupLocation,
      this.status,
      this.minimumFreeDeliveryOrderQty,
      this.deliveryCharges,
      this.createdAt,
      this.updatedAt,
      this.brandName,
      this.brandSlug,
      this.sellerRating,
      this.sellerSlug,
      this.sellerNoOfRatings,
      this.sellerProfile,
      this.storeName,
      this.storeDescription,
      this.sellerName,
      this.categoryName,
      this.categorySlug,
      this.taxPercentage,
      this.taxId,
      this.attrValueIds,
      this.productType,
      this.relativePath,
      this.videoRelativePath,
      this.attributeValueIds,
      this.deliverableZipcodesIds,
      this.isDeliverable,
      this.isFavorite,
      this.imageMd,
      this.imageSm,
      this.otherImagesSm,
      this.otherImagesMd,
      this.totalStock,
      this.selectedVariant,
      this.availableVariants,
      this.productDetails,
      this.price,
      this.specialPrice,
      this.productName,
      this.totalQuantitySold,
      this.totalSales,
      this.removeFavoriteInProgress,
      this.isAttachmentRequired,
      this.bestSeller,
      this.newArrival,
      this.customFields});

  Product copyWith(
      {int? id,
      int? storeId,
      int? categoryId,
      int? sellerId,
      String? tax,
      int? rowOrder,
      List<Attribute>? attributes,
      String? type,
      String? stockType,
      ProductMinMaxPrice? minMaxPrice,
      String? name,
      String? shortDescription,
      String? slug,
      String? productIds,
      String? productVariantIds,
      int? codAllowed,
      int? downloadAllowed,
      String? downloadType,
      String? downloadLink,
      int? minimumOrderQuantity,
      int? quantityStepSize,
      int? totalAllowedQuantity,
      int? isPricesInclusiveTax,
      int? isReturnable,
      int? isCancelable,
      String? cancelableTill,
      String? image,
      List<String>? otherImages,
      String? videoType,
      String? video,
      List<String>? tags,
      String? madeIn,
      String? brand,
      String? sku,
      String? stock,
      String? availability,
      String? rating,
      int? noOfRatings,
      String? description,
      String? extraDescription,
      int? deliverableType,
      String? deliverableZipcodes,
      int? cityDeliverableType,
      String? deliverableCities,
      String? pickupLocation,
      int? status,
      int? minimumFreeDeliveryOrderQty,
      double? deliveryCharges,
      String? createdAt,
      String? updatedAt,
      String? brandName,
      String? brandSlug,
      String? sellerRating,
      String? sellerSlug,
      String? sellerNoOfRatings,
      String? sellerProfile,
      String? storeName,
      String? storeDescription,
      String? sellerName,
      String? categoryName,
      String? categorySlug,
      String? taxPercentage,
      String? taxId,
      String? attrValueIds,
      String? productType,
      String? relativePath,
      String? videoRelativePath,
      String? attributeValueIds,
      String? deliverableZipcodesIds,
      bool? isDeliverable,
      String? isFavorite,
      String? imageMd,
      String? imageSm,
      List<String>? otherImagesSm,
      List<String>? otherImagesMd,
      String? totalStock,
      List<ProductVariant>? variants,
      List<Product>? productDetails,
      final String? price,
      final String? specialPrice,
      final ProductVariant? selectedVariant,
      List<ProductVariant>? availableVariants,
      int? isAttachmentRequired,
      bool? bestSeller,
      bool? newArrival}) {
    return Product(
        id: id ?? this.id,
        minMaxPrice: minMaxPrice ?? this.minMaxPrice,
        attributes: attributes ?? this.attributes,
        variants: variants ?? this.variants,
        storeId: storeId ?? this.storeId,
        categoryId: categoryId ?? this.categoryId,
        sellerId: sellerId ?? this.sellerId,
        tax: tax ?? this.tax,
        rowOrder: rowOrder ?? this.rowOrder,
        type: type ?? this.type,
        stockType: stockType ?? this.stockType,
        name: name ?? this.name,
        shortDescription: shortDescription ?? this.shortDescription,
        slug: slug ?? this.slug,
        productIds: productIds ?? this.productIds,
        productVariantIds: productVariantIds ?? this.productVariantIds,
        codAllowed: codAllowed ?? this.codAllowed,
        downloadAllowed: downloadAllowed ?? this.downloadAllowed,
        downloadType: downloadType ?? this.downloadType,
        downloadLink: downloadLink ?? this.downloadLink,
        minimumOrderQuantity: minimumOrderQuantity ?? this.minimumOrderQuantity,
        quantityStepSize: quantityStepSize ?? this.quantityStepSize,
        totalAllowedQuantity: totalAllowedQuantity ?? this.totalAllowedQuantity,
        isPricesInclusiveTax: isPricesInclusiveTax ?? this.isPricesInclusiveTax,
        isReturnable: isReturnable ?? this.isReturnable,
        isCancelable: isCancelable ?? this.isCancelable,
        cancelableTill: cancelableTill ?? this.cancelableTill,
        image: image ?? this.image,
        otherImages: otherImages ?? this.otherImages,
        videoType: videoType ?? this.videoType,
        video: video ?? this.video,
        tags: tags ?? this.tags,
        madeIn: madeIn ?? this.madeIn,
        brand: brand ?? this.brand,
        sku: sku ?? this.sku,
        stock: stock ?? this.stock,
        availability: availability ?? this.availability,
        rating: rating ?? this.rating,
        noOfRatings: noOfRatings ?? this.noOfRatings,
        description: description ?? this.description,
        extraDescription: extraDescription ?? this.extraDescription,
        deliverableType: deliverableType ?? this.deliverableType,
        deliverableZipcodes: deliverableZipcodes ?? this.deliverableZipcodes,
        cityDeliverableType: cityDeliverableType ?? this.cityDeliverableType,
        deliverableCities: deliverableCities ?? this.deliverableCities,
        pickupLocation: pickupLocation ?? this.pickupLocation,
        status: status ?? this.status,
        minimumFreeDeliveryOrderQty:
            minimumFreeDeliveryOrderQty ?? this.minimumFreeDeliveryOrderQty,
        deliveryCharges: deliveryCharges ?? this.deliveryCharges,
        createdAt: createdAt ?? this.createdAt,
        updatedAt: updatedAt ?? this.updatedAt,
        brandName: brandName ?? this.brandName,
        brandSlug: brandSlug ?? this.brandSlug,
        sellerRating: sellerRating ?? this.sellerRating,
        sellerSlug: sellerSlug ?? this.sellerSlug,
        sellerNoOfRatings: sellerNoOfRatings ?? this.sellerNoOfRatings,
        sellerProfile: sellerProfile ?? this.sellerProfile,
        storeName: storeName ?? this.storeName,
        storeDescription: storeDescription ?? this.storeDescription,
        sellerName: sellerName ?? this.sellerName,
        categoryName: categoryName ?? this.categoryName,
        categorySlug: categorySlug ?? this.categorySlug,
        taxPercentage: taxPercentage ?? this.taxPercentage,
        taxId: taxId ?? this.taxId,
        attrValueIds: attrValueIds ?? this.attrValueIds,
        productType: productType ?? this.productType,
        relativePath: relativePath ?? this.relativePath,
        videoRelativePath: videoRelativePath ?? this.videoRelativePath,
        attributeValueIds: attributeValueIds ?? this.attributeValueIds,
        deliverableZipcodesIds:
            deliverableZipcodesIds ?? this.deliverableZipcodesIds,
        isDeliverable: isDeliverable ?? this.isDeliverable,
        isFavorite: isFavorite ?? this.isFavorite,
        imageMd: imageMd ?? this.imageMd,
        imageSm: imageSm ?? this.imageSm,
        otherImagesSm: otherImagesSm ?? this.otherImagesSm,
        otherImagesMd: otherImagesMd ?? this.otherImagesMd,
        totalStock: totalStock ?? this.totalStock,
        selectedVariant: selectedVariant ?? this.selectedVariant,
        availableVariants: availableVariants ?? this.availableVariants,
        productDetails: productDetails ?? this.productDetails,
        price: price ?? this.price,
        specialPrice: specialPrice ?? this.specialPrice,
        isAttachmentRequired: isAttachmentRequired ?? this.isAttachmentRequired,
        bestSeller: bestSeller ?? this.bestSeller,
        newArrival: newArrival ?? this.newArrival);
  }

  Product.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int?,
        attributes = (json['attributes'] as List?)
                ?.map((dynamic e) =>
                    Attribute.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
        variants = (json['variants'] as List?)
            ?.map((dynamic e) =>
                ProductVariant.fromJson(e as Map<String, dynamic>))
            .toList(),
        minMaxPrice = json['min_max_price'] != null
            ? ProductMinMaxPrice.fromJson(
                json['min_max_price'] as Map<String, dynamic>)
            : null,
        storeId = json['store_id'] as int?,
        categoryId = json['category_id'] as int?,
        sellerId = json['seller_id'] as int?,
        tax = json['tax'] as String?,
        rowOrder = json['row_order'] as int?,
        type = json['type'] as String?,
        stockType = json['stock_type'] ?? "",
        name = json['name'] as String?,
        shortDescription = json['short_description'] as String?,
        slug = json['slug'] as String?,
        productIds = json['product_ids'] as String?,
        productVariantIds = json['product_variant_ids']?.toString(),
        codAllowed = json['cod_allowed'] as int?,
        downloadAllowed = json['download_allowed'] as int?,
        downloadType = json['download_type'] as String?,
        downloadLink = json['download_link'] as String?,
        minimumOrderQuantity =
            int.tryParse(json['minimum_order_quantity'].toString()),
        quantityStepSize = int.tryParse(json['quantity_step_size'].toString()),
        totalAllowedQuantity =
            int.tryParse(json['total_allowed_quantity'].toString()),
        isPricesInclusiveTax = json['is_prices_inclusive_tax'] as int?,
        isReturnable = json['is_returnable'] as int?,
        isCancelable = json['is_cancelable'] as int?,
        cancelableTill = json['cancelable_till'] as String?,
        image = json['image'] as String?,
        otherImages = (json['other_images'] as List?)
            ?.map((dynamic e) => e as String)
            .toList(),
        videoType = json['video_type'] ?? "",
        video = json['video'] ?? "",
        tags =
            (json['tags'] as List?)?.map((dynamic e) => e as String).toList(),
        madeIn = json['made_in'] as String?,
        brand = json['brand'] as String?,
        sku = json['sku'] as String?,
        stock = (json['stock'] ?? "").toString(),
        availability = (json['availability'] ?? "").toString(),
        rating = json['rating'].toString() as String?,
        noOfRatings = json['no_of_ratings'] as int?,
        description = json['description'] as String?,
        extraDescription = json['extra_description'] as String?,
        deliverableType = json['deliverable_type'] as int?,
        deliverableZipcodes = json['deliverable_zipcodes'] as String?,
        cityDeliverableType = json['city_deliverable_type'] as int?,
        deliverableCities = json['deliverable_cities'] as String?,
        pickupLocation = json['pickup_location'] as String?,
        status = json['status'] as int?,
        minimumFreeDeliveryOrderQty =
            json['minimum_free_delivery_order_qty'] as int?,
        deliveryCharges = double.tryParse(
            (json['delivery_charges'] ?? 0).toString().isEmpty
                ? "0"
                : (json['delivery_charges'] ?? 0).toString()),
        createdAt = json['created_at'] as String?,
        updatedAt = json['updated_at'] as String?,
        brandName = json['brand_name'] ?? '',
        brandSlug = json['brand_slug'] ?? '',
        sellerRating = (json['seller_rating'] ?? 0).toString(),
        sellerSlug = json['seller_slug'] ?? '',
        sellerNoOfRatings = json['seller_no_of_ratings'].toString(),
        sellerProfile = json['seller_profile'] as String?,
        storeName = json['store_name'] ?? '',
        storeDescription = json['store_description'] as String?,
        sellerName = json['seller_name'] as String?,
        categoryName = json['category_name'] as String?,
        categorySlug = json['category_slug'] as String?,
        taxPercentage = json['tax_percentage'] as String?,
        taxId = json['tax_id'] as String?,
        attrValueIds = json['attr_value_ids'] as String?,
        productType = json['product_type'] as String?,
        relativePath = json['relative_path'] as String?,
        videoRelativePath = json['video_relative_path'] as String?,
        attributeValueIds = json['attr_value_ids'] as String?,
        deliverableZipcodesIds = json['deliverable_zipcodes_ids'] as String?,
        isDeliverable = json['is_deliverable'] as bool?,
        isFavorite = json['is_favorite'].toString(),
        imageMd = json['image_md'] as String?,
        imageSm = json['image_sm'] as String?,
        otherImagesSm = (json['other_images_sm'] as List?)
            ?.map((dynamic e) => e as String)
            .toList(),
        otherImagesMd = (json['other_images_md'] as List?)
            ?.map((dynamic e) => e as String)
            .toList(),
        totalStock = (json['total_stock'] ?? "").toString(),
        selectedVariant = json['type'] != comboProductType
            ? (json['variants'] as List?)
                ?.map((dynamic e) =>
                    ProductVariant.fromJson(e as Map<String, dynamic>))
                .toList()
                .firstWhereOrNull(
                (element) {
                  if (json['stock_type'] == '2' || json['stock_type'] == '1') {
                    return element.availability == "1" &&
                        element.stock != null &&
                        element.stock!.isNotEmpty &&
                        int.parse(element.stock!) > 0;
                  }
                  return true;
                },
              )
            : null,
        availableVariants = json['type'] == variableProductType
            ? (json['variants'] as List?)
                    ?.map((dynamic e) =>
                        ProductVariant.fromJson(e as Map<String, dynamic>))
                    .where(
                  (element) {
                    if (json['stock_type'] == '2') {
                      return element.availability == "1" &&
                          element.stock != null &&
                          element.stock!.isNotEmpty &&
                          int.parse(element.stock!) > 0;
                    }
                    return true;
                  },
                ).toList() ??
                []
            : [],
        productDetails = (json['product_details'] as List?)
            ?.map((dynamic e) => Product.fromJson(e as Map<String, dynamic>))
            .toList(),
        price = json['price'] as String?,
        specialPrice = json['special_price'] as String?,
        removeFavoriteInProgress = false,
        isAttachmentRequired = json['is_attachment_required'] as int?,
        bestSeller = json['best_seller'] ?? false,
        newArrival = json['new_arrival'] ?? false,
        customFields = (json['custom_fields'] as List?)
            ?.map((e) => Map<String, dynamic>.from(e as Map))
            .toList();

  static Product fromMostSellingProductJson(Map<String, dynamic> json) {
    return Product(
      id: json['product_id'],
      productName: json['product_name'],
      shortDescription: json['short_description'],
      image: json['image'],
      rating: json['rating'] != null ? json['rating'].toString() : "",
      noOfRatings: json['no_of_ratings'] as int?,
      specialPrice: json['special_price'] != null
          ? json['special_price'].toString()
          : "0.0",
      price: json['price'].toString(),
      type: json['type'],
      totalQuantitySold: json['total_quantity_sold'],
      totalSales: json['total_sales'].toString(),
      isFavorite: json['is_favorite'].toString(),
      isDeliverable: json['is_deliverable'],
      bestSeller: json['best_seller'] ?? false,
      newArrival: json['new_arrival'] ?? false,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'store_id': storeId,
        'category_id': categoryId,
        'seller_id': sellerId,
        'tax': tax,
        'row_order': rowOrder,
        'type': type,
        'stock_type': stockType,
        'name': name,
        'short_description': shortDescription,
        'slug': slug,
        'product_ids': productIds,
        'product_variant_ids': productVariantIds,
        'cod_allowed': codAllowed,
        'download_allowed': downloadAllowed,
        'download_type': downloadType,
        'download_link': downloadLink,
        'attributes': attributes?.map((value) => value.toJson()).toList(),
        'minimum_order_quantity': minimumOrderQuantity,
        'quantity_step_size': quantityStepSize,
        'total_allowed_quantity': totalAllowedQuantity,
        'is_prices_inclusive_tax': isPricesInclusiveTax,
        'is_returnable': isReturnable,
        'is_cancelable': isCancelable,
        'cancelable_till': cancelableTill,
        'image': image,
        'other_images': otherImages,
        'video_type': videoType,
        'video': video,
        'tags': tags,
        'made_in': madeIn,
        'brand': brand,
        'sku': sku,
        'stock': stock,
        'availability': availability,
        'rating': rating,
        'no_of_ratings': noOfRatings,
        'description': description,
        'extra_description': extraDescription,
        'deliverable_type': deliverableType,
        'deliverable_zipcodes': deliverableZipcodes,
        'city_deliverable_type': cityDeliverableType,
        'deliverable_cities': deliverableCities,
        'pickup_location': pickupLocation,
        'status': status,
        'minimum_free_delivery_order_qty': minimumFreeDeliveryOrderQty,
        'delivery_charges': deliveryCharges,
        'created_at': createdAt,
        'updated_at': updatedAt,
        'brand_name': brandName,
        'brand_slug': brandSlug,
        'seller_rating': sellerRating,
        'seller_slug': sellerSlug,
        'seller_no_of_ratings': sellerNoOfRatings,
        'seller_profile': sellerProfile,
        'store_name': storeName,
        'store_description': storeDescription,
        'seller_name': sellerName,
        'category_name': categoryName,
        'category_slug': categorySlug,
        'tax_percentage': taxPercentage,
        'tax_id': taxId,
        'attr_value_ids': attrValueIds,
        'product_type': productType,
        'relative_path': relativePath,
        'video_relative_path': videoRelativePath,
        'attribute_value_ids': attributeValueIds,
        'deliverable_zipcodes_ids': deliverableZipcodesIds,
        'is_deliverable': isDeliverable,
        'is_favorite': isFavorite,
        'image_md': imageMd,
        'image_sm': imageSm,
        'other_images_sm': otherImagesSm,
        'other_images_md': otherImagesMd,
        'total_stock': totalStock,
        'variants': variants?.map((value) => value.toJson()).toList(),
        'min_max_price': minMaxPrice?.toJson(),
        'product_details':
            productDetails?.map((value) => value.toJson()).toList(),
        'price': price,
        'special_price': specialPrice,
        'is_attachment_required': isAttachmentRequired,
        'best_seller': bestSeller,
        'new_arrival': newArrival,
        'custom_fields': customFields,
      };

  bool isProductOutOfStock() {
    if (stockType == "") {
      return false;
    }
    if (stockType != null && stockType!.isNotEmpty) {
      if (stockType == '0') {
        if (stock != null && stock!.isNotEmpty) {
          if (int.parse(stock!) < (minimumOrderQuantity ?? 0) ||
              availability == '0') {
            return true;
          }
        }
      } else if (stockType == '1' && variants != null && variants!.isNotEmpty) {
        if (variants![0].stock != null && variants![0].stock!.isNotEmpty) {
          if (int.parse(variants![0].stock!) <= 0 ||
              variants![0].availability == '0') {
            return true;
          }
        } else {
          return true;
        }
      } else if (stockType == '2') {
        final inStockVariantIndex = variants?.indexWhere((element) =>
            element.availability == "1" &&
            element.stock != null &&
            element.stock!.isNotEmpty &&
            int.parse(element.stock!) > 0);

        ///[If we find in stock variant then return the price of that variant]
        if (inStockVariantIndex != null && inStockVariantIndex != -1) {
          return false;
        } else {
          return true;
        }
      }
    }
    return false;
  }

  isVariantOutOfStock(ProductVariant variant) {
    if (stockType == "") {
      return false;
    }
    if (variant.stock != null && variant.stock!.isNotEmpty) {
      if (int.parse(variant.stock!) <= 0 || variant.availability == '0') {
        return true;
      }
    } else {
      return true;
    }
    return false;

    ///[If we find in stock variant then return the price of that variant]
  }

  bool isFavoriteProduct(BuildContext context, String productType) {
    if (context.read<UserDetailsCubit>().isGuestUser()) {
      List fav = FavoritesRepository().getOfflineFavoriteIds();
      if (type == comboProductType) {
        if (fav[1].contains(id)) {
          return true;
        }
      } else {
        if (fav[0].contains(id)) {
          return true;
        }
      }
    }

    return isFavorite == "1";
  }

  setFavoriteProduct(bool value) {
    isFavorite = value ? "1" : "0";
  }

  bool hasSpecialPrice() {
    return getPrice() != 0.0;
  }

  ///[Get the special price of the product, This will be shown to the customer]
  double getPrice() {
    if (productType == variableProductType) {
      ///[get the special price of the first variant]
      if (selectedVariant != null) {
        return double.parse((selectedVariant!.specialPrice ?? 0.0).toString());
      }

      return double.parse((variants?.first.specialPrice ?? 0.0).toString());
    }

    ///[Only for combo product]
    ///[get the price directly from params]
    else if (type == comboProductType) {
      return double.parse((specialPrice ?? 0.0).toString());
    }

    ///[Only for simple product and digital product]
    ///[get the price of the first variant]
    return double.parse((variants?.first.specialPrice ?? 0.0).toString());
  }

  ///[Get the price of the product]
  double getBasePrice() {
    if (productType == variableProductType) {
      if (selectedVariant != null) {
        return double.parse((selectedVariant!.price ?? 0.0).toString());
      }

      ///[get the special price of the first variant]
      return double.parse((variants?.first.price ?? 0.0).toString());
    } else if (type == comboProductType) {
      return double.parse((price ?? 0.0).toString());
    }

    ///[Only for simple product and digital product]
    ///[get the price of the first variant]

    return double.parse((variants?.first.price ?? 0.0).toString());
  }

  double getDiscoutPercentage() {
    if (hasSpecialPrice()) {
      return ((getBasePrice() - getPrice()) * 100) / getBasePrice();
    }
    return 0.0;
  }

  double getDiscoutPercentageForMostSellingProduct() {
    if (specialPrice != null) {
      return ((double.parse(price!) - double.parse(specialPrice!)) * 100) /
          double.parse(price!);
    }
    return 0.0;
  }

  bool hasAnyRating() {
    return (rating ?? "").isNotEmpty && (double.tryParse(rating!)) != 0;
  }
}
