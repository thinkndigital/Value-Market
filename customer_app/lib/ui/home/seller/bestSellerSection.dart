import 'package:eshop_plus/commons/product/models/filterAttribute.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/commons/product/blocs/productsCubit.dart';
import 'package:eshop_plus/ui/home/seller/bestSellerCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/ui/explore/screens/exploreScreen.dart';
import 'package:eshop_plus/ui/home/seller/allFeaturedSellerList.dart';
import 'package:eshop_plus/ui/home/widgets/buildHeader.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../commons/product/models/product.dart';
import '../../../commons/seller/models/seller.dart';

import '../../../utils/designConfig.dart';

class BestSellerSection extends StatefulWidget {
  BestSellerSection({Key? key}) : super(key: key);

  @override
  State<BestSellerSection> createState() => _BestSellerSectionState();
}

class _BestSellerSectionState extends State<BestSellerSection> {
  List<Product> products = [];

  @override
  void initState() {
    super.initState();
    Future.microtask(() async {
      callProducts();
    });
  }

  callProducts() async {
    final bestSellersCubit = context.read<BestSellersCubit>();
    final productsCubit = context.read<ProductsCubit>();
    final defaultStoreId = context.read<StoresCubit>().getDefaultStore().id!;

    if (bestSellersCubit.state is BestSellersFetchSuccess) {
      final sellers =
          (bestSellersCubit.state as BestSellersFetchSuccess).sellers;

      for (final seller in sellers) {
        List<Product> combinedProducts = [];
        int total = 0;
        double minPrice = 0;
        double maxPrice = 0;
        List<FilterAttribute> filterAttributes = [];
        String? categoryIds = '';
        String? brandIds = '';
        bool gotAny = false;
        try {
          final regularResult = await ProductRepository().getProducts(
            storeId: defaultStoreId,
            sellerId: seller.sellerId,
          );
          combinedProducts.addAll(regularResult.products);
          total += regularResult.total;
          minPrice = regularResult.minPrice;
          maxPrice = regularResult.maxPrice;
          filterAttributes = regularResult.filterAttributes;
          categoryIds = regularResult.categoryIds;
          brandIds = regularResult.brandIds;
          gotAny = true;
        } catch (_) {}
        try {
          final comboResult = await ProductRepository().getProducts(
            storeId: defaultStoreId,
            sellerId: seller.sellerId,
            isComboProduct: true,
          );
          combinedProducts.addAll(comboResult.products);
          total += comboResult.total;
          // Optionally update min/max/filter/category/brand if needed
          gotAny = true;
        } catch (_) {}

        if (gotAny) {
          productsCubit.setProductsForSeller(
            seller.sellerId!,
            ProductsFetchSuccess(
              sellerId: seller.sellerId,
              products: combinedProducts,
              total: total,
              fetchMoreError: false,
              fetchMoreInProgress: false,
              minPrice: minPrice,
              maxPrice: maxPrice,
              filterAttributes: filterAttributes,
              categoryIds: categoryIds,
              brandIds: brandIds,
            ),
          );
        } else {
          (context.read<BestSellersCubit>().state as BestSellersFetchSuccess)
              .sellers
              .removeWhere((e) => e.sellerId == seller.sellerId);
          context.read<BestSellersCubit>().emitSuccessState(sellers);
        }
      }
    }
  }

  Widget build(BuildContext context) {
    return BlocBuilder<BestSellersCubit, BestSellersState>(
      builder: (context, state) {
        if (state is BestSellersFetchSuccess) {
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
                    title: bestSellersTitleKey,
                    subtitle: bestSellersDescKey,
                    showSeeAllButton:
                        state.sellers.length > maxLimitOfBestSellersInHome,
                    onTap: () => Utils.navigateToScreen(
                        context, Routes.allFeaturedSellerList,
                        arguments: AllFeaturedSellerList.buildArguments(
                            title: bestSellersTitleKey,
                            sellers: state.sellers.toList()))),
                DesignConfig.defaultHeightSizedBox,
                SizedBox(
                  height: 360,
                  child: ListView.separated(
                      separatorBuilder: (context, index) =>
                          DesignConfig.defaultWidthSizedBox,
                      padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: appContentHorizontalPadding),
                      clipBehavior: Clip.none,
                      scrollDirection: Axis.horizontal,
                      shrinkWrap: true,
                      itemCount:
                          state.sellers.length > maxLimitOfBestSellersInHome
                              ? maxLimitOfBestSellersInHome
                              : state.sellers.length,
                      itemBuilder: (context, index) {
                        final seller = state.sellers[index];
                        return BlocBuilder<ProductsCubit, ProductsState>(
                          buildWhen: (previous, current) {
                            // Only rebuild if the relevant seller's state changes
                            if (current is ProductsMultiSellerState) {
                              return (current.sellerStates[seller.sellerId] !=
                                  (previous is ProductsMultiSellerState
                                      ? previous.sellerStates[seller.sellerId]
                                      : null));
                            }
                            return false;
                          },
                          builder: (context, productstate) {
                            final productsCubit = context.read<ProductsCubit>();
                            final sellerState =
                                productsCubit.getSellerState(seller.sellerId!);
                            if (sellerState is ProductsFetchSuccess) {
                              final products = sellerState.products;
                              final totalProducts = sellerState.total;
                              if (totalProducts > 0) {
                                return SellerListItem(
                                  seller: seller,
                                  products: products,
                                  totalProducts: totalProducts,
                                  isLoading: false,
                                );
                              }
                              return const SizedBox.shrink();
                            }
                            if (sellerState is ProductsFetchInProgress) {
                              return CustomCircularProgressIndicator(
                                indicatorColor:
                                    Theme.of(context).colorScheme.primary,
                              );
                            }
                            if (sellerState is ProductsFetchFailure) {
                              return ErrorScreen(
                                  text: sellerState.errorMessage,
                                  onPressed: () {
                                    callProducts();
                                  });
                            }
                            return const SizedBox.shrink();
                          },
                        );
                      }),
                ),
              ],
            ),
          );
        }
        return const SizedBox.shrink();
      },
    );
  }
}

