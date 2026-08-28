import 'package:eshop_plus/ui/cart/cubits/checkCartProductDelCubit.dart';
import 'package:eshop_plus/ui/profile/promoCode/blocs/localPromoCodeCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/ui/profile/address/models/address.dart';
import 'package:eshop_plus/ui/cart/models/cart.dart';
import 'package:eshop_plus/ui/profile/transaction/models/paymentMethod.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/ui/profile/promoCode/models/promoCode.dart';
import 'package:eshop_plus/ui/cart/repositories/cartRepository.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

abstract class GetUserCartState {}

class GetUserCartInitial extends GetUserCartState {}

class GetUserCartFetchInProgress extends GetUserCartState {}

class GetUserCartFetchInProgressForAddress extends GetUserCartState {}

class GetUserCartFetchSuccess extends GetUserCartState {
  final Cart cart;

  GetUserCartFetchSuccess({required this.cart});
}

class GetUserCartFetchFailure extends GetUserCartState {
  final String errorMessage;

  GetUserCartFetchFailure(this.errorMessage);
}

class GetUserCartCubit extends Cubit<GetUserCartState> {
  final CartRepository _cartRepository = CartRepository();

  GetUserCartCubit() : super(GetUserCartInitial());

  void fetchUserCart({required Map<String, dynamic> params}) {
    /*   if (state is! GetUserCartFetchSuccess) */ emit(
        GetUserCartFetchInProgress());

    _cartRepository.fetchUserCart(params: params).then((cart) async {
      if (!isClosed)
        emit(GetUserCartFetchSuccess(cart: cart));
      else {
        emit(GetUserCartFetchFailure(defaultErrorMessageKey));
      }
    }).catchError((e) {
      if (!isClosed) emit(GetUserCartFetchFailure(e.toString()));
    });
  }

  Cart getCartDetail() {
    if (state is GetUserCartFetchSuccess) {
      return (state as GetUserCartFetchSuccess).cart;
    }
    return Cart.fromJson({});
  }

  emitSuccessState(Cart cart) {
    if (!isClosed) emit(GetUserCartFetchSuccess(cart: cart));
  }

  getCartProductLength() {
    if (state is GetUserCartFetchSuccess) {
      return (state as GetUserCartFetchSuccess).cart.cartProducts != null &&
              (state as GetUserCartFetchSuccess).cart.cartProducts!.isNotEmpty
          ? (state as GetUserCartFetchSuccess).cart.cartProducts!.length
          : 0;
    }
    return 0;
  }

  addDeliveryInstruction(String instruction) {
    if (state is GetUserCartFetchSuccess) {
      (state as GetUserCartFetchSuccess).cart.deliveryInstruction = instruction;
      emit(GetUserCartFetchSuccess(
          cart: (state as GetUserCartFetchSuccess).cart));
    }
  }

  addEmailAddress(String emailAddress) {
    if (state is GetUserCartFetchSuccess) {
      (state as GetUserCartFetchSuccess).cart.emailAddress = emailAddress;
      emit(GetUserCartFetchSuccess(
          cart: (state as GetUserCartFetchSuccess).cart));
    }
  }

//from cart screen when call this method, then no need to emit the state
//it will cause app hang indefiitely
  changeSelectedAddress(Address? address) {
    if (state is GetUserCartFetchSuccess) {
      (state as GetUserCartFetchSuccess).cart.selectedAddress = address;

      emit(GetUserCartFetchSuccess(
          cart: (state as GetUserCartFetchSuccess).cart));
    }
  }

  changePaymantMethod(PaymentModel? paymentModel) {
    if (state is GetUserCartFetchSuccess) {
      (state as GetUserCartFetchSuccess).cart.selectedPaymentMethod =
          paymentModel;
      emit(GetUserCartFetchSuccess(
          cart: (state as GetUserCartFetchSuccess).cart));
    }
  }

  useWalletBalance(bool useWalletBalance, double walletBalance,
      {Cart? newCart}) {
    late Cart cart;
    if (newCart != null) {
      cart = newCart;
    } else if (state is GetUserCartFetchSuccess) {
      cart = (state as GetUserCartFetchSuccess).cart;
    }
    //here when we update only address then we will only use new cart getting from API other wise use old cart

    cart.useWalletBalance = useWalletBalance;
    if (useWalletBalance) {
      if (walletBalance >= cart.overallAmount!) {
        // Case 1: Wallet has enough balance to cover the total amount
        cart.walletAmount = cart.overallAmount;
        cart.overallAmount = 0.0;
        cart.selectedPaymentMethod = null;
      } else {
        // Case 2: Wallet has less balance than the total amount
        double remainingAmount = cart.overallAmount! - walletBalance;
        cart.walletAmount = walletBalance;
        cart.overallAmount =
            remainingAmount; // Return the remaining amount to pay
      }
    } else {
      // Wallet deselected
      cart.useWalletBalance = false;
      cart.overallAmount =
          cart.originalOverallAmount; // Restore original amount
      cart.walletAmount = 0.0; // Reset wallet contribution
    }
    emit(GetUserCartFetchSuccess(cart: cart));
  }

  setErrorMessage(int productId, String errorMessage) {
    if (state is GetUserCartFetchSuccess) {
      CartProduct? cartProduct =
          (state as GetUserCartFetchSuccess).cart.cartProducts!.firstWhere(
                (product) => product.id == productId,
                orElse: () => CartProduct(),
              );
      if (cartProduct != CartProduct()) {
        cartProduct.errorMessage = errorMessage;
      }

      emit(GetUserCartFetchSuccess(
          cart: (state as GetUserCartFetchSuccess).cart));
    }
  }

