class FilterAttribute {
  final String? attributeName;
  final List<String>? attributeValues;
  final List<String>? attributeValuesId;

  FilterAttribute({
    this.attributeName,
    this.attributeValues,
    this.attributeValuesId,
  });

  FilterAttribute copyWith({
    String? attributeName,
    List<String>? attributeValues,
    List<String>? attributeValuesId,
  }) {
    return FilterAttribute(
      attributeName: attributeName ?? this.attributeName,
      attributeValues: attributeValues ?? this.attributeValues,
      attributeValuesId: attributeValuesId ?? this.attributeValuesId,
    );
  }

  FilterAttribute.fromJson(Map<String, dynamic> json)
      : attributeName = json['attribute_name'] as String?,
        attributeValues = (json['attribute_values'] as List?)
            ?.map((dynamic e) => e as String)
            .toList(),
        attributeValuesId = (json['attribute_values_id'] as List?)
            ?.map((dynamic e) => e as String)
            .toList();

  Map<String, dynamic> toJson() => {
        'attribute_name': attributeName,
        'attribute_values': attributeValues,
        'attribute_values_id': attributeValuesId
      };
}
