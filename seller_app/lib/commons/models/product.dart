import 'package:value_market_seller/commons/models/productVariant.dart';
import 'package:value_market_seller/commons/models/productMinMaxPrice.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';

import 'attribute.dart';

class Product {
  int? id;
  int? storeId;
  int? categoryId;
  int? sellerId;
  String? tax;
  int? rowOrder;

  ///[simple_product, variable_product, digital_product,combo-product]
  String? type;

  ///[0,1,2]
  ///[0=For simple product]
  ///[1 = For variable product check for product]
  ///[2 = Also for variable product but at a variant vise]
  String? stockType;

  String? name;
  String? shortDescription;
  String? slug;
  String? productIds;
  String? productVariantIds;

  ///[0,1,2] [0 - none, 1 - veg, 2 - non veg]
  String? indicator;
  int? codAllowed;
  int? downloadAllowed;
  String? downloadType;
  String? downloadLink;
  int? minimumOrderQuantity;
  int? quantityStepSize;
  int? totalAllowedQuantity;
  int? isPricesInclusiveTax;
  int? isReturnable;
  int? isCancelable;
  String? cancelableTill;
  String? image;
  List<String>? otherImages;
  String? videoType;
  String? video;
  List<String>? tags;
  String? warrantyPeriod;
  String? guaranteePeriod;
  String? madeIn;
  String? hsnCode;
  String? brand;
  String? sku;
  String? stock;

  ///[""= Not set, 0 = Out of stock, 1 = In stock]
  String? availability;
  String? rating;
  int? noOfRatings;
  String? description;
  String? extraDescription;
  int? deliverableType;
  String? deliverableZipcodes;
  int? cityDeliverableType;
  String? deliverableCities;
  String? pickupLocation;
  int? status;
  int? minimumFreeDeliveryOrderQty;
  double? deliveryCharges;
  String? createdAt;
  String? updatedAt;
  String? brandName;
  String? brandSlug;
  String? sellerRating;
  String? sellerSlug;
  int? sellerNoOfRatings;
  String? sellerProfile;
  String? storeName;
  String? storeDescription;
  String? sellerName;
  String? categoryName;
  String? categorySlug;
  String? taxPercentage;
  String? taxId;
  String? attrValueIds;
  String? productType;
  String? relativePath;
  String? videoRelativePath;
  String? attributeValueIds;
  String? deliverableZipcodesIds;
  bool? isDeliverable;
  String? isFavorite;
  String? imageMd;
  String? imageSm;
  List<String>? otherImagesSm;
  List<String>? otherImagesMd;
  String? totalStock;
  List<Attribute>? attributes;
  List<ProductVariant>? variants;
  ProductMinMaxPrice? minMaxPrice;
  ProductVariant? selectedVariant;
  List<ProductVariant>? availableVariants;
  Map? availableVariantsAttributeIds;
  //below 3 params are used for the combo product only
  List<Product>? similarProductDetails;
  List<Product>? productDetails;
  String? price;
  String? specialPrice;
  String? priceWithTax;
  String? specialPriceWithTax;
  String? weight, height, breadth, length;
  String? similarProductIds;
  String? hasSimilarProduct;
  String? productName;
  String? totalQuantitySold;
  int? totalSales;
  bool? removeFavoriteInProgress;
  String? taxNames;
  String? deliverableCitiesIds;
  String? deliverableZipcodeIds;
  String? deliverableZoneIds;
  String? deliverableZones;
  int? isAttachmentRequired;
  Map<String, dynamic>? translatedName;
  Map<String, dynamic>? translatedShortDescription;
  List<Map<String, dynamic>>? customFields;

  Product({
    this.id,
    this.deliverableZones,
    this.deliverableZoneIds,
    this.deliverableCitiesIds,
    this.deliverableZipcodeIds,
    this.taxNames,
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
    this.similarProductIds,
    this.hasSimilarProduct,
    this.productVariantIds,
    this.indicator,
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
    this.warrantyPeriod,
    this.guaranteePeriod,
    this.madeIn,
    this.hsnCode,
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
    this.similarProductDetails,
    this.price,
    this.specialPrice,
    this.priceWithTax,
    this.specialPriceWithTax,
    this.productName,
    this.totalQuantitySold,
    this.totalSales,
    this.removeFavoriteInProgress,
    this.weight,
    this.height,
    this.breadth,
    this.length,
    this.isAttachmentRequired,
    this.translatedName,
    this.translatedShortDescription,
    this.customFields,
  });

