import 'package:value_market_customer/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/profile/address/blocs/cityCubit.dart';
import 'package:value_market_customer/ui/profile/address/blocs/zipcodeCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/getUserCart.dart';
import 'package:value_market_customer/ui/cart/cubits/manageCartCubit.dart';
import 'package:value_market_customer/ui/profile/faq/blocs/faqCubit.dart';
import 'package:value_market_customer/ui/explore/cubits/checkProductDeliverabilityCubit.dart';
import 'package:value_market_customer/ui/explore/cubits/getProductRatingCubit.dart';

import 'package:value_market_customer/commons/product/blocs/productsCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/ui/cart/models/cart.dart';

import 'package:value_market_customer/commons/product/models/product.dart';

import 'package:value_market_customer/ui/explore/productDetails/widgets/checkDeliverableContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/compareWithSimilarItemsContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/offerContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/productDetailsContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/productFaqContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/productInfoContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/ratingContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/sellerDetailContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/similarProductContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/variantContainer.dart';
import 'package:value_market_customer/ui/explore/productDetails/widgets/variantSelector.dart';
import 'package:value_market_customer/commons/widgets/addToCartButton.dart';

import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customBottomButtonContainer.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/commons/widgets/customRoundedButton.dart';
import 'package:value_market_customer/commons/widgets/customTextButton.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/commons/widgets/error_screen.dart';
import 'package:value_market_customer/commons/widgets/favoriteButton.dart';

import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/constants/hiveConstants.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:hive/hive.dart';
import 'package:share_plus/share_plus.dart';

class ProductDetailsScreen extends StatefulWidget {
  final Product product;
  final List<int>? productIds;
  final bool? isComboProduct;
  final int? storeId;
  const ProductDetailsScreen(
      {super.key,
      required this.product,
      required this.productIds,
      this.isComboProduct,
      this.storeId});

  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => ProductsCubit(),
        ),
        BlocProvider(
          create: (context) => ProductRatingCubit(),
        ),
        BlocProvider(
          create: (context) => FAQCubit(),
        ),
      ],
      child: ProductDetailsScreen(
        product: arguments['product'] as Product,
        productIds: arguments['productIds'] as List<int>?,
        isComboProduct: arguments['isComboProduct'] ?? false,
        storeId: arguments['storeId'] as int?,
      ),
    );
  }

  static Map<String, dynamic> buildArguments(
      {required Product product,
      List<int>? productIds,
      bool? isComboProduct,
      int? storeId}) {
    return {
      'product': product,
      'productIds': productIds,
      'isComboProduct': isComboProduct,
      'storeId': storeId
    };
  }

  @override
  State<ProductDetailsScreen> createState() => _ProductDetailsScreenState();
}

class _ProductDetailsScreenState extends State<ProductDetailsScreen> {
  late Product product;

  List<Product> comboProducts = [];
  CartProduct? cartProduct;
  final GlobalKey<RatingContainerState> _ratingkey =
      GlobalKey<RatingContainerState>();

  GlobalKey _infoKey = GlobalKey(),
      _detailsKey = GlobalKey(),
      _sellerKey = GlobalKey(),
      _faqKey = GlobalKey(),
      _similarKey = GlobalKey();
  List<String> productVariantIds = [];
  @override
  void initState() {
    super.initState();
    product = widget.product;

    Future.delayed(Duration.zero, () {
      if (widget.productIds != null) {
        context.read<ProductsCubit>().getProducts(
            storeId: widget.storeId ??
                context.read<StoresCubit>().getDefaultStore().id!,
            isComboProduct: widget.isComboProduct ?? false,
            productIds: widget.productIds!);
      } else if (product.type == comboProductType) {
        productVariantIds =
            widget.product.productVariantIds!.split(',').toList();
        setVariantOfCombo();
      }
    });

    addProductId(product.id!);
  }

