class SearchedProduct {
  int? productId;
  String? productName;
  String? productImage;
  int? categoryId;
  String? categoryName;
  String? type;

  SearchedProduct(
      {this.productId,
      this.productName,
      this.productImage,
      this.categoryId,
      this.categoryName,
      this.type});

  SearchedProduct.fromJson(Map<String, dynamic> json) {
    productId = json['product_id'];
    productName = json['product_name'];
    productImage = json['product_image'];
    categoryId = json['category_id'] ?? 0;
    categoryName = json['category_name'] ?? '';
    type = json['type'] ?? '';
  }
}