  Product.fromSimillarProductJson(Map<String, dynamic> json) {
    id = (json['id'] ?? 0) as int;
    name = json['title'] ?? "";
    image = json['image'] ?? '';
  }
  Product.fromJson(Map<String, dynamic> json, {bool isComboProduct = false}) {
    id = (json['id'] ?? 0) as int;

    attributes = (json['attributes'] as List?)
        ?.map((dynamic e) => Attribute.mainFromJson(e as Map<String, dynamic>))
        .toList();

    variants = (json['variants'] as List?)
        ?.map((dynamic e) => ProductVariant.fromJson(e as Map<String, dynamic>))
        .toList();

    minMaxPrice = json['min_max_price'] != null
        ? ProductMinMaxPrice.fromJson(
            json['min_max_price'] as Map<String, dynamic>)
        : null;

    storeId = json['store_id'] as int?;

    categoryId = json['category_id'] as int?;

    sellerId = json['seller_id'] as int?;

    tax = json['tax'] as String?;

    rowOrder = json['row_order'] as int?;

    type = json['type'] as String?;

    stockType = json['stock_type'] as String?;

    name = json['name'] as String?;

    shortDescription = json['short_description'] as String?;

    slug = json['slug'] as String?;

    productIds = json['product_ids'] as String?;

    productVariantIds = json['product_variant_ids'] as String?;

    indicator = (json['indicator'] ?? "").toString();

    codAllowed = json['cod_allowed'] as int?;

    downloadAllowed = json['download_allowed'] as int?;

    downloadType = json['download_type'] as String?;

    downloadLink = json['download_link'] as String?;

    minimumOrderQuantity =
        int.tryParse(json['minimum_order_quantity'].toString());

    quantityStepSize = int.tryParse(json['quantity_step_size'].toString());

    totalAllowedQuantity =
        int.tryParse(json['total_allowed_quantity'].toString());

    isPricesInclusiveTax = json['is_prices_inclusive_tax'] as int?;

    isReturnable = json['is_returnable'] as int?;

    isCancelable = json['is_cancelable'] as int?;

    cancelableTill = json['cancelable_till'] as String?;

    image = json['image'] as String?;

    otherImages = (json['other_images'] as List?)
        ?.map((dynamic e) => e as String)
        .toList();

    videoType = json['video_type'] ?? "";

    video = json['video'] ?? "";

    tags = (json['tags'] as List?)?.map((dynamic e) => e as String).toList();

    warrantyPeriod = json['warranty_period'] as String?;

    guaranteePeriod = json['guarantee_period'] as String?;

    madeIn = json['made_in'] ?? '';

    hsnCode = json['hsn_code'] as String?;

    brand = json['brand'] as String?;

    sku = json['sku'] as String?;

    stock = (json['stock'] ?? "").toString();

    availability = (json['availability'] ?? "").toString();

    rating = json['rating'].toString() as String?;

    noOfRatings = json['no_of_ratings'] as int?;

    description = json['description'] as String?;

    extraDescription = json['extra_description'] as String?;

    deliverableType = json['deliverable_type'] as int?;

    deliverableZipcodes = json['deliverable_zipcodes'] as String?;

    cityDeliverableType = json['city_deliverable_type'] as int?;

    deliverableCities = json['deliverable_cities'] as String?;

    pickupLocation = json['pickup_location'] as String?;

    status = json['status'] as int?;

    minimumFreeDeliveryOrderQty =
        json['minimum_free_delivery_order_qty'] as int?;

    deliveryCharges = double.tryParse(
        (json['delivery_charges'] ?? 0).toString().isEmpty
            ? "0"
            : (json['delivery_charges'] ?? 0).toString());

    createdAt = json['created_at'] as String?;

    updatedAt = json['updated_at'] as String?;

    brandName = json['brand_name'] as String?;

    brandSlug = json['brand_slug'] as String?;

    sellerRating = (json['seller_rating'] ?? 0).toString();

    sellerSlug = json['seller_slug'] as String?;

    sellerNoOfRatings =
        int.tryParse(json['seller_no_of_ratings'].toString()) ?? null;

    sellerProfile = json['seller_profile'] as String?;

    storeName = json['store_name'] as String?;

    storeDescription = json['store_description'] as String?;

    sellerName = json['seller_name'] as String?;

    categoryName = json['category_name'] as String?;

    categorySlug = json['category_slug'] as String?;

    taxPercentage = json['tax_percentage'] as String?;

    taxId = json['tax_id'] as String?;

    attrValueIds = json['attr_value_ids'] as String?;

    productType = json['product_type'] as String?;

    relativePath = json['relative_path'] as String?;

    videoRelativePath = json['video_relative_path'] as String?;

    attributeValueIds = json['attr_value_ids'] as String?;

    deliverableZipcodesIds = json['deliverable_zipcodes_ids'] as String?;

    isDeliverable = json['is_deliverable'] as bool?;

    isFavorite = json['is_favorite'].toString();

    imageMd = json['image_md'] as String?;

    imageSm = json['image_sm'] as String?;

    otherImagesSm = (json['other_images_sm'] as List?)
        ?.map((dynamic e) => e as String)
        .toList();

    otherImagesMd = (json['other_images_md'] as List?)
        ?.map((dynamic e) => e as String)
        .toList();

    totalStock = (json['total_stock'] ?? "").toString();

    selectedVariant = json.containsKey("variants") &&
            json['type'] != comboProductType &&
            (json['variants'] as List).isNotEmpty
        ? (json['variants'] as List?)
            ?.map((dynamic e) =>
                ProductVariant.fromJson(e as Map<String, dynamic>))
            .toList()
            .first
        : null;

    availableVariants = json.containsKey("variants") &&
            json['type'] == variableProductType &&
            (json['variants'] as List).isNotEmpty
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
        : [];

    productDetails = (json['product_details'] as List?)
        ?.map((dynamic e) => Product.fromJson(e as Map<String, dynamic>,
            isComboProduct: isComboProduct))
        .toList();

    price = json['price'] as String?;

    specialPrice = json['special_price'] as String?;

    priceWithTax = json['price_with_tax'] as String?;

    specialPriceWithTax = json['special_price_with_tax'] as String?;

    taxNames = json['tax_names'] ?? "";

    deliverableCitiesIds = json['deliverable_cities_ids'] ?? "";

    deliverableZipcodeIds = json['deliverable_zipcodes_ids'] ?? "";

    deliverableZones = json['deliverable_zones'] ?? "";

    deliverableZoneIds = json['deliverable_zones_ids'] ?? "";

    removeFavoriteInProgress = false;

    isAttachmentRequired = json['is_attachment_required'] as int?;

    translatedName = json['translated_name'] as Map<String, dynamic>?;
    translatedShortDescription =
        json['translated_short_description'] as Map<String, dynamic>?;

    customFields = (json['custom_fields'] as List?)
        ?.map((e) => Map<String, dynamic>.from(e as Map))
        .toList();

    if (isComboProduct) {
      hasSimilarProduct = json['has_similar_product'] ?? "0";
      similarProductIds = json['similar_product_ids'] ?? "";
      productIds = json['product_ids'] ?? "";
      similarProductDetails = (json['similar_product_details'] as List?)
          ?.map((dynamic e) =>
              Product.fromSimillarProductJson(e as Map<String, dynamic>))
          .toList();
      weight = json['weight'] ?? "";
      height = json['height'] ?? "";
      length = json['length'] ?? "";
      breadth = json['breadth'] ?? "";
    }
  }

