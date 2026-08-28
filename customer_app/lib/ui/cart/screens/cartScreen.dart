import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/profile/address/blocs/getAddressCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/checkCartProductDelCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/getUserCart.dart';
import 'package:value_market_customer/ui/cart/cubits/manageCartCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/removeProductFromCartCubit.dart';
import 'package:value_market_customer/ui/profile/promoCode/blocs/validatePromoCodeCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_customer/ui/cart/models/cart.dart';
import 'package:value_market_customer/ui/profile/promoCode/models/promoCode.dart';
import 'package:value_market_customer/ui/cart/widgets/cartProductList.dart';
import 'package:value_market_customer/ui/cart/widgets/priceDetailContainer.dart';

import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_customer/commons/widgets/customRoundedButton.dart';
import 'package:value_market_customer/commons/widgets/customTextButton.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/commons/widgets/error_screen.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class CartScreen extends StatefulWidget {
  final bool shoulPop;
  final int? storeId;
  const CartScreen({Key? key, this.shoulPop = false, this.storeId})
      : super(key: key);
  static Widget getRouteInstance() => MultiBlocProvider(
          providers: [
            BlocProvider(
              create: (context) => RemoveFromCartCubit(),
            ),
            BlocProvider(
              create: (context) => CheckCartProductDeliverabilityCubit(),
            ),
          ],
          child: CartScreen(
              shoulPop:
                  Get.arguments != null && Get.arguments.containsKey('shoulPop')
                      ? Get.arguments['shoulPop'] ?? true
                      : true,
              storeId:
                  Get.arguments != null && Get.arguments.containsKey('storeId')
                      ? Get.arguments['storeId'] as int?
                      : null));
  @override
  _CartScreenState createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  GlobalKey _cartListKey = GlobalKey(), _priceDetailKey = GlobalKey();
  double? lastValidatedTotal;
  String? lastValidatedPromo;
  late int storeId;
  @override
  void initState() {
    super.initState();
    storeId =
        widget.storeId ?? context.read<StoresCubit>().getDefaultStore().id!;
    Future.delayed(Duration.zero, () {
      if (!context.read<UserDetailsCubit>().isGuestUser()) getUserCart();
    });
  }

  getUserCart() {
    String? addressId = '';
    if (context.read<GetAddressCubit>().state is GetAddressFetchSuccess) {
      GetAddressFetchSuccess state =
          context.read<GetAddressCubit>().state as GetAddressFetchSuccess;
      if (state.addresses.isNotEmpty) {
        int index =
            state.addresses.indexWhere((element) => element.isDefault == 1);
        addressId = index != -1
            ? state.addresses[index].id!.toString()
            : state.addresses.first.id!.toString();
      }
    }

    context.read<GetUserCartCubit>().fetchUserCart(params: {
      ApiURL.storeIdApiKey: storeId,
      ApiURL.onlyDeliveryChargeApiKey: 0,
      ApiURL.userIdApiKey: context.read<UserDetailsCubit>().getUserId(),
      ApiURL.addressIdApiKey: addressId
    });
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ManageCartCubit, ManageCartState>(
      builder: (context, mstate) {
        return Stack(
          clipBehavior: Clip.none,
          children: [
            IgnorePointer(
              ignoring: mstate is ManageCartFetchInProgress,
              child: BlocProvider(
                create: (context) => ValidatePromoCodeCubit(),
                child: Scaffold(
                    appBar: CustomAppbar(
                      titleKey: cartKey,
                      showBackButton: widget.shoulPop ? true : false,
                    ),
                    body: context.read<UserDetailsCubit>().isGuestUser()
                        ? ErrorScreen(
                            onPressed: () => Utils.navigateToScreen(
                                context, Routes.loginScreen),
                            text: loginToAddToCartKey,
                            buttonText: loginKey,
                            image: 'empty_cart',
                          )
                        : BlocListener<GetUserCartCubit, GetUserCartState>(
                            listener: (context, state) {
                              //we will set the default or first address as selected address
                              if (state is GetUserCartFetchSuccess) {
                                if (state.cart.selectedAddress == null) {
                                  if (context.read<GetAddressCubit>().state
                                      is GetAddressFetchSuccess) {
                                    GetAddressFetchSuccess state = context
                                        .read<GetAddressCubit>()
                                        .state as GetAddressFetchSuccess;
                                    int index = state.addresses.indexWhere(
                                        (element) => element.isDefault == 1);
                                    if (state.addresses.isNotEmpty) {
                                      if (index != -1) {
                                        context
                                            .read<GetUserCartCubit>()
                                            .changeSelectedAddress(
                                              state.addresses[index],
                                            );
                                      } else {
                                        context
                                            .read<GetUserCartCubit>()
                                            .changeSelectedAddress(
                                              state.addresses.first,
                                            );
                                      }
                                    }
                                  }
                                }
                                // Check if promo code or total has changed
                                if (state.cart.promoCode != null &&
                                    (state.cart.subTotal !=
                                            lastValidatedTotal ||
                                        state.cart.promoCode!.promoCode !=
                                            lastValidatedPromo)) {
                                  // Update tracking values
                                  setState(() {
                                    lastValidatedTotal = state.cart.subTotal;
                                    lastValidatedPromo =
                                        state.cart.promoCode!.promoCode;
                                  });

                                  // Validate promo code
                                  context
                                      .read<ValidatePromoCodeCubit>()
                                      .validatePromoCode(params: {
                                    ApiURL.finalTotalApiKey:
                                        state.cart.subTotal,
                                    ApiURL.promoCodeApiKey:
                                        state.cart.promoCode!.promoCode,
                                  });
                                }

                                if (state.cart.outOfStockProducts != null &&
                                    state.cart.outOfStockProducts!.isNotEmpty) {
                                  state.cart.outOfStockProducts!.forEach((pr) {
                                    int index = state.cart.cartProducts!
                                        .indexWhere(
                                            (element) => element.id == pr.id);
                                    if (index != -1) {
                                      state.cart.cartProducts![index]
                                              .errorMessage =
                                          productIsCurrentlyOutOfStockKey;
                                    }
                                  });
                                }
                              }
                            },
                            child:
                                BlocListener<ManageCartCubit, ManageCartState>(
                              listener: (context, manageState) {
                                if (manageState is ManageCartFetchSuccess) {
                                  // if we are reloading cart, we need to get the user cart otherwise we will get the cart from the managecartstate
                                  if (manageState.reloadCart) {
                                    getUserCart();
                                  } else {
                                    if (context.read<GetUserCartCubit>().state
                                        is GetUserCartFetchSuccess) {
                                      GetUserCartFetchSuccess state = context
                                          .read<GetUserCartCubit>()
                                          .state as GetUserCartFetchSuccess;
                                      manageState.cart.promoCode =
                                          state.cart.promoCode;
                                      manageState.cart.couponDiscount =
                                          state.cart.couponDiscount;

                                      manageState.cart.saveForLaterProducts =
                                          state.cart.saveForLaterProducts;
                                      manageState.cart.selectedAddress =
                                          state.cart.selectedAddress;
                                      manageState.cart.deliveryInstruction =
                                          state.cart.deliveryInstruction;
                                      manageState.cart.selectedPaymentMethod =
                                          state.cart.selectedPaymentMethod;
                                      manageState.cart.useWalletBalance =
                                          state.cart.useWalletBalance;
                                      manageState.cart.walletAmount =
                                          state.cart.walletAmount;
                                      manageState.cart.emailAddress =
                                          state.cart.emailAddress;
                                      manageState.cart.attachments =
                                          state.cart.attachments;
                                    }
                                    context
                                        .read<GetUserCartCubit>()
                                        .emitSuccessState(manageState.cart);
                                    if (manageState.cart.promoCode != null) {
                                      context
                                          .read<ValidatePromoCodeCubit>()
                                          .validatePromoCode(params: {
                                        ApiURL.finalTotalApiKey:
                                            manageState.cart.subTotal,
                                        ApiURL.promoCodeApiKey: manageState
                                            .cart.promoCode!.promoCode
                                      });
                                    }
                                  }
                                  refreshState();
                                }
                              },
                              child: BlocBuilder<GetUserCartCubit,
                                  GetUserCartState>(
                                builder: (context, state) {
                                  if (state is GetUserCartFetchSuccess) {
                                    return Stack(
                                      clipBehavior: Clip.none,
                                      children: [
                                        MultiBlocListener(
                                          listeners: [
                                            BlocListener<RemoveFromCartCubit,
                                                    RemoveFromCartState>(
                                                listener:
                                                    (context, remvovestate) {
                                              if (remvovestate
                                                  is RemoveFromCartFetchSuccess) {
                                                //here when we remove from cart we need to refresh the state so that change price will be reflected
                                                refreshState();
                                              }
                                            }),
                                            BlocListener<ValidatePromoCodeCubit,
                                                    ValidatePromoCodeState>(
                                                listener: (context, state) {
                                              if (context
                                                      .read<GetUserCartCubit>()
                                                      .state
                                                  is GetUserCartFetchSuccess) {
                                                Cart cart = (context
                                                            .read<
                                                                GetUserCartCubit>()
                                                            .state
                                                        as GetUserCartFetchSuccess)
                                                    .cart;
                                                if (state
                                                    is ValidatePromoCodeFetchSuccess) {
                                                  //when promo code is applied we need to update the cart
                                                  if (cart.deliveryCharge !=
                                                      0) {
                                                    cart.overallAmount = state
                                                            .promoCode
                                                            .finalTotal! +
                                                        cart.deliveryCharge!;
                                                  } else {
                                                    cart.overallAmount = state
                                                        .promoCode.finalTotal!;
                                                  }

                                                  cart.couponDiscount = state
                                                      .promoCode.finalDiscount!;
                                                  cart.promoCode =
                                                      state.promoCode;

                                                  context
                                                      .read<GetUserCartCubit>()
                                                      .emitSuccessState(cart);
                                                  if (cart.useWalletBalance ==
                                                      true) {
                                                    context
                                                        .read<
                                                            GetUserCartCubit>()
                                                        .useWalletBalance(
                                                            true,
                                                            context
                                                                    .read<
                                                                        UserDetailsCubit>()
                                                                    .getuserDetails()
                                                                    .balance ??
                                                                0);
                                                  }
                                                }
                                                if (state
                                                    is ValidatePromoCodeFetchFailure) {
                                                  Utils.showSnackBar(
                                                      context: context,
                                                      message:
                                                          state.errorMessage);
                                                  cart.overallAmount =
                                                      state.finalTotal;
                                                  cart.promoCode = null;
                                                  cart.couponDiscount = 0;
                                                  context
                                                      .read<GetUserCartCubit>()
                                                      .emitSuccessState(cart);
                                                }
                                              }
                                            })
                                          ],
                                          child: (state.cart.cartProducts ==
                                                          null ||
                                                      (state.cart.cartProducts !=
                                                              null &&
                                                          state
                                                              .cart
                                                              .cartProducts!
                                                              .isEmpty)) &&
                                                  (state.cart.saveForLaterProducts ==
                                                          null ||
                                                      (state.cart.saveForLaterProducts !=
                                                              null &&
                                                          state
                                                              .cart
                                                              .saveForLaterProducts!
                                                              .isEmpty))
                                              ? ErrorScreen(
                                                  text: emptyCartkey,
                                                  image: 'empty_cart',
                                                  onPressed: () {},
                                                  child: CustomRoundedButton(
                                                    widthPercentage: 0.5,
                                                    buttonTitle:
                                                        addFromFavoritesKey,
                                                    showBorder: false,
                                                    onTap: () =>
                                                        Utils.navigateToScreen(
                                                            context,
                                                            Routes
                                                                .favoriteScreen),
                                                  ),
                                                )
                                              : buildBodyContent(
                                                  state, context),
                                        ),
                                        buildPlaceOrderButton(state)
                                      ],
                                    );
                                  }
                                  if (state is GetUserCartFetchFailure) {
                                    return ErrorScreen(
                                        onPressed: getUserCart,
                                        text: state.errorMessage,
                                        image:
                                            state.errorMessage == noInternetKey
                                                ? "no_internet"
                                                : 'empty_cart',
                                        child: state
                                                is GetUserCartFetchInProgress
                                            ? CustomCircularProgressIndicator(
                                                indicatorColor:
                                                    Theme.of(context)
                                                        .colorScheme
                                                        .primary,
                                              )
                                            : null);
                                  }
                                  return CustomCircularProgressIndicator(
                                      indicatorColor: Theme.of(context)
                                          .colorScheme
                                          .primary);
                                },
                              ),
                            ),
                          )),
              ),
            ),
            if (mstate is ManageCartFetchInProgress ||
                mstate is GetUserCartFetchInProgressForAddress)
              Container(
                height: double.maxFinite,
                width: double.maxFinite,
                color: Colors.black.withValues(alpha: 0.5),
                alignment: Alignment.center,
                child: CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.primary,
                ),
              )
          ],
        );
      },
    );
  }

  refreshState() {
    _cartListKey = GlobalKey();
    _priceDetailKey = GlobalKey();
  }

  Widget buildBodyContent(GetUserCartFetchSuccess state, BuildContext context) {
    return ListView(
      padding: const EdgeInsets.only(top: 12, bottom: 120),
      children: [
        CartProductList(
          key: _cartListKey,
          cart: state.cart,
          removeFromCartCubit: context.read<RemoveFromCartCubit>(),
        ),
        DesignConfig.smallHeightSizedBox,
        if ((state.cart.cartProducts != null &&
                state.cart.cartProducts!.isNotEmpty &&
                state.cart.cartProducts![0].type != digitalProductType) &&
            context.read<GetUserCartCubit>().getCartDetail().selectedAddress !=
                null)
          changeAddressWidget(),
        if (state.cart.cartProducts != null &&
            state.cart.cartProducts!.isNotEmpty)
          offerContainer(state.cart.promoCode),
        if (state.cart.cartProducts != null &&
            state.cart.cartProducts!.isNotEmpty)
          PriceDetailContainer(
            key: _priceDetailKey,
            cart: state.cart,
          ),
      ],
    );
  }

  offerContainer(PromoCode? promoCode) {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.promoCodeScreen,
          arguments: {'fromCartScreen': true}),
      child: Padding(
        padding: const EdgeInsets.only(bottom: 8.0),
        child: CustomDefaultContainer(
            child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
                child: CustomTextContainer(
              textKey:
                  promoCode != null ? promoCode.promoCode! : addCouponCodeKey,
              style: Theme.of(context).textTheme.titleMedium,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            )),
            if (promoCode != null)
              IconButton(
                  visualDensity:
                      const VisualDensity(vertical: -4, horizontal: -4),
                  onPressed: () {
                    context.read<GetUserCartCubit>().removePromoCode();
                  },
                  icon: const Icon(
                    Icons.close,
                    size: 24,
                  ))
            else
              const Icon(Icons.arrow_forward_ios, size: 24)
          ],
        )),
      ),
    );
  }

  changeAddressWidget() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8.0),
      child: GestureDetector(
        onTap: () => Utils.navigateToScreen(context, Routes.placeOrderScreen,
            arguments: {
              'showAddressSelection': true,
              'storeId': storeId,
            }),
        child: Container(
            padding: const EdgeInsetsDirectional.symmetric(
                vertical: appContentVerticalSpace),
            color: Colors.white,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: appContentHorizontalPadding),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      CustomTextContainer(
                        textKey: deliveryAddressKey,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      CustomTextButton(
                        onTapButton: () => Utils.navigateToScreen(
                            context, Routes.placeOrderScreen,
                            arguments: {
                              'showAddressSelection': true,
                              'storeId': storeId,
                            }),
                        buttonTextKey: changekey,
                        textStyle: Theme.of(context)
                            .textTheme
                            .bodyMedium!
                            .copyWith(
                                color: Theme.of(context).colorScheme.primary),
                      )
                    ],
                  ),
                ),
                const Divider(
                  height: 12,
                  thickness: 0.5,
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: appContentHorizontalPadding),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Utils.getAddressWidget(
                            context,
                            context
                                .read<GetUserCartCubit>()
                                .getCartDetail()
                                .selectedAddress!),
                      ),
                      const Icon(Icons.arrow_forward_ios, size: 24)
                    ],
                  ),
                ),
              ],
            )),
      ),
    );
  }

  buildPlaceOrderButton(GetUserCartState state) {
    if (state is GetUserCartFetchSuccess &&
        state.cart.cartProducts != null &&
        state.cart.cartProducts!.isNotEmpty) {
      return BlocListener<CheckCartProductDeliverabilityCubit,
          CheckCartProductDeliverabilityState>(
        listener: (context, state) {
          if (state is CheckCartProductDeliverabilitySuccess) {
            context.read<GetUserCartCubit>().resetErrorMessages();
            Utils.navigateToScreen(context, Routes.placeOrderScreen,
                    arguments: {
                  'showAddressSelection': false,
                  'storeId': storeId,
                })!
                .then((value) {});
          }
          if (state is CheckCartProductDeliverabilityFailure) {
            if (state.errorData != null) {
              for (var item in state.errorData!) {
                if (item['is_deliverable'] == false) {
                  int productId = item['product_id'];
                  String errorMessage =
                      '${item['name']} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: notDelierableErrorMessageKey)}';

                  // Find the corresponding CartProduct and assign the error message

                  context
                      .read<GetUserCartCubit>()
                      .setErrorMessage(productId, errorMessage);
                }
              }
            }

            Utils.showSnackBar(
                context: context,
                duration: const Duration(seconds: 7),
                backgroundColor: Theme.of(context).colorScheme.error,
                message: state.errorMessage);
          }
        },
        child: Align(
          alignment: Alignment.bottomCenter,
          child: Container(
              margin: EdgeInsetsDirectional.only(
                  start: appContentHorizontalPadding,
                  end: appContentHorizontalPadding,
                  top: 8,
                  bottom: MediaQuery.of(context).padding.bottom + 8),
              child: CustomRoundedButton(
                widthPercentage: 1,
                buttonTitle: proceedToCheckoutKey,
                showBorder: false,
                onTap: () {
                  if (state.cart.cartProducts != null &&
                      state.cart.cartProducts!.isNotEmpty &&
                      state.cart.outOfStockProducts != null &&
                      state.cart.outOfStockProducts!.isNotEmpty) {
                    Utils.showSnackBar(
                        context: context,
                        duration: const Duration(seconds: 7),
                        message: removeOutOfStockProductErrorMessageKey);
                    return;
                  }
                  if (state.cart.subTotal != null &&
                      state.cart.subTotal! <
                          double.parse(context
                              .read<SettingsAndLanguagesCubit>()
                              .getSettings()
                              .systemSettings!
                              .minimumCartAmount!
                              .toString())) {
                    Utils.showSnackBar(
                        context: context,
                        duration: const Duration(seconds: 7),
                        message:
                            '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: minOrderAmountWarning1Key)}${Utils.priceWithCurrencySymbol(context: context, price: double.parse(context.read<SettingsAndLanguagesCubit>().getSettings().systemSettings!.minimumCartAmount.toString()))}. ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: minOrderAmountWarning2Key)}');
                    return;
                  }
                  if (state.cart.cartProducts![0].type != digitalProductType &&
                      context
                              .read<GetUserCartCubit>()
                              .getCartDetail()
                              .selectedAddress !=
                          null)
                    context
                        .read<CheckCartProductDeliverabilityCubit>()
                        .checkDeliverability(
                            storeId: storeId,
                            addressId: context
                                .read<GetUserCartCubit>()
                                .getCartDetail()
                                .selectedAddress!
                                .id!);
                  else
                    Utils.navigateToScreen(context, Routes.placeOrderScreen,
                        arguments: {
                          'showAddressSelection': true,
                          'storeId': storeId,
                        });
                },
              )),
        ),
      );
    }
    return const SizedBox.shrink();
  }
}
