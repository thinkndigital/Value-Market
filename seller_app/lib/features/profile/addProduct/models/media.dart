class Media {
  int? id;
  String? name;
  String? image;
  String? size;
  String? extension;
  String? type;
  String? subDirectory;
  String? relativePath;
  Media(
      {this.id,
      this.extension,
      this.name,
      this.image,
      this.size,
      this.type,
      this.subDirectory,
      this.relativePath});
  Media.fromJson(Map<String, dynamic> json) {
    id = json["id"] ?? 0;
    name = json["name"] ?? "";
    size = json["size"] ?? "";
    type = json["type"] ?? "";
    extension = json["extension"] ?? "";
    image = json["image"] ?? "";
    subDirectory = json["sub_directory"] ?? "";
    if (json.containsKey("relative_path")) {
      relativePath = json["relative_path"];
    } else {
      relativePath = "${subDirectory!}/${name!}";
    }
  }
}
