import 'package:cached_network_image/cached_network_image.dart';
import 'package:eshop_plus/commons/product/widgets/tagContainer.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/ui/home/mostSellingProduct/mostSellingProductCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';

import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/ui/explore/screens/exploreScreen.dart';
import 'package:eshop_plus/ui/explore/productDetails/productDetailsScreen.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'package:eshop_plus/core/constants/themeConstants.dart';
import '../../../utils/utils.dart';
import '../../../core/theme/colors.dart';
import '../../../commons/widgets/favoriteButton.dart';
import '../../explore/widgets/ratingAndReviewCountContainer.dart';

class MostSellingProductSection extends StatelessWidget {
  const MostSellingProductSection({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    Size size = MediaQuery.of(context).size;
    return BlocConsumer<MostSellingProductsCubit, MostSellingProductsState>(
        listener: (context, state) {},
        builder: (context, state) {
          if (state is MostSellingProductsFetchSuccess) {
            return Container(
                height: size.height * 0.6,
                alignment: Alignment.center,
                margin: const EdgeInsetsDirectional.only(
                    bottom: appContentHorizontalPadding / 2),
                child: CachedNetworkImage(
                    imageUrl: context
                        .read<StoresCubit>()
                        .getDefaultStore()
                        .bannerImageForMostSellingProduct,
                    fit: BoxFit.fitHeight,
                    placeholder: (context, url) => DesignConfig.shimmerEffect(
                        size.height * 0.6, double.maxFinite),
                    imageBuilder: (context, imageProvider) => Container(
                        width: double.maxFinite,
                        height: size.height * 0.6,
                        decoration: BoxDecoration(
                          image: DecorationImage(
                            fit: BoxFit.fitHeight,
                            image: imageProvider,
                          ),
                        ),
                        child: Container(
                          padding: const EdgeInsetsDirectional.all(16),
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.65),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              CustomTextContainer(
                                textKey: mostSellingProductsKey,
                                style: Theme.of(context)
                                    .textTheme
                                    .displayMedium!
                                    .copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .onPrimary),
                              ),
                              DesignConfig.defaultHeightSizedBox,
                              CustomRoundedButton(
                                  widthPercentage: 0.52,
                                  buttonTitle: seeCollectionKey,
                                  showBorder: true,
                                  backgroundColor: Colors.transparent,
                                  borderColor:
                                      Theme.of(context).colorScheme.onPrimary,
                                  onTap: () => Utils.navigateToScreen(
                                      context, Routes.exploreScreen,
                                      arguments: ExploreScreen.buildArguments(
                                        title: mostSellingProductsKey,
                                        productIds: state.products
                                            .map((e) => e.id!)
                                            .toList(),
                                      )),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: <Widget>[
                                      CustomTextContainer(
                                        textKey: seeCollectionKey,
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodyMedium!
                                            .copyWith(
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .onPrimary),
                                      ),
                                      DesignConfig.smallWidthSizedBox,
                                      Icon(
                                        Icons.arrow_circle_right_outlined,
                                        color: Theme.of(context)
                                            .colorScheme
                                            .onPrimary,
                                      )
                                    ],
                                  )),
                              const SizedBox(
                                height: 40,
                              ),
                              SizedBox(
                                height: 140,
                                child: ListView.separated(
                                    separatorBuilder: (context, index) =>
                                        DesignConfig.defaultWidthSizedBox,
                                    padding: const EdgeInsetsDirectional.only(
                                        end: appContentHorizontalPadding),
                                    clipBehavior: Clip.none,
                                    scrollDirection: Axis.horizontal,
                                    shrinkWrap: true,
                                    itemCount: state.products.length >
                                            maxLimitOfWidgetsInHome
                                        ? maxLimitOfWidgetsInHome
                                        : state.products.length,
                                    itemBuilder: (context, index) =>
                                        productInfoContainer(
                                            state.products[index], context)),
                              ),
                            ],
                          ),
                        ))));
          }

          return Container();
        });
  }

  productInfoContainer(Product product, BuildContext context) {
    return GestureDetector(
      onTap: () {
        Utils.navigateToScreen(context, Routes.productDetailsScreen,
            arguments: ProductDetailsScreen.buildArguments(
                product: product,
                productIds: [product.id!],
                isComboProduct: product.type == comboProductType));
      },
      child: Container(
          padding: const EdgeInsetsDirectional.all(8),
          margin: const EdgeInsetsDirectional.only(
              bottom: appContentHorizontalPadding),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primaryContainer,
            borderRadius: BorderRadius.circular(8),
          ),
          width: MediaQuery.of(context).size.width * 0.9,
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(
              child: CustomImageWidget(
                url: (product.image ?? ""),
                height: 100,
                width: 86,
                borderRadius: 8,
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Positioned.directional(
                        textDirection: Directionality.of(context),
                        bottom: 0,
                        child: product.newArrival == true
                            ? TagContainer(
                                tag: newArrivalKey,
                                color: successStatusColor,
                                fontSize: 8,
                              )
                            : product.bestSeller == true
                                ? TagContainer(
                                    tag: bestSellerKey,
                                    fontSize: 8,
                                  )
                                : SizedBox.shrink()),
                  ],
                ),
              ),
            ),
            const SizedBox(
              width: 10.0,
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: CustomTextContainer(
                            textKey: product.productName ?? "",
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleSmall,
                          ),
                        ),
                        FavoriteButton(
                          product: product,
                        ),
                      ],
                    ),
                    Padding(
                      padding:
                          const EdgeInsetsDirectional.symmetric(vertical: 5.0),
                      child: CustomTextContainer(
                        textKey: product.shortDescription ?? "",
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall!.copyWith(
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
                          child: double.parse(product.specialPrice!) != 0.0
                              ? Text.rich(
                                  TextSpan(
                                    children: [
                                      TextSpan(
                                          text: Utils.priceWithCurrencySymbol(
                                            context: context,
                                            price: double.parse(
                                                product.specialPrice!),
                                          ),
                                          style: Theme.of(context)
                                              .textTheme
                                              .titleMedium
                                              ?.copyWith(
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .primary,
                                              )),
                                      const TextSpan(text: "  "),
                                      TextSpan(
                                          text: Utils.priceWithCurrencySymbol(
                                              price:
                                                  double.parse(product.price!),
                                              context: context),
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodyMedium
                                              ?.copyWith(
                                                decoration:
                                                    TextDecoration.lineThrough,
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .secondary
                                                    .withValues(alpha: 0.67),
                                              )),
                                      const TextSpan(text: "  "),
                                      TextSpan(
                                          text:
                                              "${Utils.formatDouble(product.getDiscoutPercentageForMostSellingProduct())}% off",
                                          style: Theme.of(context)
                                              .textTheme
                                              .labelSmall
                                              ?.copyWith(
                                                  color: successStatusColor)),
                                    ],
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                )
                              : CustomTextContainer(
                                  textKey: Utils.priceWithCurrencySymbol(
                                      price: double.parse(product.price!),
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
                            rating: product.rating.toString(),
                            ratingCount: product.noOfRatings?.toString() ?? "",
                          )
                      ],
                    )
                  ],
                ),
              ),
            )
          ])),
    );
  }
}
