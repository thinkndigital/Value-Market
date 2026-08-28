class SelectedFilterAttribute {
  final String attributeName;
  final List<int> selectedIds;
  final bool isPredefined;

  final List<String>? attributeValues;
  final List<String>? attributeValuesId;
  final bool? isSingleSelection;

  SelectedFilterAttribute(
      {required this.attributeName,
      required this.selectedIds,
      required this.isPredefined,
      this.attributeValues,
      this.attributeValuesId,
      this.isSingleSelection = false});

  SelectedFilterAttribute copyWith({
    List<int>? selectedIds,
    bool? isPredefined,
  }) {
    return SelectedFilterAttribute(
      attributeValues: attributeValues,
      attributeValuesId: attributeValuesId,
      isSingleSelection: isSingleSelection,
      attributeName: attributeName,
      selectedIds: selectedIds ?? this.selectedIds,
      isPredefined: isPredefined ?? this.isPredefined,
    );
  }

  @override
  bool operator ==(covariant SelectedFilterAttribute other) {
    return other.attributeName == attributeName;
  }

  @override
  int get hashCode {
    return attributeName.hashCode;
  }

  bool isIdSelected(int id) {
    return selectedIds.contains(id);
  }
}