  bool isProductOutOfStock() {
    if (stockType == "") {
      return false;
    }
    if (stockType != null && stockType!.isNotEmpty) {
      if (stockType == '0') {
        if (stock != null && stock!.isNotEmpty) {
          if (int.parse(stock!) <= (minimumOrderQuantity ?? 0) ||
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

  bool isFavoriteProduct() {
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

      if (variants?.isNotEmpty ?? false) {
        return double.parse((variants?.first.specialPrice ?? 0.0).toString());
      }
      return 0.0;
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

      if (variants?.isNotEmpty ?? false) {
        ///[get the special price of the first variant]
        return double.parse((variants?.first.price ?? 0.0).toString());
      } else {
        return double.parse((price ?? 0.0).toString());
      }
    } else if (type == comboProductType) {
      return double.parse((price ?? 0.0).toString());
    }

    ///[Only for simple product and digital product]
    ///[get the price of the first variant]

    return double.parse((variants?.first.price ?? 0.0).toString());
  }

  double getSellingPrice() {
    double sPrice = 0.0;
    double basePrice = 0.0;
    if (type == comboProductType) {
      sPrice = double.parse((specialPriceWithTax ?? 0.0).toString());
      basePrice = double.parse((priceWithTax ?? 0.0).toString());
      if (sPrice != 0.0) {
        return sPrice;
      }
      return basePrice;
    } else {
      if (variants?.isNotEmpty ?? false) {
        sPrice = double.parse(
            (variants!.first.specialPriceWithTax ?? 0.0).toString());
        basePrice =
            double.parse((variants!.first.priceWithTax ?? 0.0).toString());
        if (sPrice != 0.0) {
          return sPrice;
        }
        return basePrice;
      }
      return 0.0;
    }
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

  void changeStatus(int new_status) {
    status = new_status;
  }

  bool hasAnyRating() {
    return (rating ?? "").isNotEmpty && (double.tryParse(rating!)) != 0;
  }
}
