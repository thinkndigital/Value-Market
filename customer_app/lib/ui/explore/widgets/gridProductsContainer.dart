import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';

import 'package:eshop_plus/commons/product/models/product.dart';

import 'package:eshop_plus/commons/product/widgets/productCard.dart';
import 'package:eshop_plus/ui/explore/productDetails/productDetailsScreen.dart';

import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customTextButton.dart';

import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';

class GridProductsContainer extends StatelessWidget {
  final bool isExploreScreen;
  final bool forSellerDetailScreen;
  final bool sellerProductScreen;
  final List<Product> products;
  final Function loadMoreProducts;
  final bool hasMore;
  final bool fetchMoreError;

  const GridProductsContainer({
    super.key,
    this.isExploreScreen = true,
    this.forSellerDetailScreen = false,
    this.sellerProductScreen = false,
    required this.products,
    required this.loadMoreProducts,
    required this.hasMore,
    required this.fetchMoreError,
  });

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      SizedBox(
        height: sellerProductScreen
            ? MediaQuery.of(context).padding.top + 44
            : !isExploreScreen && !forSellerDetailScreen
                ? 0
                : MediaQuery.of(context).padding.top +
                    appContentHorizontalPadding +
                    130,
      ),
      Flexible(
          child: Container(
        height: MediaQuery.of(context).size.height,
        width: MediaQuery.of(context).size.width,
        color: Theme.of(context).colorScheme.primaryContainer,
        padding: const EdgeInsetsDirectional.only(
          start: appContentHorizontalPadding,
          end: appContentHorizontalPadding,
        ),
        child: NotificationListener<ScrollUpdateNotification>(
          onNotification: (notification) {
            if (notification.metrics.pixels >=
                notification.metrics.maxScrollExtent) {
              if (hasMore) {
                loadMoreProducts();
              }
            }
            return true;
          },
          child: SingleChildScrollView(
            padding: const EdgeInsetsDirectional.only(
              top: appContentHorizontalPadding,
              bottom: 75,
            ),
            child: LayoutBuilder(builder: (context, boxConstraint) {
              return Wrap(
                alignment: WrapAlignment.start,
                spacing: appContentHorizontalPadding,
                runSpacing: appContentHorizontalPadding,
                children: List.generate(products.length, (index) {
                  final product = products[index];

                  if (hasMore) {
                    if (index == products.length - 1) {
                      if (fetchMoreError) {
                        return Center(
                          child: CustomTextButton(
                              buttonTextKey: retryKey,
                              onTapButton: () {
                                loadMoreProducts();
                              }),
                        );
                      }

                      return Center(
                        child: CustomCircularProgressIndicator(
                            indicatorColor:
                                Theme.of(context).colorScheme.primary),
                      );
                    }
                  }

                  return GestureDetector(
                      onTap: () => {
                            Utils.navigateToScreen(
                                context, Routes.productDetailsScreen,
                                arguments: product.type == comboProductType
                                    ? ProductDetailsScreen.buildArguments(
                                        product: product, isComboProduct: true)
                                    : ProductDetailsScreen.buildArguments(
                                        product: product,
                                      ))
                          },
                      child: ProductCard(
                        product: product,
                        backgroundColor:
                            Theme.of(context).colorScheme.primaryContainer,
                      ));
                }).toList(),
              );
            }),
          ),
        ),
      ))
    ]);
  }
}
