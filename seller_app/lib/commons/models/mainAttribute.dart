
class MainAttribute {
  int? id, statusCode;
  String? name;
  String? value;
  String? status;
  String? attributeValueId;
  Map<String, String>? attributeValueMap;
  MainAttribute(
      {this.id,
      this.statusCode,
      this.attributeValueId,
      this.name,
      this.value,
      this.status,
      this.attributeValueMap});
  MainAttribute.fromJson(Map<String, dynamic> json) {
    id = json["id"] ?? 0;
    if (json.containsKey("attr_id")) {
      id = int.parse(json["attr_id"].toString());
    }
    name = json["name"];
    status = json["status"] ?? "";
    statusCode = json["status_code"] ?? 0;
    attributeValueId = json["attribute_value_id"] ?? "";
    value = json["value"] ?? "";
    attributeValueMap = {};
    if (json.containsKey("attributeValueMap")) {
      attributeValueMap = json["attributeValueMap"];
    } else if (attributeValueId!.trim().isNotEmpty) {
      List<String> ids = attributeValueId!.split(",");
      List<String> values = value!.split(",");
      for (int i = 0; i < ids.length; i++) {
        attributeValueMap![ids[i]] = values[i];
      }
    }
  }

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'attr_id': id,
      'status_code': statusCode,
      'name': name,
      'value': value,
      'status': status,
      'attribute_value_id': attributeValueId,
      'attributeValueMap': attributeValueMap,
    };
  }
}
