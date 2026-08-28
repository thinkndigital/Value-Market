import 'package:eshop_plus/ui/cart/models/cart.dart';
import 'package:eshop_plus/ui/cart/repositories/cartRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class RemoveFromCartState {}

class RemoveFromCartInitial extends RemoveFromCartState {}

class RemoveFromCartFetchInProgress extends RemoveFromCartState {
  final List<CartProduct>? products;
  final bool isRemoveForSavedForLater;
  RemoveFromCartFetchInProgress(this.products, this.isRemoveForSavedForLater);
}

class RemoveFromCartFetchSuccess extends RemoveFromCartState {
  final int id;
  String? totalQuantity;
  double? subTotal;
  double? itemTotal;
  double? discount;
  double? deliveryCharge;
  double? taxAmount;
  double? overallAmount;
  final String successMessage;
  final bool isRemoveForSavedForLater;
  RemoveFromCartFetchSuccess(
      {required this.id,
      required this.successMessage,
      this.totalQuantity,
      this.subTotal,
      this.itemTotal,
      this.discount,
      this.deliveryCharge,
      this.taxAmount,
      this.overallAmount,
      this.isRemoveForSavedForLater = false});
}

class RemoveFromCartFetchFailure extends RemoveFromCartState {
  final String errorMessage;
  final int id;
  RemoveFromCartFetchFailure(this.errorMessage, this.id);
}

class RemoveFromCartCubit extends Cubit<RemoveFromCartState> {
  final CartRepository _cartRepository = CartRepository();

  RemoveFromCartCubit() : super(RemoveFromCartInitial());

  void removeProductFromCart(
      {required Map<String, dynamic> params,
      List<CartProduct>? products,
      required int cartId,
      bool isRemoveForSavedForLater = false}) {
    if (products != null && products.isNotEmpty) {
      products
          .firstWhere((element) => element.cartId == cartId)
          .removeProductInProgress = true;
    }
    emit(RemoveFromCartFetchInProgress(products, isRemoveForSavedForLater));

    _cartRepository
        .removeProductFromCart(params: params)
        .then((value) => emit(RemoveFromCartFetchSuccess(
            id: cartId,
            successMessage: value.successMessage!,
            totalQuantity: value.totalQuantity,
            subTotal: value.subTotal,
            deliveryCharge: value.deliveryCharge,
            taxAmount: value.taxAmount,
            overallAmount: value.overallAmount,
            itemTotal: value.itemTotal,
            discount: value.discount,
            isRemoveForSavedForLater: isRemoveForSavedForLater)))
        .catchError((e) {
      emit(RemoveFromCartFetchFailure(e.toString(), cartId));
    });
  }
}
