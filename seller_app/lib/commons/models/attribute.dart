class Attribute {
  String? ids;
  String? value;
  String? attrName;
  String? name;
  Map<String, String>? attributeValueMap;
  String? attrId;
  Attribute({
    this.ids,
    this.value,
    this.attrName,
    this.name,
    this.attrId,
    this.attributeValueMap,
  });

  Attribute copyWith({
    String? ids,
    String? value,
    String? attrName,
    String? name,
    String? attrId,
    Map<String, String>? attributeValueMap,
  }) {
    return Attribute(
      ids: ids ?? this.ids,
      value: value ?? this.value,
      attrName: attrName ?? this.attrName,
      name: name ?? this.name,
      attributeValueMap: attributeValueMap ?? this.attributeValueMap,
      attrId: attrId ?? this.attrId,
    );
  }

  Attribute.mainFromJson(Map<String, dynamic> json) {
    ids = json['ids'];
    attrId = (json['attr_id'] ?? "").toString();
    value = json['value'];
    attrName = json['attr_name'];
    name = json['name'];
    attributeValueMap = {};
    if (json.containsKey("attributeValueMap")) {
      attributeValueMap = json["attributeValueMap"];
    } else if (ids!.trim().isNotEmpty) {
      List<String> mids = ids!.split(",");
      List<String> mvalues = value!.split(",");
      for (int i = 0; i < mids.length; i++) {
        attributeValueMap![mids[i]] = mvalues[i];
      }
    }
  }
  Attribute.fromJson(Map<String, dynamic> json)
      : ids = json['ids'] as String?,
        value = json['value'] as String?,
        attrName = json['attr_name'] as String?,
        name = json['name'] as String?;

  Map<String, dynamic> toJson() => {
        'ids': ids,
        'value': value,
        'attr_name': attrName,
        'name': name,
        'attributeValueMap': attributeValueMap,
        'attr_id': attrId,
      };
}
