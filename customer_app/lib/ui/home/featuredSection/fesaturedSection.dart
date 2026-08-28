import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/core/api/apiEndPoints.dart';

class FeaturedSection {
  int? id;
  int? storeId;
  String? title;
  String? shortDescription;
  String? style;
  String? headerStyle;
  String? productIds;
  int? rowOrder;
  String? categories;
  String? productType;
  String? bannerImage;
  String? backgroundColor;
  String? updatedAt;
  String? createdAt;
  String? total;
  List<dynamic>? filters;
  List<Product>? productDetails;

  FeaturedSection(
      {this.id,
      this.storeId,
      this.title,
      this.shortDescription,
      this.style,
      this.headerStyle,
      this.productIds,
      this.rowOrder,
      this.categories,
      this.productType,
      this.bannerImage,
      this.backgroundColor,
      this.updatedAt,
      this.createdAt,
      this.total,
      this.filters,
      this.productDetails});

  FeaturedSection.fromJson(
      Map<String, dynamic> json, bool checkDeliverability) {
    id = json['id'];
    storeId = json['store_id'];
    title = json['title'];
    shortDescription = json['short_description'];
    style = json['style'];
    headerStyle = json['header_style'];
    productIds = json['product_ids'];
    rowOrder = json['row_order'];
    categories = json['categories'];
    productType = json['product_type'];
    bannerImage = json['banner_image'];
    backgroundColor = json['background_color'] ?? "#FFFFFF";
    updatedAt = json['updated_at'];
    createdAt = json['created_at'];
    total = json[ApiURL.totalKey];
    filters = json['filters'];
    if (json['product_details'] != null) {
      productDetails = <Product>[];
      json['product_details'].forEach((v) {
        var product = Product.fromJson(v);
        if (product.type == comboProductType ||
            (product.type != comboProductType &&
                product.variants != null &&
                product.variants!.isNotEmpty)) {
          if (checkDeliverability) {
            if (product.isDeliverable == true) {
              productDetails!.add(product);
            }
          } else {
            productDetails!.add(product);
          }
        }
      });
    }
  }
}
