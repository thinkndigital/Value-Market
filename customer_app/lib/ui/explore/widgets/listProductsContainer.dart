import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/commons/product/blocs/productsCubit.dart';
import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/ui/explore/widgets/ratingAndReviewCountContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/productDetailsScreen.dart';
import 'package:value_market_customer/core/theme/colors.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/commons/widgets/customTextButton.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/commons/widgets/favoriteButton.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class ListProductsContainer extends StatelessWidget {
  final List<Product> products;

  final Function loadMoreProducts;

  const ListProductsContainer(
      {super.key, required this.products, required this.loadMoreProducts});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsetsDirectional.only(
          start: appContentHorizontalPadding,
          end: appContentHorizontalPadding,
          top: MediaQuery.of(context).padding.top +
              appContentHorizontalPadding +
              80),
      child: NotificationListener<ScrollUpdateNotification>(
        onNotification: (notification) {
          if (notification.metrics.pixels >=
              notification.metrics.maxScrollExtent) {
            if (context.read<ProductsCubit>().hasMore()) {
              loadMoreProducts();
            }
          }
          return true;
        },
        child: ListView.builder(
            padding: const EdgeInsetsDirectional.only(
              top: 75,
              bottom: 100,
            ),
            itemCount: products.length,
            itemBuilder: (context, index) {
              final product = products[index];

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

              return Padding(
                padding: const EdgeInsetsDirectional.only(
                    bottom: appContentHorizontalPadding),
                child: GestureDetector(
                  onTap: () {
                    Get.toNamed(
                      Routes.productDetailsScreen,
                      arguments:
                          ProductDetailsScreen.buildArguments(product: product),
                    );
                  },
                  child: Container(
                      padding: const EdgeInsetsDirectional.all(8),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primaryContainer,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      width: MediaQuery.of(context).size.width,
                      height: 120,
                      child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CustomImageWidget(
                              url: (product.image ?? "").isNotEmpty
                                  ? (product.image ?? "")
                                  : product.imageMd!,
                              width: 86,
                              height: 100,
                              borderRadius: 4,
                            ),
                            const SizedBox(
                              width: 10.0,
                            ),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: CustomTextContainer(
                                          textKey: product.name ?? "",
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: Theme.of(context)
                                              .textTheme
                                              .titleSmall,
                                        ),
                                      ),
                                      FavoriteButton(
                                        product: product,
                                      ),
                                    ],
                                  ),
                                  Padding(
                                    padding:
                                        const EdgeInsetsDirectional.symmetric(
                                            vertical: 5.0),
                                    child: CustomTextContainer(
                                      textKey: product.shortDescription ?? "",
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall!
                                          .copyWith(
                                              height: 1.0,
                                              color: Theme.of(context)
                                                  .colorScheme
                                                  .secondary
                                                  .withValues(alpha: 0.67)),
                                    ),
                                  ),
                                  const Spacer(),
                                  Row(
                                    children: [
                                      Expanded(
                                        child: product.hasSpecialPrice()
                                            ? Text.rich(
                                                TextSpan(
                                                  children: [
                                                    TextSpan(
                                                        text: Utils
                                                            .priceWithCurrencySymbol(
                                                          context: context,
                                                          price: product
                                                              .getPrice(),
                                                        ),
                                                        style: Theme.of(context)
                                                            .textTheme
                                                            .titleMedium
                                                            ?.copyWith(
                                                              color: Theme.of(
                                                                      context)
                                                                  .colorScheme
                                                                  .primary,
                                                            )),
                                                    const TextSpan(text: "  "),
                                                    TextSpan(
                                                        text: Utils
                                                            .priceWithCurrencySymbol(
                                                                price: product
                                                                    .getBasePrice(),
                                                                context:
                                                                    context),
                                                        style: Theme.of(context)
                                                            .textTheme
                                                            .bodyMedium
                                                            ?.copyWith(
                                                              decoration:
                                                                  TextDecoration
                                                                      .lineThrough,
                                                              color: Theme.of(
                                                                      context)
                                                                  .colorScheme
                                                                  .secondary
                                                                  .withValues(
                                                                      alpha:
                                                                          0.67),
                                                            )),
                                                    const TextSpan(text: "  "),
                                                    TextSpan(
                                                        text:
                                                            "${Utils.formatDouble(product.getDiscoutPercentage())}% off",
                                                        style: Theme.of(context)
                                                            .textTheme
                                                            .labelSmall
                                                            ?.copyWith(
                                                                color:
                                                                    successStatusColor)),
                                                  ],
                                                ),
                                                maxLines: 1,
                                                overflow: TextOverflow.ellipsis,
                                              )
                                            : CustomTextContainer(
                                                textKey: Utils
                                                    .priceWithCurrencySymbol(
                                                        price: product
                                                            .getBasePrice(),
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
                                      ),
                                      if (product.hasAnyRating())
                                        RatingAndReviewCountContainer(
                                          rating: product.rating ?? "",
                                          ratingCount:
                                              product.noOfRatings?.toString() ??
                                                  "",
                                        )
                                    ],
                                  ),
                                ],
                              ),
                            )
                          ])),
                ),
              );
            }),
      ),
    );
  }
}
