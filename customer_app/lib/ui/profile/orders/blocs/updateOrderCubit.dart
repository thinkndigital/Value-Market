import 'package:eshop_plus/ui/profile/orders/models/order.dart';
import 'package:eshop_plus/ui/profile/orders/repositories/orderRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class UpdateOrderState {}

class UpdateOrderInitial extends UpdateOrderState {}

class UpdateOrderFetchInProgress extends UpdateOrderState {}

class UpdateOrderFetchSuccess extends UpdateOrderState {
  final Order order;
  final String successMessage;
  UpdateOrderFetchSuccess({required this.order, required this.successMessage});
}

class UpdateOrderFetchFailure extends UpdateOrderState {
  final String errorMessage;

  UpdateOrderFetchFailure(this.errorMessage);
}

class UpdateOrderCubit extends Cubit<UpdateOrderState> {
  final OrderRepository _orderRepository = OrderRepository();

  UpdateOrderCubit() : super(UpdateOrderInitial());

  void updateOrder({required Map<String, dynamic> params}) {
    emit(UpdateOrderFetchInProgress());

    _orderRepository
        .updateOrderItemStatus(params: params)
        .then((value) => emit(UpdateOrderFetchSuccess(
            order: value.order, successMessage: value.successMessage)))
        .catchError((e) {
      emit(UpdateOrderFetchFailure(e.toString()));
    });
  }
}
