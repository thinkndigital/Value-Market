import 'package:value_market_customer/ui/cart/cubits/getUserCart.dart';
import 'package:value_market_customer/ui/cart/repositories/cartRepository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ClearCartState {}

class ClearCartInitial extends ClearCartState {}

class ClearCartProgress extends ClearCartState {}

class ClearCartFailure extends ClearCartState {
  final String errorMessage;
  ClearCartFailure(this.errorMessage);
}

class ClearCartSuccess extends ClearCartState {
  final String successMessage;
  ClearCartSuccess({required this.successMessage});
}

class ClearCartCubit extends Cubit<ClearCartState> {
  final CartRepository _cartRepository = CartRepository();
  ClearCartCubit() : super(ClearCartInitial());
  void clearCart(BuildContext context) async {
    emit(ClearCartProgress());

    _cartRepository.clearCart().then((value) {
      context.read<GetUserCartCubit>().resetCart(context);
      emit(ClearCartSuccess(successMessage: value));
    }).catchError((e) {
      emit(ClearCartFailure(e.toString()));
    });
  }
}
