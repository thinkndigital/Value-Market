import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customStatusContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../commons/models/product.dart';
import '../../../utils/designConfig.dart';

class ProductInfoContainer extends StatelessWidget {
  final Product product;
  final bool isProductScreen;
  final bool isProductDetailScreen;
  final Widget? editIcon;
  final VoidCallback? onTapProduct;
  final Function getValueList;
  const ProductInfoContainer(
      {Key? key,
      required this.product,
      this.isProductScreen = true,
      this.isProductDetailScreen = false,
      this.editIcon,
      this.onTapProduct,
      required this.getValueList})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return (product.type == variableProductType &&
                (product.variants!.isEmpty || product.attributes!.isEmpty)) ||
            (product.type != comboProductType && product.variants!.isEmpty)
        ? SizedBox.shrink()
        : GestureDetector(
            onTap: onTapProduct,
            child: Container(
              padding: const EdgeInsetsDirectional.all(8),
              decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.transparent)),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.start,
                children: <Widget>[
                  buildProductImage(context),
                  DesignConfig.smallWidthSizedBox,
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            buildProductName(context),
                            if (!isProductScreen) editIcon ?? Container()
                          ],
                        ),
                        DesignConfig.smallHeightSizedBox,
                        if (product.type != variableProductType) ...[
                          buildLabelAndValue(
                              context,
                              priceKey,
                              Utils.priceWithCurrencySymbol(
                                  price: product.hasSpecialPrice()
                                      ? product.getPrice()
                                      : product.getBasePrice(),
                                  context: context)),
                          buildLabelAndValue(
                              context,
                              sellingPriceKey,
                              Utils.priceWithCurrencySymbol(
                                  price: product.getSellingPrice(),
                                  context: context)),
                        ],
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: <Widget>[
                            if (product.type != comboProductType)
                              CustomTextContainer(
                                textKey: allProductTypes[product.type!] ?? '',
                                style: Theme.of(context).textTheme.bodySmall,
                              )
                            else
                              buildLabelAndValue(
                                context,
                                quantityKey,
                                product.stock.toString(),
                              ),
                            CustomStatusContainer(
                                getValueList: getValueList,
                                status: product.status!.toString())
                          ],
                        )
                      ],
                    ),
                  )
                ],
              ),
            ),
          );
  }

  Expanded buildProductName(BuildContext context) {
    return Expanded(
      child: CustomTextContainer(
        textKey: product.name ?? "",
        maxLines: 2,
        style: Theme.of(context).textTheme.bodyMedium,
        overflow: isProductDetailScreen
            ? TextOverflow.visible
            : TextOverflow.ellipsis,
      ),
    );
  }

  Widget buildProductImage(BuildContext context) {
    double imageSize = isProductDetailScreen
        ? MediaQuery.of(context).size.height * 0.12
        : MediaQuery.of(context).size.height * 0.1;
    return CustomImageWidget(
      url: product.image ?? '',
      width: imageSize,
      height: imageSize,
      borderRadius: 4,
    );
  }

  buildLabelAndValue(BuildContext context, String title, String value) {
    return Text.rich(
      TextSpan(
        children: [
          TextSpan(
              text:
                  '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: title)} : ',
              style: Theme.of(context).textTheme.labelMedium!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.67))),
          TextSpan(
              text: value,
              style: Theme.of(context)
                  .textTheme
                  .labelSmall!
                  .copyWith(color: Theme.of(context).colorScheme.secondary)),
        ],
      ),
    );
  }
}
