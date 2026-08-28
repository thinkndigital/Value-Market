import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/models/product.dart';
import 'package:eshopplus_seller/features/product/widgets/productInfoContainer.dart';

import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ListProductsContainer extends StatelessWidget {
  final List<Product> products;

  final Function loadMoreProducts;

  const ListProductsContainer(
      {super.key, required this.products, required this.loadMoreProducts});

  @override
  Widget build(BuildContext context) {
    return NotificationListener<ScrollUpdateNotification>(
      onNotification: (notification) {
        if (notification.metrics.pixels ==
            notification.metrics.maxScrollExtent) {
          if (context.read<ProductsCubit>().hasMore()) {
            loadMoreProducts();
          }
        }
        return true;
      },
      child: ListView.separated(
          padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
          separatorBuilder: (context, index) {
            Product product = products[index];
            if ((product.type == variableProductType &&
                    (product.variants!.isEmpty ||
                        product.attributes!.isEmpty)) ||
                (product.type != comboProductType &&
                    product.variants!.isEmpty)) {
              return const SizedBox();
            }
            return DesignConfig.defaultHeightSizedBox;
          },
          itemCount: products.length,
          itemBuilder: (context, index) {
            if (context.read<ProductsCubit>().hasMore()) {
              if (index == products.length - 1) {
                if (context.read<ProductsCubit>().fetchMoreError()) {
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
                      indicatorColor: Theme.of(context).colorScheme.primary),
                );
              }
            }

            return ProductInfoContainer(
                product: products[index],
                getValueList: Utils.getProductStatusTextAndColor,
                onTapProduct: () {
                  FocusScope.of(context).unfocus();

                  Utils.navigateToScreen(context, Routes.productDetailsScreen,
                      arguments: {
                        'product': products[index],
                        'productsCubit': context.read<ProductsCubit>()
                      });
                });
          }),
    );
  }
}
