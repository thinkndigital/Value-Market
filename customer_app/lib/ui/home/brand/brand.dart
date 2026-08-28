class Brand {
  final int? id;
  final int? storeId;
  final String? name;
  final String? slug;
  final String? image;
  final int? status;
  final String? createdAt;
  final String? updatedAt;

  Brand({
    this.id,
    this.storeId,
    this.name,
    this.slug,
    this.image,
    this.status,
    this.createdAt,
    this.updatedAt,
  });

  Brand copyWith({
    int? id,
    int? storeId,
    String? name,
    String? slug,
    String? image,
    int? status,
    String? createdAt,
    String? updatedAt,
  }) {
    return Brand(
      id: id ?? this.id,
      storeId: storeId ?? this.storeId,
      name: name ?? this.name,
      slug: slug ?? this.slug,
      image: image ?? this.image,
      status: status ?? this.status,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  Brand.fromJson(Map<String, dynamic> json)
      : id = json['id'] as int?,
        storeId = json['store_id'] as int?,
        name = json['name'] as String?,
        slug = json['slug'] as String?,
        image = json['image'] as String?,
        status = json['status'] as int?,
        createdAt = json['created_at'] as String?,
        updatedAt = json['updated_at'] as String?;

  Map<String, dynamic> toJson() => {
        'id': id,
        'store_id': storeId,
        'name': name,
        'slug': slug,
        'image': image,
        'status': status,
        'created_at': createdAt,
        'updated_at': updatedAt
      };
}
