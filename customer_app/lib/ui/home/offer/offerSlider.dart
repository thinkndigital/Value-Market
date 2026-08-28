import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/ui/categoty/models/category.dart';

class OfferSlider {
  int? id;
  int? storeId;
  String? title;
  String? bannerImage;
  String? offerIds;
  int? status;
  String? createdAt;
  String? updatedAt;
  List<OfferImages>? offerImages;

  OfferSlider(
      {this.id,
      this.storeId,
      this.title,
      this.bannerImage,
      this.offerIds,
      this.status,
      this.createdAt,
      this.updatedAt,
      this.offerImages});

  OfferSlider.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    storeId = json['store_id'];
    title = json['title'];
    bannerImage = json['banner_image'];
    offerIds = json['offer_ids'];
    status = json['status'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    if (json['offer_images'] != null) {
      offerImages = <OfferImages>[];
      json['offer_images'].forEach((v) {
        offerImages!.add(OfferImages.fromJson(v));
      });
    }
  }
}

class OfferImages {
  int? id;
  int? storeId;
  String? title;
  String? type;
  int? typeId;
  String? link;
  String? image;
  String? bannerImage;
  String? minDiscount;
  String? maxDiscount;
  String? createdAt;
  String? updatedAt;
  int? categoryId;
  String? categoryName;
  List<Data>? data;
  Category? categoryData;

  OfferImages(
      {this.id,
      this.storeId,
      this.title,
      this.type,
      this.typeId,
      this.link,
      this.image,
      this.bannerImage,
      this.minDiscount,
      this.maxDiscount,
      this.createdAt,
      this.updatedAt,
      this.categoryId,
      this.categoryName,
      this.data,
      this.categoryData});

  OfferImages.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    storeId = json['store_id'];
    title = json['title'];
    type = json['type'];
    typeId = json['type_id'];
    link = json['link'];
    image = json['image'];
    bannerImage = json['banner_image'];
    minDiscount = json['min_discount'].toString();
    maxDiscount = json['max_discount'].toString();
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    categoryId = json['category_id'];
    categoryName = json['category_name'];
    if (json[ApiURL.dataKey] != null) {
      data = <Data>[];
      json[ApiURL.dataKey].forEach((v) {
        data!.add(new Data.fromJson(v));
      });
    }
    if (json['category_data'] != null) {
      categoryData = Category.fromJson(json['category_data']);
    }
  }
}

class Data {
  int? id;
  String? image;
  String? name;

  Data({this.id, this.image, this.name});

  Data.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    image = json['image'];
    name = json['name'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['id'] = id;
    data['image'] = image;
    return data;
  }
}
