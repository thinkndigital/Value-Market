import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/ui/explore/productDetails/widgets/mediaViewer.dart';
import 'package:eshop_plus/core/theme/colors.dart';

import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ProductInfoContainer extends StatefulWidget {
  final Product product;
  final bool isFullScreen;
  const ProductInfoContainer(
      {Key? key, required this.product, this.isFullScreen = false})
      : super(key: key);

  @override
  State<ProductInfoContainer> createState() => _ProductInfoContainerState();
}

class _ProductInfoContainerState extends State<ProductInfoContainer> {
  List<String> _productImages = [];
  late Product product;

  @override
  void initState() {
    super.initState();
    product = widget.product;

    _productImages.clear();
    if (product.productType != variableProductType) {
      // For simple products, add the main image and other images from the product model
      _productImages.insert(0, product.image!);
      _productImages.addAll(product.otherImages ?? []);
    }
    if (product.productType == variableProductType) {
      if (product.selectedVariant != null) {
        // For variable products, add images only from the selected variant
        if (product.selectedVariant!.images != null &&
            product.selectedVariant!.images!.isNotEmpty) {
          _productImages.addAll(product.selectedVariant!.images!);
          _productImages.add(product.image!);
          _productImages.addAll(product.otherImages ?? []);
        } else {
          // If the selected variant has no images, fallback to the main product image
          _productImages.insert(0, product.image!);
          _productImages.addAll(product.otherImages ?? []);
        }
      } else {
        // Default behavior (e.g., if no variant is selected for a variable product)
        _productImages.insert(0, product.image!);
        _productImages.addAll(product.otherImages ?? []);
      }
    }
    if (product.videoType != '' &&
        product.video != '' &&
        product.video!.isNotEmpty) {
      _productImages.add('video');
    }
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(
          appContentHorizontalPadding, 12, appContentHorizontalPadding, 0),
      child: SizedBox(
        width: MediaQuery.of(context).size.width,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            buildImageSection(),
            const SizedBox(
              height: appContentHorizontalPadding,
            ),
            CustomTextContainer(
              textKey: widget.product.storeName ?? "",
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: Theme.of(context).colorScheme.secondary,
                  ),
            ),
            const SizedBox(
              height: 5.0,
            ),
            CustomTextContainer(
              textKey: product.type == variableProductType &&
                      product.selectedVariant != null
                  ? '${product.name}/ ${product.selectedVariant!.variantValues}'
                  : product.name ?? "",
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    color: Theme.of(context).colorScheme.secondary,
                    overflow: TextOverflow.visible,
                  ),
            ),
            widget.product.hasSpecialPrice()
                ? Text.rich(TextSpan(children: [
                    TextSpan(
                        text: Utils.priceWithCurrencySymbol(
                            price: widget.product.getPrice(), context: context),
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: Theme.of(context).colorScheme.primary,
                            )),
                    const TextSpan(text: "  "),
                    TextSpan(
                        text: Utils.priceWithCurrencySymbol(
                            price: widget.product.getBasePrice(),
                            context: context),
                        style:
                            Theme.of(context).textTheme.titleMedium?.copyWith(
                                  decoration: TextDecoration.lineThrough,
                                  color: Theme.of(context)
                                      .colorScheme
                                      .secondary
                                      .withValues(alpha: 0.67),
                                )),
                    const TextSpan(text: "  "),
                    TextSpan(
                        text:
                            "${Utils.formatDouble(widget.product.getDiscoutPercentage())}% off",
                        style: Theme.of(context)
                            .textTheme
                            .labelLarge
                            ?.copyWith(color: successStatusColor))
                  ]))
                : CustomTextContainer(
                    textKey: Utils.priceWithCurrencySymbol(
                        price: widget.product.getBasePrice(), context: context),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: Theme.of(context).colorScheme.primary,
                        ),
                  ),
            const SizedBox(
              height: 5.0,
            ),
            CustomTextContainer(
                textKey:
                    "(${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: inclusiveOfAllTaxesKey)})",
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Theme.of(context).colorScheme.secondary,
                    )),
            const SizedBox(
              height: 7.5,
            ),
            if (widget.product.rating != '' &&
                widget.product.rating != '0.00' &&
                widget.product.rating != '0')
              Row(children: <Widget>[
                Icon(Icons.star,
                    size: 12.0, color: Theme.of(context).colorScheme.primary),
                const SizedBox(
                  width: 2.5,
                ),
                CustomTextContainer(
                    textKey: widget.product.rating ?? "",
                    style: Theme.of(context).textTheme.bodyMedium!),
                DesignConfig.smallWidthSizedBox,
                CustomTextContainer(
                    textKey: widget.product.noOfRatings?.toString() ?? "",
                    style: Theme.of(context).textTheme.bodySmall!),
                const SizedBox(
                  width: 2,
                ),
                CustomTextContainer(
                    textKey: ratingsKey,
                    style: Theme.of(context).textTheme.bodySmall!),
              ]),
          ],
        ),
      ),
    );
  }

  buildImageSection() {
    return MediaViewer(
      imageUrls: _productImages,
      videoUrl: widget.product.video,
      videoType: widget.product.videoType,
  
      isFullScreen: widget.isFullScreen,
    );
  }
}
