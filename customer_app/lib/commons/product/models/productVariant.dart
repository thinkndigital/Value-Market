import 'package:eshop_plus/commons/product/models/productCurrencyDetails.dart';

class ProductVariant {
  final int? id;
  final int? productId;
  final String? attributeValueIds;
  final String? attributeSet;
  final String? price;
  final String? specialPrice;
  final String? sku;
  final String? stock;
  final double? weight;
  final double? height;
  final double? breadth;
  final double? length;
  final List<String>? images;
  final String? availability;
  final int? status;
  final String? createdAt;
  final String? updatedAt;
  final String? productName;
  final String? productImage;
  final String? variantIds;
  final String? attrName;
  final String? variantValues;
  final String? swatcheType;
  final String? swatcheValue;
  final String? stockType;
  final List<String>? imagesMd;
  final List<String>? imagesSm;
  final List<String>? variantRelativePath;
  final String? cartCount;
  final Map<String, ProductCurrencyDetails>? currencyPriceData;

  final Map<String, ProductCurrencyDetails>? currencySpecialPriceData;

  ProductVariant({
    this.id,
    this.productId,
    this.attributeValueIds,
    this.currencySpecialPriceData,
    this.currencyPriceData,
    this.attributeSet,
    this.price,
    this.specialPrice,
    this.sku,
    this.stock,
    this.weight,
    this.height,
    this.breadth,
    this.length,
    this.images,
    this.availability,
    this.status,
    this.createdAt,
    this.updatedAt,
    this.productName,
    this.productImage,
    this.variantIds,
    this.attrName,
    this.variantValues,
    this.swatcheType,
    this.swatcheValue,
    this.stockType,
    this.imagesMd,
    this.imagesSm,
    this.variantRelativePath,
    this.cartCount,
  });

  ProductVariant.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int?,
        productId = json['product_id'] as int?,
        attributeValueIds = json['attribute_value_ids'] as String?,
        attributeSet = json['attribute_set'] as String?,
        price = json['price'].toString(),
        specialPrice = json['special_price'].toString(),
        sku = json['sku'] as String?,
        stock = (json['stock'] ?? "").toString(),
        weight = double.tryParse((json['weight'] ?? 0).toString()),
        height = double.tryParse((json['height'] ?? 0).toString()),
        breadth = double.tryParse((json['breadth'] ?? 0).toString()),
        length = double.tryParse((json['length'] ?? 0).toString()),
        currencyPriceData = (json['currency_price_data']
                as Map<String, dynamic>?)
            ?.map((key, value) => MapEntry(
                key, ProductCurrencyDetails.fromJson(Map.from(value ?? {})))),
        currencySpecialPriceData = (json['currency_special_price_data']
                as Map<String, dynamic>?)
            ?.map((key, value) => MapEntry(
                key, ProductCurrencyDetails.fromJson(Map.from(value ?? {})))),
        images = json['images'] != ""
            ? (json['images'] as List?)
                ?.map((dynamic e) => e as String)
                .toList()
            : [],
        availability = (json['availability'] ?? "").toString(),
        status = json['status'] as int?,
        createdAt = json['created_at'] as String?,
        updatedAt = json['updated_at'] as String?,
        productName = json['product_name'] as String?,
        productImage = json['product_image'] as String?,
        variantIds = json['variant_ids'] as String?,
        attrName = json['attr_name'] as String?,
        variantValues = json['variant_values'] as String?,
        swatcheType = json['swatche_type'] as String?,
        swatcheValue = json['swatche_value'] as String?,
        stockType = json['stock_type'] as String?,
        imagesMd = (json['images_md'] as List?)
            ?.map((dynamic e) => e as String)
            .toList(),
        imagesSm = (json['images_sm'] as List?)
            ?.map((dynamic e) => e as String)
            .toList(),
        variantRelativePath = (json['variant_relative_path'] as List?)
            ?.map((dynamic e) => e as String)
            .toList(),
        cartCount = json['cart_count'].toString();

  Map<String, dynamic> toJson() => {
        'id': id,
        'product_id': productId,
        'attribute_value_ids': attributeValueIds,
        'attribute_set': attributeSet,
        'price': price,
        'special_price': specialPrice,
        'sku': sku,
        'stock': stock,
        'weight': weight,
        'height': height,
        'breadth': breadth,
        'length': length,
        'images': images,
        'availability': availability,
        'status': status,
        'created_at': createdAt,
        'updated_at': updatedAt,
        'product_name': productName,
        'product_image': productImage,
        'variant_ids': variantIds,
        'attr_name': attrName,
        'variant_values': variantValues,
        'swatche_type': swatcheType,
        'swatche_value': swatcheValue,
        'stock_type': stockType,
        'images_md': imagesMd,
        'images_sm': imagesSm,
        'variant_relative_path': variantRelativePath,
        'cart_count': cartCount,
        'currency_price_data': currencyPriceData
            ?.map((key, value) => MapEntry(key, value.toJson())),
        'currency_special_price_data': currencySpecialPriceData
            ?.map((key, value) => MapEntry(key, value.toJson())),
      };
  double getBasePrice() {
    return double.parse((price ?? 0.0).toString());
  }

  double getPrice() {
    return double.parse((specialPrice ?? 0.0).toString());
  }
}
