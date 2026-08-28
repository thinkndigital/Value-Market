import 'package:value_market_seller/commons/models/product.dart';
import 'package:value_market_seller/commons/models/productVariant.dart';
import 'package:value_market_seller/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_seller/commons/widgets/customImageWidget.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';

import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';

class ComboProductList extends StatelessWidget {
  final Product product;
  const ComboProductList({Key? key, required this.product}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomTextContainer(
            textKey: comboProductListKey,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          DesignConfig.defaultHeightSizedBox,
          buildProductList(product.productDetails!),
        ],
      ),
    );
  }

  Widget buildProductList(List<Product> products) {
    return ListView.separated(
      separatorBuilder: (context, index) => DesignConfig.smallHeightSizedBox,
      itemCount: products.length,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemBuilder: (context, index) {
        final prdct = products[index];
        ProductVariant? selectedVariant;

        if (prdct.type == variableProductType) {
          for (var element in prdct.variants!) {
            if (product.productVariantIds!
                .split(',')
                .contains(element.id.toString())) {
              selectedVariant = element;
              break;
            }
          }
        }
        return Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
              color: Theme.of(context).scaffoldBackgroundColor,
              borderRadius: BorderRadius.circular(8)),
          child: Row(
            children: <Widget>[
              CustomImageWidget(
                url: prdct.type == variableProductType
                    ? selectedVariant != null &&
                            selectedVariant.images!.isNotEmpty
                        ? selectedVariant.images!.first
                        : product.image ?? ''
                    : prdct.image ?? '',
                width: 48,
                height: 48,
                borderRadius: 4,
              ),
              DesignConfig.defaultWidthSizedBox,
              Expanded(
                child: CustomTextContainer(
                  textKey: prdct.type == variableProductType &&
                          selectedVariant != null
                      ? '${prdct.name}/ ${selectedVariant.variantValues}'
                      : prdct.name!,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
