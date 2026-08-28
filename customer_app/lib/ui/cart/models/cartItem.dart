import 'package:hive/hive.dart';
part 'cartItem.g.dart';

@HiveType(typeId: 0)
class CartItem extends HiveObject {
  @HiveField(0)
  final int productId;

  @HiveField(1)
  final int storeId;

  @HiveField(2)
  final String productType;

  @HiveField(3)
  final int qty;

  @HiveField(4)
  final int saveForLater;

  CartItem({
    required this.productId,
    required this.storeId,
    required this.productType,
    required this.qty,
    required this.saveForLater,
  });
}
