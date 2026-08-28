import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/ui/explore/productDetails/productDetailsScreen.dart';
import 'package:value_market_customer/ui/explore/widgets/ratingAndReviewCountContainer.dart';
import 'package:value_market_customer/ui/mainScreen.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';

class compareWithSimilarItemsContainer extends StatefulWidget {
  final Product product;
  const compareWithSimilarItemsContainer({Key? key, required this.product})
      : super(key: key);

  @override
  State<compareWithSimilarItemsContainer> createState() =>
      _compareWithSimilarItemsContainerState();
}

class _compareWithSimilarItemsContainerState
    extends State<compareWithSimilarItemsContainer> {
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        if (comparableProducts.contains(widget.product)) {
          Utils.navigateToScreen(
            context,
            Routes.comparisonScreen,
          );
        } else {
          comparableProducts.add(widget.product);
          Utils.navigateToScreen(
            context,
            Routes.comparisonScreen,
          );
        }
      },
      child: CustomDefaultContainer(
          child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: CustomTextContainer(
              textKey: compareWithSimilarItemsKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          const Icon(Icons.arrow_forward_ios, size: 24)
        ],
      )),
    );
  }
}

class ComparisonScreen extends StatefulWidget {
  const ComparisonScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => ComparisonScreen();

  @override
  _ComparisonScreenState createState() => _ComparisonScreenState();
}

class _ComparisonScreenState extends State<ComparisonScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
        appBar: const CustomAppbar(titleKey: compareWithSimilarItemsKey),
        body: Padding(
          padding: const EdgeInsets.all(appContentHorizontalPadding),
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                ProductComparisonTable(products: comparableProducts),
                Wrap(
                    alignment: WrapAlignment.start,
                    spacing: appContentHorizontalPadding,
                    runSpacing: appContentHorizontalPadding * 2,
                    children: List.generate(comparableProducts.length, (index) {
                      final product = comparableProducts[index];
                      return GestureDetector(
                        onTap: () {
                          Utils.navigateToScreen(
                              context, Routes.productDetailsScreen,
                              arguments: product.type == comboProductType
                                  ? ProductDetailsScreen.buildArguments(
                                      product: product, isComboProduct: true)
                                  : ProductDetailsScreen.buildArguments(
                                      product: product,
                                    ));
                        },
                        child: Stack(
                          clipBehavior: Clip.none,
                          children: [
                            Container(
                              width: (MediaQuery.of(context).size.width -
                                      appContentHorizontalPadding * 3) /
                                  2,
                              padding: const EdgeInsetsDirectional.all(8),
                              decoration: BoxDecoration(
                                color: Theme.of(context)
                                    .colorScheme
                                    .primaryContainer,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Column(
                                children: <Widget>[
                                  Stack(
                                    children: [
                                      CustomImageWidget(
                                        url: (product.image ?? "").isNotEmpty
                                            ? (product.image ?? "")
                                            : product.imageMd!,
                                        borderRadius: 8,
                                        height: 180,
                                      ),
                                      if (product.hasAnyRating())
                                        Positioned(
                                          right: 5,
                                          bottom: 10,
                                          child: RatingAndReviewCountContainer(
                                              rating: product.rating!,
                                              ratingCount: product.noOfRatings
                                                      ?.toString() ??
                                                  ''),
                                        ),
                                    ],
                                  ),
                                  const SizedBox(
                                    height: 4,
                                  ),
                                  SizedBox(
                                    height: 40,
                                    child: CustomTextContainer(
                                      textKey: product.name ?? "",
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleSmall,
                                    ),
                                  ),
                                  const SizedBox(
                                    height: 2,
                                  ),
                                  SizedBox(
                                    height: 20,
                                    child: CustomTextContainer(
                                      textKey: product.storeName ?? "",
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .labelSmall!
                                          .copyWith(
                                              color: Theme.of(context)
                                                  .colorScheme
                                                  .secondary
                                                  .withValues(alpha: 0.67)),
                                    ),
                                  ),
                                  Wrap(
                                      runSpacing: 4.0,
                                      alignment: WrapAlignment.start,
                                      crossAxisAlignment:
                                          WrapCrossAlignment.center,
                                      children: [
                                        if (product.hasSpecialPrice()) ...[
                                          CustomTextContainer(
                                              textKey:
                                                  Utils.priceWithCurrencySymbol(
                                                      price: product.getPrice(),
                                                      context: context),
                                              style: Theme.of(context)
                                                  .textTheme
                                                  .titleMedium
                                                  ?.copyWith(
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .secondary,
                                                  )),
                                          const SizedBox(
                                            width: 4,
                                          ),
                                          CustomTextContainer(
                                              textKey:
                                                  Utils.priceWithCurrencySymbol(
                                                      price: product
                                                          .getBasePrice(),
                                                      context: context),
                                              style: Theme.of(context)
                                                  .textTheme
                                                  .bodyMedium
                                                  ?.copyWith(
                                                    decoration: TextDecoration
                                                        .lineThrough,
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .secondary
                                                        .withValues(
                                                            alpha: 0.67),
                                                  )),
                                          const SizedBox(
                                            width: 4,
                                          ),
                                        ] else
                                          CustomTextContainer(
                                            textKey:
                                                Utils.priceWithCurrencySymbol(
                                                    price:
                                                        product.getBasePrice(),
                                                    context: context),
                                            style: Theme.of(context)
                                                .textTheme
                                                .titleMedium
                                                ?.copyWith(
                                                  color: Theme.of(context)
                                                      .colorScheme
                                                      .primary,
                                                ),
                                          ),
                                      ]),
                                  buildListTile(
                                      madeInKey, product.madeIn ?? '-'),
                                  // buildListTile(warrantyKey,
                                  //     product.warrantyPeriod ?? noKey),
                                  // buildListTile(guaranteeKey,
                                  //     product.guaranteePeriod ?? noKey),
                                  buildListTile(
                                      returnableKey,
                                      product.isReturnable == 1
                                          ? yesKey
                                          : noKey),
                                  buildListTile(
                                      product.isCancelable == 1
                                          ? cancellableTillKey
                                          : cancellableKey,
                                      product.isCancelable == 1
                                          ? 'Till ${product.cancelableTill!}'
                                          : noKey),
                                ],
                              ),
                            ),
                            Positioned(
                              right: 0,
                              top: 0,
                              child: IconButton(
                                  onPressed: () {
                                    setState(() {
                                      comparableProducts.remove(product);
                                    });
                                  },
                                  icon: Icon(
                                    Icons.close,
                                    color:
                                        Theme.of(context).colorScheme.primary,
                                  )),
                            ),
                          ],
                        ),
                      );
                    })),
              ],
            ),
          ),
        ));
  }

  buildListTile(String title, String value) {
    return Center(
      child: CustomTextContainer(
        textKey: value.isEmpty ? '-' : value,
        style: Theme.of(context)
            .textTheme
            .bodyMedium!
            .copyWith(fontWeight: FontWeight.w500),
      ),
    );
  }
}

class ProductComparisonTable extends StatelessWidget {
  final List<Product> products;

  ProductComparisonTable({required this.products});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
        height: 345,
        child: Container(
          margin: const EdgeInsets.only(right: 5),
          padding: EdgeInsets.only(right: 5),
          decoration: const BoxDecoration(
              border: Border(right: BorderSide(color: Colors.grey))),
          child: const Column(
            mainAxisAlignment: MainAxisAlignment.end,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              CustomTextContainer(textKey: madeInKey),
              CustomTextContainer(textKey: returnableKey),
              CustomTextContainer(textKey: cancellableKey),
            ],
          ),
        ));
  }
}
