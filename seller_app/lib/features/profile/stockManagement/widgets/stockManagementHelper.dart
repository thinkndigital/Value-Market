import 'package:eshopplus_seller/commons/models/productVariant.dart';

import '../../../../commons/models/product.dart';

class StockManagementHelper {
  static getProductQuantity(Product product, ProductVariant? productVariant) {
    String qty = "0";
    switch (product.stockType) {
      case "1":
        qty = (product.variants != null && product.variants!.isNotEmpty)
            ? product.variants!.first.stock != null &&
                    product.variants!.first.stock!.trim().isEmpty
                ? "0"
                : product.variants!.first.stock!
            : "0";
        break;
      case "2":
        qty = productVariant != null &&
                productVariant.stock != null &&
                productVariant.stock!.trim().isNotEmpty
            ? productVariant.stock!
            : "0";

        break;
      default:
        qty = product.stock == null || product.stock!.trim().isEmpty
            ? "0"
            : product.stock!;
    }
    return qty;
  }
}