  resetErrorMessages() {
    if (state is GetUserCartFetchSuccess) {
      (state as GetUserCartFetchSuccess)
          .cart
          .cartProducts!
          .forEach((cartProduct) {
        cartProduct.errorMessage = '';
      });
      emit(GetUserCartFetchSuccess(
          cart: (state as GetUserCartFetchSuccess).cart));
    }
  }

  setPromoCode(BuildContext context, PromoCode promoCode) {
    if (state is GetUserCartFetchSuccess) {
      LocalPromocodeCubit().applyPromocode(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          promoCode: promoCode);
      Cart cart = (state as GetUserCartFetchSuccess).cart;
      if (cart.deliveryCharge != 0) {
        cart.overallAmount = promoCode.finalTotal! + cart.deliveryCharge!;
      } else {
        cart.overallAmount = promoCode.finalTotal!;
      }
      cart.couponDiscount = promoCode.finalDiscount!;
      cart.promoCode = promoCode;
      emitSuccessState(cart);
      if (cart.useWalletBalance == true) {
        useWalletBalance(true,
            context.read<UserDetailsCubit>().getuserDetails().balance ?? 0);
      }
    }
  }

  removePromoCode() {
    if (state is GetUserCartFetchSuccess) {
      Cart cart = (state as GetUserCartFetchSuccess).cart;
      cart.promoCode = null;
      cart.couponDiscount = 0;
      cart.overallAmount = cart.originalOverallAmount;
      LocalPromocodeCubit().removePromocode(cart.cartProducts!.first.storeId!);
      emitSuccessState(cart);
    }
  }

  double calculateItemTotalForProduct(Cart cart, int productId) {
    double total = 0.0;

    // Iterate over each cart product
    for (var cartProduct in cart.cartProducts!) {
      // Check if the product ID matches the specific product ID
      if (cartProduct.productDetails![0].id == productId) {
        // Add the total of this variant to the running total
        total += cartProduct.specialPrice! * cartProduct.qty!;
      }
    }

    return total;
  }

  updateCart(
      {required BuildContext context,
      required Cart oldCart,
      required Map<String, dynamic> params}) {
    emit(GetUserCartFetchInProgressForAddress());

    _cartRepository
        .fetchUserCart(params: params, isCallForSavedForLater: false)
        .then((newCart) {
      newCart.promoCode = oldCart.promoCode;
      newCart.couponDiscount = oldCart.couponDiscount;
      newCart.saveForLaterProducts = oldCart.saveForLaterProducts;
      newCart.selectedAddress = oldCart.selectedAddress;
      newCart.deliveryInstruction = oldCart.deliveryInstruction;
      newCart.selectedPaymentMethod = oldCart.selectedPaymentMethod;
      newCart.useWalletBalance = oldCart.useWalletBalance;
      newCart.walletAmount = oldCart.walletAmount;
      newCart.emailAddress = oldCart.emailAddress;
      newCart.attachments = oldCart.attachments;
      newCart.selectedAddress = oldCart.selectedAddress;

      if (oldCart.useWalletBalance == true) {
        useWalletBalance(
            oldCart.useWalletBalance ?? false, oldCart.walletAmount!,
            newCart: newCart);
      } else {
        emit(GetUserCartFetchSuccess(cart: newCart));
      }
      context.read<CheckCartProductDeliverabilityCubit>().checkDeliverability(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          addressId: oldCart.selectedAddress!.id!);
    }).catchError((e) {
      emit(GetUserCartFetchFailure(e.toString()));
    });
  }

  resetCart(BuildContext context) {
    LocalPromocodeCubit()
        .removePromocode(context.read<StoresCubit>().getDefaultStore().id!);
    if (state is GetUserCartFetchSuccess) {
      List<CartProduct>? saveForLaterProducts =
          (state as GetUserCartFetchSuccess).cart.saveForLaterProducts;

      // Create a new empty Cart object
      Cart cart = Cart(
        cartProducts: [], // Explicitly setting cartProducts to an empty list
        saveForLaterProducts:
            saveForLaterProducts ?? [], // Retain saveForLaterProducts
      );

      // Emit loading state before emitting the updated state
      emit(GetUserCartFetchInProgress());

      // Emit the updated state with the reset cart
      emit(GetUserCartFetchSuccess(cart: cart));
    }
  }

  void setStripePayId(String stripePayId) {
    if (state is GetUserCartFetchSuccess) {
      (state as GetUserCartFetchSuccess).cart.stripePayId = stripePayId;
      emit(GetUserCartFetchSuccess(
          cart: (state as GetUserCartFetchSuccess).cart));
    }
  }

  updateProductDetails(int cartProductId, Product product) {
    if (state is GetUserCartFetchSuccess) {
      CartProduct? cartProduct = (state as GetUserCartFetchSuccess)
          .cart
          .cartProducts!
          .firstWhereOrNull(
            (product) => product.id == cartProductId,
          );
      if (cartProduct != null) {
        cartProduct.productDetails![0] = product;
      }

      emit(GetUserCartFetchSuccess(
          cart: (state as GetUserCartFetchSuccess).cart));
    }
  }
}
