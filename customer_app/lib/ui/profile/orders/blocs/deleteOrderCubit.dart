import 'package:eshop_plus/ui/profile/orders/repositories/orderRepository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class DeleteOrderState {}

class DeleteOrderInitial extends DeleteOrderState {}

class DeleteOrderProgress extends DeleteOrderState {}

class DeleteOrderFailure extends DeleteOrderState {
  final String errorMessage;
  DeleteOrderFailure(this.errorMessage);
}

class DeleteOrderSuccess extends DeleteOrderState {
  final String successMessage;
  DeleteOrderSuccess({required this.successMessage});
}

class DeleteOrderCubit extends Cubit<DeleteOrderState> {
  final OrderRepository orderRepository = OrderRepository();
  DeleteOrderCubit() : super(DeleteOrderInitial());
  void deleteOrder(
      {required String orderId, required BuildContext context}) async {
    emit(DeleteOrderProgress());

    try {
      await orderRepository.deleteOrder(orderId: orderId).then((value) {
        emit(DeleteOrderSuccess(successMessage: value));
      });
    } catch (e) {
      emit(DeleteOrderFailure(e.toString()));
    }
  }
}
