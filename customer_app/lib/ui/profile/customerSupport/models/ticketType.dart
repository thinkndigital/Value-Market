class TicketType {
  int? id;
  String? title;
  String? createdAt;
  String? updatedAt;

  TicketType({this.id, this.title, this.createdAt, this.updatedAt});

  TicketType.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    title = json['title'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }
}
