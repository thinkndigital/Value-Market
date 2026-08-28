import 'package:eshop_plus/ui/categoty/models/category.dart';

class Notifications {
  int? id;
  int? storeId;
  String? title;
  String? message;
  String? type;
  String? typeId;
  String? sendTo;
  String? usersId;
  String? image;
  String? link;
  String? createdAt;
  String? updatedAt;
  Category? categoryData;

  Notifications(
      {this.id,
      this.storeId,
      this.title,
      this.message,
      this.type,
      this.typeId,
      this.sendTo,
      this.usersId,
      this.image,
      this.link,
      this.createdAt,
      this.updatedAt,
      this.categoryData});

  Notifications.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    storeId = json['store_id'];
    title = json['title'];
    message = json['message'];
    type = json['type'];
    typeId = json['type_id'];
    sendTo = json['send_to'];
    usersId = json['users_id'];
    image = json['image'];
    link = json['link'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    if (json['category_data'] != null) {
      categoryData = Category.fromJson(json['category_data']);
    }
  }
}