  void addProductId(int productId) {
    if (!Hive.box(productsBoxKey).containsKey(productId)) {
      if (Hive.box(productsBoxKey).values.length >= 5) {
        Hive.box(productsBoxKey).delete(Hive.box(productsBoxKey).values.first);
      }

      Hive.box(productsBoxKey).put(productId, productId);
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<StoresCubit, StoresState>(
      builder: (context, storestate) {
        return BlocBuilder<SettingsAndLanguagesCubit,
            SettingsAndLanguagesState>(
          builder: (context, state) {
            return OrientationBuilder(builder: (context, orientation) {
              // if (state is SettingsAndLanguagesFetchSuccess &&
              //     storestate is StoresFetchSuccess) {
              return BlocConsumer<ProductsCubit, ProductsState>(
                  listener: (context, state) {
                if (state is ProductsFetchSuccess) {
                  if (state.products.isNotEmpty) {
                    // updateCartProducts(state.products);

                    //here we will assign the first
                    // product to the product variable only if we are
                    //not passing the product model in arguments
                    if (state.products[0].id == widget.productIds![0]) {
                      setState(() {
                        product = state.products[0];
                        if (product.type == comboProductType) {
                          productVariantIds =
                              product.productVariantIds!.split(',').toList();
                          setVariantOfCombo();
                        }
                      });
                    }
                  }
                }
              }, builder: (context, state) {
                if (widget.productIds == null ||
                    state is ProductsFetchSuccess) {
                  return SafeAreaWithBottomPadding(
                    child: Scaffold(
                        appBar: buildAppBar(),
                        bottomNavigationBar:
                            BlocBuilder<GetUserCartCubit, GetUserCartState>(
                          builder: (context, state) {
                            if (state is GetUserCartFetchSuccess &&
                                state.cart.cartProducts != null &&
                                product.type == variableProductType &&
                                product.selectedVariant != null) {
                              cartProduct = state.cart.cartProducts!
                                  .firstWhereOrNull((element) =>
                                      element.productVariantId ==
                                      product.selectedVariant!.id);
                            }
                            return BlocBuilder<ManageCartCubit,
                                ManageCartState>(
                              builder: (context, state) {
                                if (widget.productIds == null ||
                                    context.read<ProductsCubit>().state
                                        is ProductsFetchSuccess) {
                                  return buildBottomBar();
                                }
                                return const SizedBox.shrink();
                              },
                            );
                          },
                        ),
                        body: NotificationListener<ScrollNotification>(
                          onNotification: (ScrollNotification scrollInfo) {
                            if (scrollInfo.metrics.pixels > 100) {
                              // Trigger the animation when user scrolls past 100 pixels
                              _ratingkey.currentState?.onScroll();
                            }
                            return true;
                          },
                          child: SingleChildScrollView(
                            padding:
                                const EdgeInsetsDirectional.only(bottom: 12),
                            child: Column(
                              children: [
                                ProductInfoContainer(
                                  product: product,
                                  key: _infoKey,
                                  isFullScreen:
                                      orientation == Orientation.landscape,
                                ),
                                DesignConfig.smallHeightSizedBox,
                                if (product.type != comboProductType &&
                                    product.productType ==
                                        variableProductType) ...[
                                  if (context
                                          .read<StoresCubit>()
                                          .getDefaultStore()
                                          .storeSettings!
                                          .productStyle ==
                                      'style_1')
                                    VariantContainer(
                                      product: product,
                                      onVariantSelected: () {
                                        setState(() {
                                          _infoKey = GlobalKey();
                                        });
                                      },
                                    )
                                  else
                                    VariantSelector(
                                      variants: product.variants ?? [],
                                      product: product,
                                      onVariantSelected: () {
                                        setState(() {
                                          _infoKey = GlobalKey();
                                        });
                                      },
                                    ),
                                ],
                                if (product.type == comboProductType) ...[
                                  comboProductsContainer(
                                      product.productDetails!),
                                  DesignConfig.smallHeightSizedBox,
                                ],
                                ProductDetailsContainer(
                                  product: product,
                                  key: _detailsKey,
                                ),
                                DesignConfig.smallHeightSizedBox,
                                const OfferContainer(
                                    title: allOffersAndCouponsKey),
                                compareWithSimilarItemsContainer(
                                    product: product),
                                if (product.productType !=
                                    digitalProductType) ...[
                                  DesignConfig.smallHeightSizedBox,
                                  MultiBlocProvider(
                                    providers: [
                                      BlocProvider(
                                        create: (context) => CityCubit(),
                                      ),
                                      BlocProvider(
                                        create: (context) => ZipcodeCubit(),
                                      ),
                                      BlocProvider(
                                        create: (context) =>
                                            CheckProductDeliverabilityCubit(),
                                      ),
                                    ],
                                    child: CheckDeliverableContainer(
                                        product: product),
                                  ),
                                ],
                                DesignConfig.smallHeightSizedBox,
                                SellerDetailContainer(
                                  product: product,
                                  key: _sellerKey,
                                ),
                                RatingContainer(
                                  product: product,
                                  isComboProduct:
                                      product.type == comboProductType
                                          ? true
                                          : false,
                                  key: _ratingkey,
                                  isFullScreen: false,
                                ),
                                ProductFaqContainer(
                                  product: product,
                                  key: _faqKey,
                                ),
                                BlocProvider(
                                  create: (context) => ProductsCubit(),
                                  child: SimilarProductContainer(
                                    product: product,
                                    key: _similarKey,
                                  ),
                                )
                              ],
                            ),
                          ),
                        )),
                  );
                }
                if (state is ProductsFetchFailure) {
                  return ErrorScreen(
                      text: state.errorMessage,
                      onPressed: () {
                        if (widget.productIds != null) {
                          context.read<ProductsCubit>().getProducts(
                              storeId: context
                                  .read<StoresCubit>()
                                  .getDefaultStore()
                                  .id!,
                              productIds: widget.productIds!);
                        }
                      });
                }
                return Scaffold(
                  body: CustomCircularProgressIndicator(
                      indicatorColor: Theme.of(context).colorScheme.primary),
                );
              });
              // } else {
              //   return Scaffold(
              //     body: CustomCircularProgressIndicator(
              //         indicatorColor: Theme.of(context).colorScheme.primary),
              //   );
              // }
            });
          },
        );
      },
    );
  }

  CustomAppbar buildAppBar() {
    return CustomAppbar(
      titleKey: "",
      trailingWidget: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Utils.searchIcon(context),
          FavoriteButton(
            product: product,
            size: 40,
          ),
          Utils.cartIcon(context),
          IconButton(
            icon: Icon(Icons.share,
                color: Theme.of(context).colorScheme.secondary),
            onPressed: () {
              _shareProduct(context);
            },
          ),
        ],
      ),
    );
  }

  void _shareProduct(BuildContext context) {
    final String productUrl =
        "$baseUrl/product/${product.id}/${product.storeId}/${product.type ?? 'regular'}";
    final String productName = product.name ?? '';
    SharePlus.instance.share(ShareParams(
      text:
          "${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: checkOutProductKey)} : $productName\n$productUrl",
      subject:
          "${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: productNameKey)}: $productName",
    ));
  }

  comboProductsContainer(List<Product> comboProducts) {
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomTextContainer(
            textKey:
                '${comboProducts.length} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: itemsInThisComboKey)}',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          DesignConfig.defaultHeightSizedBox,
          Column(
            spacing: appContentHorizontalPadding,
            children: List.generate(comboProducts.length, (index) {
              return buildComboProduct(comboProducts[index]);
            }),
          )
        ],
      ),
    );
  }

  buildBottomBar() {
    if ((product.type == comboProductType && product.isProductOutOfStock()) ||
        (product.type == variableProductType &&
            context
                    .read<StoresCubit>()
                    .getDefaultStore()
                    .storeSettings!
                    .productStyle ==
                'style_1' &&
            product.isVariantOutOfStock(
                product.selectedVariant ?? product.variants![0])) ||
        product.isProductOutOfStock()) {
      return CustomBottomButtonContainer(
        bottomPadding: 8,
        child: const CustomRoundedButton(
            widthPercentage: 1.0,
            buttonTitle: outOfStockKey,
            showBorder: false),
      );
    }

    return product.type == variableProductType &&
            context
                    .read<StoresCubit>()
                    .getDefaultStore()
                    .storeSettings!
                    .productStyle !=
                'style_1'
        ? isProductAddedinCart()
            ? CustomBottomButtonContainer(
                bottomPadding: 8,
                child: Container(
                  height: 50,
                  padding: const EdgeInsetsDirectional.all(12),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primary,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      CustomTextContainer(
                          textKey:
                              '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: itemTotalKey)} : ${Utils.priceWithCurrencySymbol(price: context.read<GetUserCartCubit>().calculateItemTotalForProduct(context.read<GetUserCartCubit>().getCartDetail(), widget.product.id!), context: context)}',
                          style: Theme.of(context)
                              .textTheme
                              .labelLarge!
                              .copyWith(
                                  color:
                                      Theme.of(context).colorScheme.onPrimary)),
                      CustomTextButton(
                          buttonTextKey: confirmKey,
                          onTapButton: () => Utils.navigateToScreen(
                                context,
                                Routes.cartScreen,
                              ),
                          textStyle: Theme.of(context)
                              .textTheme
                              .labelLarge!
                              .copyWith(
                                  color:
                                      Theme.of(context).colorScheme.onPrimary))
                    ],
                  ),
                ))
            : const SizedBox.shrink()
        : CustomBottomButtonContainer(
            bottomPadding: 8,
            child: Row(
              children: [
                Expanded(
                  child: AddToCartButton(
                      storeId: product.storeId!,
                      widthPercentage: isProductAddedinCart() ? 1.0 : 0.4,
                      height: 40,
                      showButtonBorder: !isProductAddedinCart(),
                      isBuyNowButton: false,
                      productId: product.type == comboProductType
                          ? product.id!
                          : product.selectedVariant!.id!,
                      type: product.type == comboProductType
                          ? comboType
                          : regularType,
                      productType: product.productType!,
                      sellerId: product.sellerId!,
                      stock: product.type == variableProductType
                          ? product.selectedVariant!.stock!
                          : product.stock!,
                      stockType: product.type == comboProductType
                          ? product.stockType!
                          : product.stockType!,
                      qty: product.type == comboProductType
                          ? product.minimumOrderQuantity
                          : product.minimumOrderQuantity),
                ),
                if (!isProductAddedinCart()) ...[
                  const SizedBox(
                    width: 16,
                  ),
                  Expanded(
                    child: AddToCartButton(
                        storeId: product.storeId!,
                        isBuyNowButton: true,
                        title: buyNowKey,
                        widthPercentage: 0.4,
                        height: 40,
                        sellerId: product.sellerId!,
                        productId: product.type == comboProductType
                            ? product.id!
                            : product.selectedVariant!.id!,
                        type: product.type == comboProductType
                            ? comboType
                            : regularType,
                        stockType: product.stockType!,
                        stock: product.type == variableProductType
                            ? product.selectedVariant!.stock!
                            : product.stock!,
                        productType: product.productType!,
                        qty: product.minimumOrderQuantity),
                  )
                ]
              ],
            ),
          );
  }

  isProductAddedinCart() {
    if (context
                .read<StoresCubit>()
                .getDefaultStore()
                .storeSettings!
                .productStyle ==
            'style_1' &&
        cartProduct != null) {
      return true;
    }
    return context.read<GetUserCartCubit>().getCartDetail().cartProducts !=
            null &&
        context
                .read<GetUserCartCubit>()
                .getCartDetail()
                .cartProducts!
                .indexWhere((element) =>
                    element.productDetails![0].id == widget.product.id) !=
            -1;
  }

  void setVariantOfCombo() {
    for (String productId in product.productIds!.split(',').toList()) {
      Product? prdct = product.productDetails!
          .firstWhereOrNull((p) => p.id.toString() == productId);
      if (prdct != null) {
        // Create a deep copy of the product to avoid reference issues

        // You might also handle different variants manually based on your product structure

        setState(() {
          if (prdct.type == variableProductType &&
              productVariantIds.isNotEmpty) {
            var selectedVariant = prdct.variants!.firstWhere(
                (variant) => productVariantIds.contains(variant.id.toString()),
                orElse: () => prdct.variants!.first // default in case no match
                );

            prdct.selectedVariant = selectedVariant;
            // Remove the assigned variant id from the productVariantIds list
            productVariantIds.remove(selectedVariant.id.toString());
          } else {
            prdct.selectedVariant = prdct.variants!.first;
          }
        });
      }
    }
  }

  Widget buildComboProduct(Product comboProduct) {
    return GestureDetector(
      onTap: () {
        Utils.navigateToScreen(context, Routes.productDetailsScreen,
            arguments:
                ProductDetailsScreen.buildArguments(product: comboProduct));
      },
      child: Container(
          padding: const EdgeInsetsDirectional.symmetric(
              vertical: appContentHorizontalPadding / 2),
          decoration: BoxDecoration(
            border: Border.all(color: Colors.transparent),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              CustomImageWidget(
                  url: comboProduct.type == variableProductType
                      ? comboProduct.selectedVariant != null &&
                              comboProduct.selectedVariant!.images!.isNotEmpty
                          ? comboProduct.selectedVariant!.images!.first
                          : comboProduct.image ?? ''
                      : comboProduct.image ?? '',
                  width: 86,
                  height: 100,
                  borderRadius: 8),
              DesignConfig.smallWidthSizedBox,
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    CustomTextContainer(
                      textKey: comboProduct.type == variableProductType &&
                              comboProduct.selectedVariant != null
                          ? '${comboProduct.name}/ ${comboProduct.selectedVariant!.variantValues}'
                          : comboProduct.name ?? "",
                      maxLines: 3,
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium!
                          .copyWith(fontWeight: FontWeight.bold),
                    ),
                    DesignConfig.smallHeightSizedBox,
                    CustomTextContainer(
                        textKey: Utils.priceWithCurrencySymbol(
                            price: comboProduct.hasSpecialPrice()
                                ? comboProduct.getPrice()
                                : comboProduct.getBasePrice(),
                            context: context),
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: Theme.of(context).colorScheme.primary,
                            )),
                  ],
                ),
              ),
              const Icon(
                Icons.arrow_forward_ios,
                size: 24,
              )
            ],
          )),
    );
  }
}
