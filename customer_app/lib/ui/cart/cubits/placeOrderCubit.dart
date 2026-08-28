import 'package:eshop_plus/ui/cart/repositories/cartRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class PlaceOrderState {}

class PlaceOrderInitial extends PlaceOrderState {}

class PlaceOrderInProgress extends PlaceOrderState {}

class PlaceOrderSuccess extends PlaceOrderState {
  final int orderId;
  final double finalTotal;
  final double walletBalance;
  PlaceOrderSuccess(
      {required this.orderId,
      required this.finalTotal,
      required this.walletBalance});
}

class PlaceOrderFailure extends PlaceOrderState {
  final String errorMessage;

  PlaceOrderFailure(this.errorMessage);
}

class PlaceOrderCubit extends Cubit<PlaceOrderState> {
  final CartRepository _cartRepository = CartRepository();

  PlaceOrderCubit() : super(PlaceOrderInitial());

  void placeOrder({required Map<String, dynamic> params}) {
    emit(PlaceOrderInProgress());

    _cartRepository
        .placeOrder(params: params)
        .then((value) => emit(PlaceOrderSuccess(
            orderId: value.orderId,
            finalTotal: value.finalTotal,
            walletBalance: value.walletBalance)))
        .catchError((e) {
      emit(PlaceOrderFailure(e.toString()));
    });
  }

  resetState() {
    emit(PlaceOrderInitial());
  }
}
