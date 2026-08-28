class Offer {
  int? id;
  int? storeId;
  String? title;
  String? type;
  int? typeId;
  String? link;
  String? image;
  String? bannerImage;
  int? minDiscount;
  int? maxDiscount;
  String? createdAt;
  String? updatedAt;

  Offer({
    id,
    storeId,
    title,
    type,
    typeId,
    link,
    image,
    bannerImage,
    minDiscount,
    maxDiscount,
    createdAt,
    updatedAt,
  });

  Offer.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    storeId = json['store_id'];
    title = json['title'];
    type = json['type'];
    typeId = json['type_id'];
    link = json['link'];
    image = json['image'];
    bannerImage = json['banner_image'];
    minDiscount = json['min_discount'];
    maxDiscount = json['max_discount'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data =  Map<String, dynamic>();
    data['id'] = id;
    data['store_id'] = storeId;
    data['title'] = title;
    data['type'] = type;
    data['type_id'] = typeId;
    data['link'] = link;
    data['image'] = image;
    data['banner_image'] = bannerImage;
    data['min_discount'] = minDiscount;
    data['max_discount'] = maxDiscount;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;

    return data;
  }
}
