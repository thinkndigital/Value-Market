import 'package:value_market_seller/commons/models/productCurrencyDetails.dart';

class ProductVariant {
  final int? id;
  final int? productId;
  final String? attributeValueIds;
  final String? attributeSet;
  final String? price;
  final String? specialPrice;
  String? priceWithTax;
  String? specialPriceWithTax;
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
    this.priceWithTax,
    this.specialPriceWithTax,
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

  ProductVariant copyWith({
    int? id,
    int? productId,
    String? attributeValueIds,
    String? attributeSet,
    String? price,
    String? specialPrice,
    String? priceWithTax,
    String? specialPriceWithTax,
    Map<String, ProductCurrencyDetails>? currencyPriceData,
    Map<String, ProductCurrencyDetails>? currencySpecialPriceData,
    String? sku,
    String? stock,
    double? weight,
    double? height,
    double? breadth,
    double? length,
    List<String>? images,
    String? availability,
    int? status,
    String? createdAt,
    String? updatedAt,
    String? productName,
    String? productImage,
    String? variantIds,
    String? attrName,
    String? variantValues,
    String? swatcheType,
    String? swatcheValue,
    String? stockType,
    List<String>? imagesMd,
    List<String>? imagesSm,
    List<String>? variantRelativePath,
    String? cartCount,
  }) {
    return ProductVariant(
      currencySpecialPriceData:
          currencySpecialPriceData ?? this.currencySpecialPriceData,
      currencyPriceData: currencyPriceData ?? this.currencyPriceData,
      id: id ?? this.id,
      productId: productId ?? this.productId,
      attributeValueIds: attributeValueIds ?? this.attributeValueIds,
      attributeSet: attributeSet ?? this.attributeSet,
      price: price ?? this.price,
      specialPrice: specialPrice ?? this.specialPrice,
      priceWithTax: priceWithTax ?? this.priceWithTax,
      specialPriceWithTax: specialPriceWithTax ?? this.specialPriceWithTax,
      sku: sku ?? this.sku,
      stock: stock ?? this.stock,
      weight: weight ?? this.weight,
      height: height ?? this.height,
      breadth: breadth ?? this.breadth,
      length: length ?? this.length,
      images: images ?? this.images,
      availability: availability ?? this.availability,
      status: status ?? this.status,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      productName: productName ?? this.productName,
      productImage: productImage ?? this.productImage,
      variantIds: variantIds ?? this.variantIds,
      attrName: attrName ?? this.attrName,
      variantValues: variantValues ?? this.variantValues,
      swatcheType: swatcheType ?? this.swatcheType,
      swatcheValue: swatcheValue ?? this.swatcheValue,
      stockType: stockType ?? this.stockType,
      imagesMd: imagesMd ?? this.imagesMd,
      imagesSm: imagesSm ?? this.imagesSm,
      variantRelativePath: variantRelativePath ?? this.variantRelativePath,
      cartCount: cartCount ?? this.cartCount,
    );
  }

  ProductVariant.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int?,
        productId = json['product_id'] as int?,
        attributeValueIds = json['attribute_value_ids'] as String?,
        attributeSet = json['attribute_set'] as String?,
        price = json['price'].toString(),
        specialPrice = json['special_price'].toString(),
        priceWithTax = json['price_with_tax'] as String?,
        specialPriceWithTax = json['special_price_with_tax'] as String?,
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
        images = json['images'] != null
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
        cartCount = json['cart_count'] as String?;

  Map<String, dynamic> toJson() => {
        'id': id,
        'product_id': productId,
        'attribute_value_ids': attributeValueIds,
        'attribute_set': attributeSet,
        'price': price,
        'special_price': specialPrice,
        'price_with_tax': priceWithTax,
        'special_price_with_tax': specialPriceWithTax,
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
  double getPrice() {
    double sPrice = double.parse((specialPrice ?? 0.0).toString());
    double basePrice = double.parse((price ?? 0.0).toString());
    if (sPrice == 0.0) {
      return basePrice;
    }
    return sPrice;
  }

  double getSellingPrice() {
    double sPrice = double.parse((specialPriceWithTax ?? 0.0).toString());
    double basePrice = double.parse((priceWithTax ?? 0.0).toString());
    if (sPrice == 0.0) {
      return basePrice;
    }
    return sPrice;
  }
}
