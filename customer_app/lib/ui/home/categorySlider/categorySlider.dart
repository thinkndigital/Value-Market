import 'package:eshop_plus/ui/categoty/models/category.dart';

class CategorySlider {
  int? id;
  String? title;
  String? categoryIds;
  int? storeId;
  String? style;
  int? status;
  String? createdAt;
  String? updatedAt;
  String? bannerImage;
  String? backgroundColor;
  List<Category>? categoryData;

  CategorySlider(
      {this.id,
      this.title,
      this.categoryIds,
      this.storeId,
      this.style,
      this.status,
      this.createdAt,
      this.updatedAt,
      this.bannerImage,
      this.backgroundColor,
      this.categoryData});

  CategorySlider.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    title = json['title'];
    categoryIds = json['category_ids'];
    storeId = json['store_id'];
    style = json['style'];
    status = json['status'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    bannerImage = json['banner_image'];
    backgroundColor = json['background_color'];
    if (json['category_data'] != null) {
      categoryData = <Category>[];
      json['category_data'].forEach((v) {
        categoryData!.add(Category.fromJson(v));
      });
    }
  }
}
