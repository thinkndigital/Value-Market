class Country {
  int? id;
  String? name;
  Country({
    required this.id,
    required this.name,
  });
  Country.fromJson(Map<String, dynamic> json) {
    id = json["id"];
    name = json["name"];
  }
}
