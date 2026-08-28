class Attribute {
  final String? ids;
  final String? value;
  final String? attrName;
  final String? name;

  Attribute({
    this.ids,
    this.value,
    this.attrName,
    this.name,
  });

  Attribute copyWith({
    String? ids,
    String? value,
    String? attrName,
    String? name,
  }) {
    return Attribute(
      ids: ids ?? this.ids,
      value: value ?? this.value,
      attrName: attrName ?? this.attrName,
      name: name ?? this.name,
    );
  }

  Attribute.fromJson(Map<String, dynamic> json)
      : ids = json['ids'] as String?,
        value = json['value'] as String?,
        attrName = json['attr_name'] as String?,
        name = json['name'] as String?;

  Map<String, dynamic> toJson() =>
      {'ids': ids, 'value': value, 'attr_name': attrName, 'name': name};
}
