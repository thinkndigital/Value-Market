class Tax {
  int? id;
  String? title;
  String? percentage;
  int? status;
  Tax({
    required this.id,
    required this.title,
    required this.percentage,
    required this.status,
  });
  Tax.fromJson(Map<String, dynamic> json) {
    id = json["id"];
    title = json["title"];
    percentage = json["percentage"];
    status = json["status"];
  }
}
