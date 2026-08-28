class Brand {
  int? id;
  String? name;
  String? image;
  int? status;
  Brand({
    required this.id,
    required this.name,
    required this.image,
    required this.status,
  });
  Brand.fromJson(Map<String, dynamic> json) {
    id = json["id"];
    name = json["name"];
    image = json["image"];
    status = json["status"];
  }
}
