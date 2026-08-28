import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/home/featuredSection/featuredSectionCubit.dart';
import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/ui/explore/screens/exploreScreen.dart';
import 'package:value_market_customer/ui/explore/widgets/ratingAndReviewCountContainer.dart';
import 'package:value_market_customer/ui/home/widgets/buildHeader.dart';
import 'package:value_market_customer/commons/product/widgets/productCard.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'fesaturedSection.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customRoundedButton.dart';

class FeaturedSectionContainer extends StatelessWidget {
  const FeaturedSectionContainer({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<FeaturedSectionCubit, FeaturedSectionState>(
      builder: (context, state) {
        if (state is FeaturedSectionFetchSuccess) {
          return ListView.separated(
            separatorBuilder: (context, index) =>
                DesignConfig.smallHeightSizedBox,
            itemCount: state.sections.length,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemBuilder: (context, index) {
              FeaturedSection section = state.sections[index];

              if (section.productDetails != null &&
                  section.productDetails!.isNotEmpty) {
                return Container(
                  color: Utils.getColorFromHexValue(section.backgroundColor!) ??
                      Theme.of(context).colorScheme.primaryContainer,
                  padding: const EdgeInsetsDirectional.symmetric(
                      vertical: appContentHorizontalPadding),
                  child: Column(
                    children: <Widget>[
                      buildSectionHeader(context, section),
                      DesignConfig.defaultHeightSizedBox,
                      section.productType == 'custom_combo_products'
                          ? buildComboProducts(context, section)
                          : buildSectionContent(context, section)
                    ],
                  ),
                );
              }
              return const SizedBox.shrink();
            },
          );
        } else if (state is FeaturedSectionFetchInProgress) {
          return CustomCircularProgressIndicator(
            indicatorColor: Theme.of(context).colorScheme.primary,
          );
        }

        return const SizedBox.shrink();
      },
    );
  }

  buildSectionHeader(BuildContext context, FeaturedSection section) {
    return section.headerStyle == 'header_style_1'
        ? BuildHeader(
            title: section.title!,
            subtitle: section.shortDescription,
            showSeeAllButton: showSeeAllButton(section),
            onTap: () => onTapOfSeeAll(context, section))
        : section.headerStyle == 'header_style_2'
            ? GestureDetector(
                onTap: () {
                  if (!showSeeAllButton(section)) return;
                  onTapOfSeeAll(context, section);
                },
                child: SizedBox(
                  height: 100,
                  width: double.maxFinite,
                  child: CustomImageWidget(
                    url: section.bannerImage ?? "",
                  ),
                ),
              )
            : Column(
                children: <Widget>[
                  BuildHeader(
                      title: section.title!,
                      subtitle: section.shortDescription,
                      showSeeAllButton: showSeeAllButton(section),
                      onTap: () => onTapOfSeeAll(context, section)),
                  DesignConfig.defaultHeightSizedBox,
                  SizedBox(
                    height: 100,
                    child: CustomImageWidget(
                      url: section.bannerImage ?? "",
                    ),
                  )
                ],
              );
  }

  showSeeAllButton(FeaturedSection section) {
    //as per design , we will show max 10 products in style 1 and 4 in style 2 and 3 in style 3
    return section.productType == 'custom_combo_products'
        ? false
        : (int.tryParse(section.total!) ?? 0) >
                (section.style == 'style_1'
                    ? maxLimitOfWidgetsInHome
                    : section.style == 'style_2'
                        ? 4
                        : 3)
            ? true
            : false;
  }

  onTapOfSeeAll(BuildContext context, FeaturedSection section) {
    Utils.navigateToScreen(context, Routes.exploreScreen,
        arguments: ExploreScreen.buildArguments(
          title: section.title,
          productIds:
              section.productIds != null && section.productIds!.isNotEmpty
                  ? section.productIds!.split(',').map(int.parse).toList()
                  : section.productDetails!.map((e) => e.id!).toList(),
        ));
  }

  buildSectionContent(BuildContext context, FeaturedSection section) {
    if (section.productDetails != null) {
      return section.style == 'style_1'
          ? SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(
                  horizontal: appContentHorizontalPadding),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: List.generate(
                    section.productDetails!.length > maxLimitOfWidgetsInHome
                        ? maxLimitOfWidgetsInHome
                        : section.productDetails!.length, (index) {
                  return Padding(
                    padding: const EdgeInsetsDirectional.only(
                        end: appContentHorizontalPadding),
                    child: ProductCard(
                      product: section.productDetails![index],
                      backgroundColor: Utils.getColorFromHexValue(
                              section.backgroundColor!) ??
                          Theme.of(context).colorScheme.primaryContainer,
                    ),
                  );
                }),
              ),
            )
          : section.style == 'style_2'
              ? LayoutBuilder(builder: (context, boxConstraints) {
                  return Padding(
                    padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: appContentHorizontalPadding),
                    child: Wrap(
                      spacing: appContentHorizontalPadding,
                      runSpacing: appContentHorizontalPadding,
                      children: List.generate(
                          section.productDetails!.length > 4
                              ? 4
                              : section.productDetails!.length,
                          (index) => ProductCard(
                                product: section.productDetails![index],
                                backgroundColor: Utils.getColorFromHexValue(
                                        section.backgroundColor!) ??
                                    Theme.of(context)
                                        .colorScheme
                                        .primaryContainer,
                              )),
                    ),
                  );
                })
              : LayoutBuilder(builder: (context, boxConstraints) {
                  return Padding(
                    padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: appContentHorizontalPadding),
                    child: Wrap(
                      spacing: appContentHorizontalPadding,
                      runSpacing: appContentHorizontalPadding,
                      children: List.generate(
                          section.productDetails!.length > 3
                              ? 3
                              : section.productDetails!.length,
                          (index) => SizedBox(
                              width: index == 0 ? double.maxFinite : null,
                              child: ProductCard(
                                product: section.productDetails![index],
                                backgroundColor: Utils.getColorFromHexValue(
                                        section.backgroundColor!) ??
                                    Theme.of(context)
                                        .colorScheme
                                        .primaryContainer,
                              ))),
                    ),
                  );
                });
    }
    return const SizedBox.shrink();
  }

  buildComboProducts(BuildContext context, FeaturedSection section) {
    if (section.productDetails != null) {
      return Container(
        padding: const EdgeInsetsDirectional.symmetric(
            horizontal: appContentHorizontalPadding),
        color: Utils.getColorFromHexValue(section.backgroundColor!) ??
            Theme.of(context).colorScheme.primaryContainer,
        child: Column(
          children: <Widget>[
            Container(
              padding: const EdgeInsetsDirectional.all(
                  appContentHorizontalPadding / 2),
              decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.secondary,
                  borderRadius: BorderRadius.circular(borderRadius)),
              child: Row(children: <Widget>[
                CustomImageWidget(
                  url: context.read<StoresCubit>().getDefaultStore().image,
                  height: 50,
                  width: 50,
                  borderRadius: 2,
                ),
                DesignConfig.smallWidthSizedBox,
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CustomTextContainer(
                          textKey:
                              section.productDetails!.first.storeName ?? '',
                          maxLines: 1,
                          style: Theme.of(context)
                              .textTheme
                              .labelLarge!
                              .copyWith(
                                  color:
                                      Theme.of(context).colorScheme.onPrimary)),
                      const SizedBox(
                        height: 4,
                      ),
                      RatingAndReviewCountContainer(
                          rating: section.productDetails!.first.sellerRating!,
                          ratingCount: section
                              .productDetails!.first.sellerNoOfRatings
                              .toString())
                    ],
                  ),
                ),
              ]),
            ),
            DesignConfig.defaultHeightSizedBox,
            Wrap(
                spacing: appContentHorizontalPadding,
                runSpacing: appContentHorizontalPadding / 2,
                children: List.generate(
                  section.productDetails!.length > 1 ? 2 : 1,
                  (index) {
                    return ProductCard(product: section.productDetails![index]);
                  },
                )),
            if (section.productDetails!.length > 2) ...[
              DesignConfig.defaultHeightSizedBox,
              CustomRoundedButton(
                  widthPercentage: 0.6,
                  buttonTitle: viewAllCombosKey,
                  showBorder: true,
                  backgroundColor: Colors.transparent,
                  borderColor: Theme.of(context).inputDecorationTheme.iconColor,
                  onTap: () =>
                      Utils.navigateToScreen(context, Routes.exploreScreen,
                          arguments: ExploreScreen.buildArguments(
                            title: section.title,
                            productIds: section.productIds!
                                .split(',')
                                .map(int.parse)
                                .toList(),
                            isComboProduct: true,
                          )),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      CustomTextContainer(
                        textKey: viewAllCombosKey,
                        style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                            color: Theme.of(context).colorScheme.secondary),
                      ),
                      DesignConfig.smallWidthSizedBox,
                      Icon(
                        Icons.arrow_circle_right_outlined,
                        color: Theme.of(context).colorScheme.secondary,
                      )
                    ],
                  )),
            ],
          ],
        ),
      );
    }
    return const SizedBox.shrink();
  }
}
