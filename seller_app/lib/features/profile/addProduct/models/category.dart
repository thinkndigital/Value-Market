class Category {
  late int id;
  late int storeId;
  late String name;
  int? parentId;
  String? slug;
  late String image;
  late String banner;
  String? style;
  int? rowOrder;
  int? status;
  int? clicks;
  String? createdAt;
  String? updatedAt;
  List<Category>? children;
  String? text;
  CatState? state;
  String? icon;
  int? level;
  int? total;

  Category(
      {required id,
      required storeId,
      required name,
      parentId,
      slug,
      required image,
      required banner,
      style,
      rowOrder,
      status,
      clicks,
      createdAt,
      updatedAt,
      children,
      text,
      state,
      icon,
      level,
      total});

  Category.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    storeId = json['store_id'];
    name = json['name'];
    parentId = json['parent_id'];
    slug = json['slug'];
    image = json['image'];
    banner = json['banner'];
    style = json['style'];
    rowOrder = json['row_order'];
    status = json['status'];
    clicks = json['clicks'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    children = <Category>[];
    if (json['children'] != null) {
      json['children'].forEach((v) {
        children!.add(Category.fromJson(v));
      });
    }
    text = json['text'];
    state = json['state'] != null ? CatState.fromJson(json['state']) : null;
    icon = json['icon'];
    level = json['level'];
    total = json['total'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = {};
    data['id'] = id;
    data['store_id'] = storeId;
    data['name'] = name;
    data['parent_id'] = parentId;
    data['slug'] = slug;
    data['image'] = image;
    data['banner'] = banner;
    data['style'] = style;
    data['row_order'] = rowOrder;
    data['status'] = status;
    data['clicks'] = clicks;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    if (children != null) {
      data['children'] = children!.map((v) => v.toJson()).toList();
    }
    data['text'] = text;
    if (state != null) {
      data['state'] = state!.toJson();
    }
    data['icon'] = icon;
    data['level'] = level;
    data['total'] = total;
    return data;
  }
}

class CatState {
  bool? opened;

  CatState({opened});

  CatState.fromJson(Map<String, dynamic> json) {
    opened = json['opened'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = {};
    data['opened'] = opened;
    return data;
  }
}
