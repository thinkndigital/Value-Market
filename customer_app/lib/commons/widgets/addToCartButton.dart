import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/cart/cubits/getUserCart.dart';
import 'package:value_market_customer/ui/cart/cubits/manageCartCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_customer/ui/cart/models/cart.dart';
import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customRoundedButton.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

GlobalKey<AddToCartButtonState> addCartKey() =>
    GlobalKey<AddToCartButtonState>();

class AddToCartButton extends StatefulWidget {
  final int storeId;
  final double? widthPercentage;
  final double? height;
  final String? title;
  final int productId;
  final String productType; // product type will be physical or digital
  final String type; // type will be regular or combo product
  final int? qty;
  final int sellerId;
  final bool? reloadCart;
  final bool? isBuyNowButton;
  final String stockType;
  final String stock;
  final bool? showButtonBorder;
  final bool?
      isFromVariantSelectorPopup; // we will use this param to check whether this button is modal bottom sheet or not..bcoz we have to pop the bottom sheet when user press the button and get failure state
  const AddToCartButton(
      {super.key,
      required this.storeId,
      this.widthPercentage,
      this.height = 28,
      this.title,
      required this.productId,
      required this.productType,
      required this.type,
      required this.qty,
      required this.sellerId,
      this.reloadCart = false,
      this.isBuyNowButton = false,
      required this.stockType,
      required this.stock,
      this.showButtonBorder = false,
      this.isFromVariantSelectorPopup = false});

  @override
  AddToCartButtonState createState() => AddToCartButtonState();
}

class AddToCartButtonState extends State<AddToCartButton> {
  bool isLoading = false;
  @override
  Widget build(BuildContext context) {
    return BlocBuilder<GetUserCartCubit, GetUserCartState>(
      builder: (context, getCartState) {
        return BlocConsumer<ManageCartCubit, ManageCartState>(
          listener: (context, state) {
            if (state is ManageCartFetchSuccess) {
              if (widget.isBuyNowButton == true && isLoading) {
                // we will use this param to check whether this button is in detail scren or not..bco
                Utils.navigateToScreen(context, Routes.cartScreen);
              }
              setState(() {
                isLoading = false;
              });
            }
            if (state is ManageCartFetchFailure) {
              setState(() {
                isLoading = false;
              });
            }
          },
          builder: (context, state) {
            return CustomRoundedButton(
                height: widget.height ?? 28,
                widthPercentage: widget.widthPercentage ?? 0.25,
                buttonTitle: isLoading
                    ? ''
                    : widget.title ??
                        (isProductAddedinCart() ? goToCartKey : addToCartKey),
                showBorder: widget.showButtonBorder ?? false,
                borderColor: Theme.of(context).colorScheme.primary,
                backgroundColor: widget.showButtonBorder ?? false
                    ? Theme.of(context).colorScheme.primaryContainer
                    : Theme.of(context).colorScheme.primary,
                horizontalPadding: 2,
                style: widget.showButtonBorder == true
                    ? Theme.of(context).textTheme.bodyMedium!.copyWith(
                        color: Theme.of(context).colorScheme.secondary)
                    : widget.height! > 30
                        ? Theme.of(context).textTheme.bodyMedium!.copyWith(
                            color: Theme.of(context).colorScheme.onPrimary)
                        : Theme.of(context).textTheme.bodySmall!.copyWith(
                            color: Theme.of(context).colorScheme.onPrimary),
                child: isLoading
                    ? CustomCircularProgressIndicator(
                        indicatorColor: widget.showButtonBorder == true
                            ? Theme.of(context).colorScheme.primary
                            : Colors.white,
                      )
                    : null,
                onTap: () {
                  if (context.read<UserDetailsCubit>().isGuestUser()) {
                    Utils.showSnackBar(
                        context: context, message: loginToAddToCartKey);
                    return;
                  }
                  if (isLoading) return;
                  if (isProductAddedinCart()) {
                    Utils.navigateToScreen(
                      context,
                      Routes.cartScreen,
                    );
                  } else if (stockExceeded()) {
                    Utils.showSnackBar(
                      context: context,
                      message: stockLimitReachedKey,
                    );
                    if (widget.isFromVariantSelectorPopup == true) {
                      Navigator.pop(context);
                    }
                  } else {
                    //if the product is already in progress to add to cart then we will not add it again
                    if (state is ManageCartFetchInProgress &&
                        state.cartProductId == widget.productId) {
                      return;
                    }
                    if (context
                                .read<GetUserCartCubit>()
                                .getCartDetail()
                                .cartProducts !=
                            null &&
                        context
                                .read<GetUserCartCubit>()
                                .getCartDetail()
                                .cartProducts!
                                .length ==
                            int.parse(context
                                .read<SettingsAndLanguagesCubit>()
                                .getSettings()
                                .systemSettings!
                                .maximumItemAllowedInCart!)) {
                      Utils.showSnackBar(
                          context: context,
                          message: maxCartLimitWarningKey,
                          duration: const Duration(seconds: 5),
                          backgroundColor: Theme.of(context).colorScheme.error);
                      if (widget.isFromVariantSelectorPopup == true) {
                        Navigator.pop(context);
                      }
                      return;
                    }
                    setState(() {
                      isLoading = true;
                    });
                    addToCart(
                        type: widget.type,
                        productType: widget.productType,
                        productId: widget.productId,
                        qty: widget.qty,
                        sellerId: widget.sellerId,
                        reloadCart: widget.reloadCart);
                  }
                });
          },
        );
      },
    );
  }

