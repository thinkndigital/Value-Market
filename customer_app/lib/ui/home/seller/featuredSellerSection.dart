import 'package:cached_network_image/cached_network_image.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';

import 'package:eshop_plus/ui/home/seller/allFeaturedSellerList.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/favoriteButton.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'featuredSellerCubit.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../utils/designConfig.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customTextContainer.dart';
import '../widgets/buildHeader.dart';

class FeaturedSellerSection extends StatefulWidget {
  const FeaturedSellerSection({Key? key}) : super(key: key);

  @override
  _FeaturedSellerSectionState createState() => _FeaturedSellerSectionState();
}

class _FeaturedSellerSectionState extends State<FeaturedSellerSection> {
  late Size size = MediaQuery.of(context).size;
  ScrollController _scrollController =
      ScrollController(initialScrollOffset: 0.8);
  @override
  Widget build(BuildContext context) {
    return BlocConsumer<FeaturedSellerCubit, FeaturedSellerState>(
      listener: (context, state) {
        if (state is FeaturedSellerFetchFailure) {}
      },
      builder: (context, state) {
        if (state is FeaturedSellerFetchSuccess) {
          return Container(
            color: Theme.of(context).colorScheme.primaryContainer,
            padding: const EdgeInsetsDirectional.symmetric(
                vertical: appContentHorizontalPadding),
            margin: const EdgeInsetsDirectional.only(
                bottom: appContentHorizontalPadding / 2),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                BuildHeader(
                    title: featuredSellersKey,
                    subtitle: featuredSellersDescKey,
                    showSeeAllButton:
                        state.topSellers.length > maxLimitOfWidgetsInHome,
                    onTap: () => Utils.navigateToScreen(
                        context, Routes.allFeaturedSellerList,
                        arguments: AllFeaturedSellerList.buildArguments(
                            title: featuredSellersKey,
                            sellers: state.topSellers.toList()))),
                DesignConfig.defaultHeightSizedBox,
                SizedBox(
                  height: context
                              .read<StoresCubit>()
                              .getDefaultStore()
                              .storeSettings!
                              .storeStyle ==
                          'header_style_1'
                      ? 90 + appContentHorizontalPadding
                      : 250,
                  child: ListView.separated(
                      controller: _scrollController,
                      separatorBuilder: (context, index) =>
                          DesignConfig.defaultWidthSizedBox,
                      padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: appContentHorizontalPadding),
                      clipBehavior: Clip.none,
                      scrollDirection: Axis.horizontal,
                      shrinkWrap: true,
                      itemCount:
                          state.topSellers.length > maxLimitOfWidgetsInHome
                              ? maxLimitOfWidgetsInHome
                              : state.topSellers.length,
                      itemBuilder: (context, index) {
                        if (context
                                .read<StoresCubit>()
                                .getDefaultStore()
                                .storeSettings!
                                .storeStyle ==
                            'header_style_1') {
                          return buildSellerCardForStyle1(
                              state.topSellers[index]);
                        } else if (context
                                .read<StoresCubit>()
                                .getDefaultStore()
                                .storeSettings!
                                .storeStyle ==
                            'header_style_2') {
                          return buildSellerCardForStyle2(
                              state.topSellers[index]);
                        } else {
                          return buildSellerCardForStyle3(
                              state.topSellers[index]);
                        }
                      }),
                ),
              ],
            ),
          );
        }
        return const Center(child: SizedBox.shrink());
      },
    );
  }

  buildSellerCardForStyle1(Seller seller) {
    return GestureDetector(
      onTap: () => onTapSellerCard(seller),
      child: Align(
        alignment: Alignment.bottomCenter,
        child: Container(
          width: size.width * 0.8,
          padding: const EdgeInsetsDirectional.all(8),
          decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primaryContainer,
              borderRadius: BorderRadius.circular(8),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x14212121),
                  blurRadius: 22,
                  offset: Offset(0, 2),
                  spreadRadius: 0,
                )
              ]),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              CustomImageWidget(
                  url: seller.storeLogo ?? '',
                  height: 90,
                  width: 90,
                  boxFit: BoxFit.cover,
                  borderRadius: 8),
              DesignConfig.smallWidthSizedBox,
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    buildStoreName(seller),
                    const SizedBox(
                      height: 4,
                    ),
                    buildStoreDescription(seller),
                    DesignConfig.smallHeightSizedBox,
                    buildProductCount(seller),
                  ],
                ),
              ),
              DesignConfig.smallWidthSizedBox,
              Column(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  buildRatingWidget(seller),
                  buildFavoriteButton(seller: seller, size: 40)
                ],
              )
            ],
          ),
        ),
      ),
    );
  }

  buildSellerCardForStyle2(Seller seller) {
    return GestureDetector(
      onTap: () => onTapSellerCard(seller),
      child: Padding(
        padding: const EdgeInsetsDirectional.all(8.0),
        child: CustomImageWidget(
            width: size.width * 0.8,
            url: seller.storeThumbnail ?? "",
            borderRadius: 16,
            child: Padding(
              padding: const EdgeInsets.all(8.0),
              child: Column(
                children: <Widget>[
                  Align(
                      alignment: Alignment.topRight,
                      child: buildFavoriteButton(
                        seller: seller,
                        iconColor: Theme.of(context).colorScheme.primary,
                        size: 40,
                      )),
                  const Spacer(),
                  Align(
                    alignment: Alignment.bottomCenter,
                    child: Container(
                      padding: const EdgeInsetsDirectional.all(8),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primaryContainer,
                        borderRadius: BorderRadius.circular(borderRadius),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          CustomImageWidget(
                              url: seller.storeLogo ?? '',
                              height: 88,
                              width: 88,
                              boxFit: BoxFit.cover,
                              borderRadius: 8),
                          DesignConfig.smallWidthSizedBox,
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                buildStoreName(seller),
                                const SizedBox(
                                  height: 4,
                                ),
                                buildStoreDescription(seller),
                                DesignConfig.defaultHeightSizedBox,
                                buildProductCount(seller),
                              ],
                            ),
                          ),
                          DesignConfig.smallWidthSizedBox,
                          buildRatingWidget(seller)
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            )),
      ),
    );
  }

  Widget buildSellerCardForStyle3(Seller seller) {
    return GestureDetector(
        onTap: () => onTapSellerCard(seller),
        child: Container(
            width: size.width * 0.8,
            height: 270,
            alignment: Alignment.center,
            child: CachedNetworkImage(
                imageUrl: seller.storeThumbnail!,
                fit: BoxFit.fitHeight,
                placeholder: (context, url) =>
                    DesignConfig.shimmerEffect(270, size.width * 0.8),
                imageBuilder: (context, imageProvider) => Container(
                    width: size.width * 0.8,
                    height: 270,
                    padding: const EdgeInsetsDirectional.all(16.0),
                    margin: const EdgeInsetsDirectional.only(bottom: 50),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(16),
                      image: DecorationImage(
                        fit: BoxFit.cover,
                        image: imageProvider,
                      ),
                    ),
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: <Widget>[
                        Align(
                            alignment: Alignment.topRight,
                            child: buildFavoriteButton(
                              seller: seller,
                              size: 40,
                              iconColor: Theme.of(context).colorScheme.primary,
                            )),
                        Positioned.directional(
                          textDirection: Directionality.of(context),
                          bottom: -60,
                          child: Container(
                            width: size.width * 0.8 -
                                (appContentHorizontalPadding * 2),
                            padding: const EdgeInsetsDirectional.all(
                                appContentHorizontalPadding),
                            decoration: BoxDecoration(
                              color: Theme.of(context)
                                  .colorScheme
                                  .primaryContainer,
                              borderRadius: BorderRadius.circular(borderRadius),
                              boxShadow: const [
                                BoxShadow(
                                  color: Color(0x0A201A1A),
                                  blurRadius: 16,
                                  offset: Offset(0, 4),
                                  spreadRadius: 0,
                                )
                              ],
                            ),
                            child: Stack(
                              clipBehavior: Clip.none,
                              children: [
                                Padding(
                                  padding:
                                      const EdgeInsetsDirectional.only(top: 30),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      buildStoreName(seller),
                                      const SizedBox(
                                        height: 4,
                                      ),
                                      buildStoreDescription(seller),
                                      DesignConfig.smallHeightSizedBox,
                                      buildProductCount(seller),
                                    ],
                                  ),
                                ),
                                Positioned.directional(
                                    textDirection: Directionality.of(context),
                                    top: -50,
                                    start: appContentHorizontalPadding,
                                    child: Container(
                                      decoration: BoxDecoration(
                                          shape: BoxShape.circle,
                                          color: Colors.transparent,
                                          border: Border.all(
                                              width: 2,
                                              color: Theme.of(context)
                                                  .colorScheme
                                                  .primary)),
                                      child: CustomImageWidget(
                                        url: seller.storeLogo!,
                                        borderRadius: 30,
                                        isCircularImage: true,
                                      ),
                                    )),
                                Positioned.directional(
                                    textDirection: Directionality.of(context),
                                    top: -8,
                                    end: 0,
                                    child: buildRatingWidget(seller)),
                              ],
                            ),
                          ),
                        ),
                      ],
                    )))));
  }

  Container buildRatingWidget(Seller seller) {
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(
          horizontal: appContentHorizontalPadding * (0.25),
          vertical: appContentHorizontalPadding * (0.25)),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary,
        borderRadius: BorderRadius.circular(5),
      ),
      child: Row(children: [
        Icon(Icons.star,
            size: 15.0, color: Theme.of(context).colorScheme.onPrimary),
        Padding(
          padding: const EdgeInsetsDirectional.symmetric(horizontal: 5),
          child: CustomTextContainer(
            textKey: seller.rating.toString(),
            style: Theme.of(context)
                .textTheme
                .labelMedium!
                .copyWith(color: Theme.of(context).colorScheme.onPrimary),
          ),
        ),
      ]),
    );
  }

  onTapSellerCard(Seller seller) {
    Utils.navigateToScreen(
      context,
      Routes.sellerDetailScreen,
      arguments: {
        'seller': seller,
      },
    );
  }

  Text buildProductCount(Seller seller) {
    return Text.rich(
      TextSpan(
        style: Theme.of(context).textTheme.labelMedium!,
        children: [
          TextSpan(
            text: context
                .read<SettingsAndLanguagesCubit>()
                .getTranslatedValue(labelKey: productsKey),
          ),
          const TextSpan(
            text: ' : ',
          ),
          TextSpan(text: seller.totalProducts.toString()),
        ],
      ),
      textAlign: TextAlign.center,
    );
  }

  Widget buildStoreDescription(Seller seller) {
    if (seller.storeDescription == null || seller.storeDescription!.isEmpty) {
      return const SizedBox();
    }
    return CustomTextContainer(
      textKey: seller.storeDescription ?? "",
      style: Theme.of(context).textTheme.bodySmall!.copyWith(
          overflow: TextOverflow.ellipsis,
          color:
              Theme.of(context).colorScheme.secondary.withValues(alpha: 0.67)),
      maxLines: 2,
    );
  }

  Widget buildStoreName(Seller seller) {
    return CustomTextContainer(
      textKey: seller.storeName ?? '',
      style: Theme.of(context).textTheme.titleSmall,
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }

  buildFavoriteButton(
      {required Seller seller, Color? iconColor, double? size}) {
    return FavoriteButton(
      unFavoriteColor: iconColor,
      size: size ?? 24,
      isSeller: true,
      sellerId: seller.sellerId!,
      seller: seller,
    );
  }
}
