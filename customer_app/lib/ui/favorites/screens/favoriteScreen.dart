import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';

import 'package:eshop_plus/ui/favorites/blocs/removeFavoriteCubit.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/ui/explore/widgets/ratingAndReviewCountContainer.dart';

import 'package:eshop_plus/core/theme/colors.dart';
import 'package:eshop_plus/commons/widgets/addToCartButton.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customTabbar.dart';

import 'package:eshop_plus/commons/widgets/customTextButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';
import 'package:eshop_plus/commons/widgets/favoriteButton.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

import '../../../commons/blocs/storesCubit.dart';

import '../../../../utils/utils.dart';
import '../blocs/getFavoriteCubit.dart';
import '../../explore/productDetails/productDetailsScreen.dart';

class FavoriteScreen extends StatefulWidget {
  const FavoriteScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => FavoriteScreen();
  @override
  _FavoriteScreenState createState() => _FavoriteScreenState();
}

class _FavoriteScreenState extends State<FavoriteScreen> {
  int _selectedTabIndex = 0;

  @override
  void initState() {
    super.initState();

    Future.delayed(Duration.zero, () {
      getFavorites();
    });
  }

  getFavorites() {
    context.read<FavoritesCubit>().getFavorites(
        storeId: context.read<StoresCubit>().getDefaultStore().id!,
        context: context);
  }

  void loadMoreProducts() {
    context.read<FavoritesCubit>().loadMoreProducts(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
        );
  }

