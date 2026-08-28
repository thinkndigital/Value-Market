class Notifications {
  int? id;
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

  Notifications({
    this.id,
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
  });

  Notifications.fromJson(Map<String, dynamic> json) {
    id = json['id'];
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
  }
}