  isProductAddedinCart() {
    return (context.read<GetUserCartCubit>().getCartDetail().cartProducts !=
            null &&
        context
                .read<GetUserCartCubit>()
                .getCartDetail()
                .cartProducts!
                .indexWhere((element) => widget.type == comboType
                    ? element.id == widget.productId
                    : element.productVariantId == widget.productId) !=
            -1);
  }

  addToCart(
      {required String type,
      required String productType,
      required int productId,
      required int? qty,
      required int sellerId,
      bool? reloadCart}) {
    List<CartProduct> cartProducts =
        context.read<GetUserCartCubit>().getCartDetail().cartProducts ?? [];
    // Check if the cart already contains a different product type
    bool containsPhysical =
        cartProducts.any((p) => p.type != digitalProductType);
    bool containsDigital =
        cartProducts.any((p) => p.type == digitalProductType);

    if ((productType != digitalProductType && containsDigital) ||
        (productType == digitalProductType && containsPhysical)) {
      // Show a message to the user
      Utils.showSnackBar(
          context: context,
          message: productTypeMixingWarningKey,
          backgroundColor: Theme.of(context).colorScheme.error);
      if (widget.isFromVariantSelectorPopup == true) {
        Navigator.pop(context);
      }
      setState(() {
        isLoading = false;
      });
    }

    //singleSellerOrderSystem = 0 means multiple seller order system , 1 means user can only order  product of only one seller at a time
    else if (cartProducts.isNotEmpty &&
        context
                .read<StoresCubit>()
                .getAllStores()
                .firstWhere((element) => element.id == widget.storeId)
                .isSingleSellerOrderSystem ==
            1 &&
        sellerId != cartProducts[0].sellerId) {
      Utils.showSnackBar(
          context: context,
          message: sellerOrderSystemWarningKey,
          duration: const Duration(seconds: 5),
          backgroundColor: Theme.of(context).colorScheme.error);
      if (widget.isFromVariantSelectorPopup == true) {
        Navigator.pop(context);
      }
      setState(() {
        isLoading = false;
      });
    } else {
      if (context.read<UserDetailsCubit>().isGuestUser()) {
      } else {
        context
            .read<ManageCartCubit>()
            .manageUserCart(productId, reloadCart: reloadCart ?? true, params: {
          ApiURL.storeIdApiKey: widget.storeId,
          ApiURL.productVariantIdApiKey: productId,
          ApiURL.productTypeApiKey: type,
          ApiURL.isSavedForLaterApiKey: 0,
          ApiURL.qtyApiKey: qty ?? 1,
          ApiURL.addressIdApiKey: context
                      .read<GetUserCartCubit>()
                      .getCartDetail()
                      .selectedAddress !=
                  null
              ? context
                  .read<GetUserCartCubit>()
                  .getCartDetail()
                  .selectedAddress!
                  .id!
              : '',
        });
      }
    }
  }

  bool stockExceeded() {
    if (widget.stockType == "") {
      return false;
    }
    return (int.tryParse(widget.stock) ?? 0) < (widget.qty ?? 1);
  }
}
