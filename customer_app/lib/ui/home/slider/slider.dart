import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/ui/categoty/models/category.dart';
import 'package:eshop_plus/commons/product/models/product.dart';

class Sliders {
  int? id;
  int? storeId;
  String? type;
  int? typeId;
  String? link;
  String? image;
  String? createdAt;
  String? updatedAt;
  List<dynamic>? itemList;

  Sliders(
      {this.id,
      this.storeId,
      this.type,
      this.typeId,
      this.link,
      this.image,
      this.createdAt,
      this.updatedAt,
      this.itemList});

  Sliders.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    storeId = json['store_id'];
    type = json['type'];
    typeId = json['type_id'];
    link = json['link'];
    image = json['image'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    if (json[ApiURL.dataKey].length > 0) {
      var data = json[ApiURL.dataKey][0];
      itemList = [];
      if (json['type'] == 'categories') {
        itemList!.add(Category.fromJson(data));
      } else if (json['type'] == 'products' ||
          json['type'] == 'combo_products') {
        Product product = Product.fromJson(data);
        if (product.type == comboProductType ||
            (product.type != comboProductType &&
                product.variants != null &&
                product.variants!.isNotEmpty)) {
          itemList!.add(product);
        }
      }
    }
  }
}
