import 'package:app_links/app_links.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/core/routes/routes.dart';

import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class DeepLinkHandler {
  late AppLinks _appLinks;
  Uri? _lastHandledUri;
  void init(BuildContext context) {
    _appLinks = AppLinks();

    // Handle links
    _appLinks.uriLinkStream.listen((uri) {
      final initialUri = uri;
      if (initialUri != _lastHandledUri) {
        _lastHandledUri = initialUri; // Track it so it's not handled again
        handleDeepLink(initialUri, context);
      }
    });
  }

  void handleDeepLink(Uri uri, BuildContext context) {
    if (uri.pathSegments.isNotEmpty && uri.pathSegments[0] == 'product') {
      final int productId =
          int.parse(uri.pathSegments[1]); // Get the product ID
      final int storeId = int.parse(uri.pathSegments[2]); // Get the store ID
      final String productType = uri.pathSegments[3]; // Get the product type

      Utils.navigateToScreen(
        context,
        Routes.productDetailsScreen,
        preventDuplicates: false,
        replacePrevious: Get.currentRoute == Routes.productDetailsScreen,
        arguments: {
          'storeId': storeId,
          'product': Product(id: productId, type: productType),
          'isComboProduct': productType == comboProductType,
          'productIds': [productId]
        },
      );
    }
    if (uri.pathSegments.isNotEmpty && uri.pathSegments[0] == 'seller') {
      final int sellerId =
          int.parse(uri.pathSegments[1]); // Get the seller ID from the URL
      final int storeId = int.parse(uri.pathSegments[2]); // Get the store ID
      Utils.navigateToScreen(context, Routes.sellerDetailScreen,
          preventDuplicates: false,
          replacePrevious: Get.currentRoute == Routes.sellerDetailScreen,
          arguments: {
            'sellerId': sellerId,
            'storeId': storeId,
          });
    }
  }
}
