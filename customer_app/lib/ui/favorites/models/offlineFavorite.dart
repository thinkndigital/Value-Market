import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';

class OfflineFavorite {
  final int id; // Product ID or Seller ID
  final String productType;
  final String type; // 'product' or 'seller'

  OfflineFavorite(
      {required this.id, required this.productType, required this.type});

  // Convert OfflineFavorite to Hive-storable map
  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'productType': productType,
      'type': type,
    };
  }

  // Convert map back to OfflineFavorite
  factory OfflineFavorite.fromMap(Map<dynamic, dynamic> map) {
    return OfflineFavorite(
      id: map['id'],
      productType: map['productType'],
      type: map['type'],
    );
  }
  dynamic toModel() {
    if (type == 'product') {
      return Product(
          id: id,
          productType:
              productType); // Replace with your actual Product model fields
    } else if (type == 'seller') {
      return Seller(
        sellerId: id,
      ); // Replace with your actual Seller model fields
    }
    return null;
  }
}
