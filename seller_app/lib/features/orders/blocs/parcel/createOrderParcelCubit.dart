import 'package:eshopplus_seller/features/orders/models/parcel.dart';
import 'package:eshopplus_seller/features/orders/repositories/orderRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class CreateOrderParcelState {}

class CreateOrderParcelInitial extends CreateOrderParcelState {}

class CreateOrderParcelProgress extends CreateOrderParcelState {}

class CreateOrderParcelFailure extends CreateOrderParcelState {
  final String errorMessage;
  CreateOrderParcelFailure(this.errorMessage);
}

class CreateOrderParcelSuccess extends CreateOrderParcelState {
  final String successMessage;
  final int orderID;
  final Parcel parcel;
  CreateOrderParcelSuccess(
      {required this.successMessage,
      required this.orderID,
      required this.parcel});
}

class CreateOrderParcelCubit extends Cubit<CreateOrderParcelState> {
  final OrderRepository orderRepository = OrderRepository();
  CreateOrderParcelCubit() : super(CreateOrderParcelInitial());
  void createOrderParcel({required Map<String, dynamic> params}) async {
    emit(CreateOrderParcelProgress());

    try {
      final result = await orderRepository.createOrderParcel(params);

      emit(CreateOrderParcelSuccess(
          successMessage: result.message,
          orderID: int.parse(result.parcel.id.toString()),
          parcel: result.parcel));
    } catch (e) {
      emit(CreateOrderParcelFailure(e.toString()));
    }
  }
}