  void loadMoreSellers() {
    context.read<FavoritesCubit>().loadMoreSellers(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
        );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(titleKey: wishlistKey),
      body: buildTabBar(),
    );
  }

  buildTabBar() {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: <Widget>[
          CustomTabbar(
            currentPage: _selectedTabIndex,
            textStyle: Theme.of(context).textTheme.bodyLarge,
            tabTitles: const [allProductsKey, allSellersKey],
            onTapTitle: (index) {
              _selectedTabIndex = index;
              setState(() {});
            },
          ),
          Expanded(
              child: _selectedTabIndex == 0
                  ? buildProductsList()
                  : buildSellersList())
        ],
      ),
    );
  }

  Tab buildTabLabel(String title) {
    return Tab(
      text: context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: title),
    );
  }

  Widget buildProductsList() {
    return BlocBuilder<FavoritesCubit, FavoritesState>(
      builder: (context, state) {
        if (state is FavoritesFetchSuccess) {
          if (state.products.isEmpty) {
            return getErrorScreen(state, noFavoritesKey);
          }
          return BlocListener<RemoveFavoriteCubit, RemoveFavoriteState>(
            listener: (context, removeState) {
              if (removeState is RemoveFavoriteSuccess) {
                state.products
                    .removeWhere((element) => element.id == removeState.id);
                context.read<FavoritesCubit>().emitProductSuccessState(
                    state.products, state.totalProducts);
              }

              if (removeState is RemoveFavoriteFailure) {
                state.products
                    .firstWhere((element) => element.id == removeState.id)
                    .removeFavoriteInProgress = false;
              }
            },
            child: NotificationListener<ScrollUpdateNotification>(
              onNotification: (notification) {
                if (notification.metrics.pixels >=
                    notification.metrics.maxScrollExtent) {
                  if (context.read<FavoritesCubit>().hasMoreProducts() &&
                      !(context.read<UserDetailsCubit>().isGuestUser())) {
                    loadMoreProducts();
                  }
                }
                return true;
              },
              child: ListView.separated(
                separatorBuilder: (context, index) =>
                    DesignConfig.smallHeightSizedBox,
                padding: const EdgeInsets.symmetric(
                    horizontal: appContentHorizontalPadding, vertical: 12),
                itemCount: state.products.length,
                shrinkWrap: true,
                itemBuilder: (context, index) {
                  final product = state.products[index];
                  if (context.read<FavoritesCubit>().hasMoreProducts()) {
                    if (index == state.products.length - 1) {
                      if (context
                          .read<FavoritesCubit>()
                          .fetchMoreProductsError()) {
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
                    onTap: () {
                      Get.toNamed(Routes.productDetailsScreen,
                          arguments: product.type == comboProductType
                              ? ProductDetailsScreen.buildArguments(
                                  product: product, isComboProduct: true)
                              : ProductDetailsScreen.buildArguments(
                                  product: product,
                                ));
                    },
                    child: Container(
                        padding: const EdgeInsetsDirectional.all(8),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.primaryContainer,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        width: MediaQuery.of(context).size.width,
                        child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              CustomImageWidget(
                                  url: (product.image ?? "").isNotEmpty
                                      ? (product.image ?? "")
                                      : product.imageMd!,
                                  width: 86,
                                  height: 100,
                                  borderRadius: 8),
                              const SizedBox(
                                width: 10.0,
                              ),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
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
                                        buildRemoveProductButton(
                                            product, state.products),
                                      ],
                                    ),
                                    Padding(
                                      padding:
                                          const EdgeInsetsDirectional.symmetric(
                                              vertical: 8.0),
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
                                    Wrap(
                                      spacing: 8,
                                      runSpacing: 2,
                                      children: [
                                        product.hasSpecialPrice()
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
                                        product.isProductOutOfStock()
                                            ? Padding(
                                                padding:
                                                    const EdgeInsetsDirectional
                                                        .only(top: 8),
                                                child: CustomTextContainer(
                                                    textKey: outOfStockKey,
                                                    style: Theme.of(context)
                                                        .textTheme
                                                        .labelSmall!
                                                        .copyWith(
                                                            color:
                                                                cancelledStatusColor)),
                                              )
                                            : AddToCartButton(
                                                storeId: product.storeId!,
                                                productId: product.type ==
                                                        comboProductType
                                                    ? product.id!
                                                    : product
                                                        .selectedVariant!.id!,
                                                type: product.type ==
                                                        comboProductType
                                                    ? comboType
                                                    : regularType,
                                                stockType: product.stockType!,
                                                stock: product.type ==
                                                        variableProductType
                                                    ? product
                                                        .selectedVariant!.stock!
                                                    : product.stock!,
                                                sellerId: product.sellerId!,
                                                productType:
                                                    product.productType!,
                                                qty: product
                                                    .minimumOrderQuantity),
                                      ],
                                    ),
                                  ],
                                ),
                              )
                            ])),
                  );
                },
              ),
            ),
          );
        }
        if (state is FavoritesFetchFailure) {
          return getErrorScreen(state, state.errorMessage);
        }
        return Center(
          child: CustomCircularProgressIndicator(
              indicatorColor: Theme.of(context).colorScheme.primary),
        );
      },
    );
  }

  Widget buildSellersList() {
    return BlocBuilder<FavoritesCubit, FavoritesState>(
      builder: (context, state) {
        if (state is FavoritesFetchSuccess) {
          if (state.sellers.isEmpty) {
            return getErrorScreen(state, noFavoritesKey);
          }
          return BlocListener<RemoveFavoriteCubit, RemoveFavoriteState>(
            listener: (context, removeState) {
              if (removeState is RemoveFavoriteSuccess) {
                state.sellers.removeWhere(
                    (element) => element.sellerId == removeState.id);
                context
                    .read<FavoritesCubit>()
                    .emitSelletSuccessState(state.sellers, state.totalSellers);

                Utils.showSnackBar(
                    context: context, message: removeState.successMessage);
              }

              if (removeState is RemoveFavoriteFailure) {
                state.products
                    .firstWhere((element) => element.id == removeState.id)
                    .removeFavoriteInProgress = false;
                Utils.showSnackBar(
                    context: context, message: removeState.errorMessage);
              }
            },
            child: NotificationListener<ScrollUpdateNotification>(
              onNotification: (notification) {
                if (notification.metrics.pixels >=
                    notification.metrics.maxScrollExtent) {
                  if (context.read<FavoritesCubit>().hasMoreSellers()) {
                    loadMoreSellers();
                  }
                }
                return true;
              },
              child: ListView.separated(
                separatorBuilder: (context, index) =>
                    DesignConfig.smallHeightSizedBox,
                shrinkWrap: true,
                padding: const EdgeInsets.all(appContentHorizontalPadding),
                itemCount: state.sellers.length,
                itemBuilder: (context, index) {
                  final seller = state.sellers[index];
                  if (context.read<FavoritesCubit>().hasMoreSellers()) {
                    if (index == state.sellers.length - 1) {
                      if (context
                          .read<FavoritesCubit>()
                          .fetchMoreSellersError()) {
                        return Center(
                          child: CustomTextButton(
                              buttonTextKey: retryKey,
                              onTapButton: () {
                                loadMoreSellers();
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
                    onTap: () => Utils.navigateToScreen(
                      context,
                      Routes.sellerDetailScreen,
                      arguments: {
                        'seller': seller,
                      },
                    ),
                    child: Container(
                        padding: const EdgeInsetsDirectional.all(8),
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.primaryContainer,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        width: MediaQuery.of(context).size.width,
                        child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              CustomImageWidget(
                                  url: seller.storeLogo!,
                                  width: 80,
                                  height: 80,
                                  borderRadius: 8),
                              const SizedBox(
                                width: 10.0,
                              ),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Expanded(
                                          child: CustomTextContainer(
                                            textKey: seller.storeName ?? '',
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: Theme.of(context)
                                                .textTheme
                                                .titleSmall,
                                          ),
                                        ),
                                        RatingAndReviewCountContainer(
                                          rating: seller.rating.toString(),
                                          ratingCount: '',
                                          textColor: Theme.of(context)
                                              .colorScheme
                                              .primary,
                                        )
                                      ],
                                    ),
                                    Padding(
                                      padding:
                                          const EdgeInsetsDirectional.symmetric(
                                              vertical: 8.0),
                                      child: CustomTextContainer(
                                        textKey: seller.storeDescription ?? "",
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
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        CustomTextContainer(
                                            textKey:
                                                '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: productsKey)} : ${seller.totalProducts}',
                                            style: Theme.of(context)
                                                .textTheme
                                                .labelMedium!
                                                .copyWith(
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .secondary
                                                        .withValues(
                                                            alpha: 0.8))),
                                        if (context
                                            .read<UserDetailsCubit>()
                                            .isGuestUser())
                                          buildRemoveSellerButton(
                                              seller, state.sellers)
                                        else
                                          FavoriteButton(
                                            sellerId: seller.sellerId,
                                            seller: seller,
                                            isSeller: true,
                                            size: 30,
                                            favoriteColor: Theme.of(context)
                                                .colorScheme
                                                .error,
                                          )
                                      ],
                                    ),
                                  ],
                                ),
                              )
                            ])),
                  );
                },
              ),
            ),
          );
        }
        if (state is FavoritesFetchFailure) {
          return getErrorScreen(state, state.errorMessage);
        }
        return Center(
          child: CustomCircularProgressIndicator(
              indicatorColor: Theme.of(context).colorScheme.primary),
        );
      },
    );
  }

  getErrorScreen(FavoritesState state, String title) {
    return ErrorScreen(
        onPressed: getFavorites,
        image: "no_data_found",
        text: title,
        child: state is FavoritesFetchInProgress
            ? CustomCircularProgressIndicator(
                indicatorColor: Theme.of(context).colorScheme.primary)
            : null);
  }

  buildRemoveProductButton(Product product, List<Product> products) {
    return BlocBuilder<RemoveFavoriteCubit, RemoveFavoriteState>(
      builder: (context, state) {
        return GestureDetector(
          child: state is RemoveFavoriteInProgress &&
                  state.products!.isNotEmpty &&
                  state.products!
                          .firstWhere((element) => element.id == product.id)
                          .removeFavoriteInProgress ==
                      true
              ? CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.secondary)
              : Icon(
                  Icons.close_outlined,
                  size: 24,
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.67),
                ),
          onTap: () {
            if (state is RemoveFavoriteInProgress &&
                state.products!
                        .firstWhere((element) => element.id == product.id)
                        .removeFavoriteInProgress ==
                    true) return;
            context.read<RemoveFavoriteCubit>().removeFavorite(
                context: context,
                params: {
                  ApiURL.isSellerApiKey: 0,
                  ApiURL.productIdApiKey: product.id,
                  ApiURL.productTypeApiKey: product.type == comboProductType
                      ? comboType
                      : regularType,
                },
                products: products);
          },
        );
      },
    );
  }

  buildRemoveSellerButton(Seller seller, List<Seller> sellers) {
    return BlocBuilder<RemoveFavoriteCubit, RemoveFavoriteState>(
      builder: (context, state) {
        return GestureDetector(
          child: state is RemoveFavoriteInProgress &&
                  state.sellers!
                          .firstWhere(
                              (element) => element.sellerId == seller.sellerId)
                          .removeFavoriteInProgress ==
                      true
              ? CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.secondary)
              : Icon(
                  Icons.close_outlined,
                  size: 24,
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.67),
                ),
          onTap: () {
            if (state is RemoveFavoriteInProgress &&
                state.sellers!
                        .firstWhere(
                            (element) => element.sellerId == seller.sellerId)
                        .removeFavoriteInProgress ==
                    true) return;
            context.read<RemoveFavoriteCubit>().removeFavorite(
                context: context,
                params: {
                  ApiURL.isSellerApiKey: 1,
                  ApiURL.sellerIdApiKey: seller.sellerId,
                },
                sellers: sellers);
          },
        );
      },
    );
  }
}
