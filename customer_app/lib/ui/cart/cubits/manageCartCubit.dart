import 'package:eshop_plus/ui/cart/models/cart.dart';
import 'package:eshop_plus/ui/cart/repositories/cartRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ManageCartState {}

class ManageCartInitial extends ManageCartState {}

class ManageCartFetchInProgress extends ManageCartState {
  final int? cartProductId;
  ManageCartFetchInProgress(this.cartProductId);
}

class ManageCartFetchSuccess extends ManageCartState {
  final Cart cart;
  final bool reloadCart;
  final bool? isAddedToSaveLater;
  bool? changeQuantity;
  final int? cartProductId;
  ManageCartFetchSuccess(
      {required this.cart,
      this.reloadCart = true,
      this.isAddedToSaveLater = false,
      this.changeQuantity = false,
      this.cartProductId});
}

class ManageCartFetchFailure extends ManageCartState {
  final String errorMessage;

  ManageCartFetchFailure(this.errorMessage);
}

class ManageCartCubit extends Cubit<ManageCartState> {
  final CartRepository _cartRepository = CartRepository();

  ManageCartCubit() : super(ManageCartInitial());
//we dont have to show snackbar when we are changing the quantity of the product or moving the product to saved for later list
  //thats why we are passing changeQuantity param in manageUserCart function
  void manageUserCart(int? cartProductId,
      {bool? reloadCart,
      bool? isAddedToSaveLater,
      bool? changeQuantity,
      required Map<String, dynamic> params}) {
    emit(ManageCartFetchInProgress(cartProductId));
//here we are taking product in argumetns so that we can add it to saved for later list locallly..we need to update the cart from the total quantity and sub total and delivery charge which is response from remove from cart api
    _cartRepository
        .manageUserCart(params: params)
        .then((value) => emit(ManageCartFetchSuccess(
            cart: value,
            reloadCart: reloadCart ?? true,
            isAddedToSaveLater: isAddedToSaveLater ?? false,
            changeQuantity: changeQuantity ?? false,
            cartProductId: cartProductId)))
        .catchError((e) {
      emit(ManageCartFetchFailure(e.toString()));
    });
  }

  Cart getCartDetail() {
    if (state is ManageCartFetchSuccess) {
      return (state as ManageCartFetchSuccess).cart;
    }
    return Cart.fromJson({});
  }

  emitSuccessState(Cart cart) {
    emit(ManageCartFetchSuccess(cart: cart));
  }
}