class SellerListItem extends StatelessWidget {
  final Seller seller;
  final List<Product> products;
  final bool isLoading;
  final String? error;
  final int totalProducts;

  const SellerListItem(
      {required this.seller,
      required this.products,
      this.isLoading = false,
      this.error,
      required this.totalProducts});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => totalProducts > 0
          ? Utils.navigateToScreen(
              context,
              Routes.sellerDetailScreen,
              arguments: {
                'seller': seller,
              },
            )
          : null,
      child: Container(
          width: MediaQuery.of(context).size.width * 0.7,
          padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
          decoration: BoxDecoration(
              color: Theme.of(context).scaffoldBackgroundColor,
              borderRadius: BorderRadius.circular(8)),
          child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  mainAxisAlignment: MainAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CustomImageWidget(
                      url: seller.storeThumbnail ?? '',
                      borderRadius: 8,
                      width: 70,
                      height: 70,
                    ),
                    const SizedBox(
                      width: 12,
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CustomTextContainer(
                            textKey: seller.storeName ?? '',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleSmall,
                          ),
                          Text.rich(
                            TextSpan(
                              style: Theme.of(context).textTheme.labelMedium!,
                              children: [
                                TextSpan(
                                  text: context
                                      .read<SettingsAndLanguagesCubit>()
                                      .getTranslatedValue(
                                          labelKey: productsKey),
                                ),
                                const TextSpan(
                                  text: ' : ',
                                ),
                                TextSpan(text: totalProducts.toString()),
                              ],
                            ),
                            textAlign: TextAlign.center,
                          )
                        ],
                      ),
                    ),
                  ],
                ),
                DesignConfig.defaultHeightSizedBox,
                LayoutBuilder(builder: (context, boxConstraints) {
                  return Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: List.generate(
                          totalProducts > 4 ? 4 : totalProducts, (index) {
                        final product = products[index];
                        if (isLoading) {
                          return const Center(
                              child: CircularProgressIndicator());
                        } else if (error != null)
                          return CustomTextContainer(
                            textKey: error!,
                            style: const TextStyle(color: Colors.red),
                          );
                        else if (products.isNotEmpty)
                          return GestureDetector(
                            onTap: () => Utils.navigateToScreen(
                                context, Routes.exploreScreen,
                                arguments: ExploreScreen.buildArguments(
                                    title: bestSellersTitleKey,
                                    sellerId: seller.sellerId,
                                    sellerProductScreen: true)),
                            child: Container(
                                width: (MediaQuery.of(context).size.width *
                                        0.7 /
                                        2) -
                                    (appContentHorizontalPadding * 2),
                                height: (MediaQuery.of(context).size.width *
                                        0.7 /
                                        2) -
                                    (appContentHorizontalPadding * 2),
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: index == 3 && totalProducts > 3
                                      ? Colors.black.withValues(alpha: 0.5)
                                      : Colors.transparent,
                                  borderRadius: BorderRadius.circular(8),
                                  image: DecorationImage(
                                    image: NetworkImage(product.image ?? ''),
                                    fit: BoxFit.cover,
                                    colorFilter: index == 3 && totalProducts > 3
                                        ? ColorFilter.mode(
                                            Colors.black.withValues(alpha: 0.5),
                                            BlendMode.srcATop)
                                        : null,
                                  ),
                                ),
                                child: index == 3 && totalProducts > 3
                                    ? CustomTextContainer(
                                        textKey: '+${totalProducts - 4}',
                                        style: Theme.of(context)
                                            .textTheme
                                            .headlineSmall!
                                            .copyWith(color: Colors.white),
                                      )
                                    : null),
                          );

                        return const Center(
                            child: CustomTextContainer(
                                textKey: 'No products available'));
                      }));
                })
              ])),
    );
  }
}
