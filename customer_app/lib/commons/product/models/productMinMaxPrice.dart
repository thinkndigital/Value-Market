class ProductMinMaxPrice {
  final double? minPrice;
  final double? maxPrice;
  final double? specialMinPrice;
  final double? specialMaxPrice;
  final double? discountInPercentage;

  ProductMinMaxPrice({
    this.minPrice,
    this.maxPrice,
    this.specialMinPrice,
    this.specialMaxPrice,
    this.discountInPercentage,
  });

  ProductMinMaxPrice copyWith({
    double? minPrice,
    double? maxPrice,
    double? specialMinPrice,
    double? specialMaxPrice,
    double? discountInPercentage,
  }) {
    return ProductMinMaxPrice(
      minPrice: minPrice ?? this.minPrice,
      maxPrice: maxPrice ?? this.maxPrice,
      specialMinPrice: specialMinPrice ?? this.specialMinPrice,
      specialMaxPrice: specialMaxPrice ?? this.specialMaxPrice,
      discountInPercentage: discountInPercentage ?? this.discountInPercentage,
    );
  }

  ProductMinMaxPrice.fromJson(Map<String, dynamic> json)
      : minPrice = double.tryParse((json['min_price'] ?? 0).toString()),
        maxPrice = double.tryParse((json['max_price'] ?? 0).toString()),
        specialMinPrice =
            double.tryParse((json['special_min_price'] ?? 0).toString()),
        specialMaxPrice =
            double.tryParse((json['special_max_price'] ?? 0).toString()),
        discountInPercentage =
            double.tryParse((json['discount_in_percentage'] ?? 0).toString());

  Map<String, dynamic> toJson() => {
        'min_price': minPrice,
        'max_price': maxPrice,
        'special_min_price': specialMinPrice,
        'special_max_price': specialMaxPrice,
        'discount_in_percentage': discountInPercentage
      };
}
