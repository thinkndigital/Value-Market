import 'package:eshop_plus/ui/profile/orders/repositories/orderRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class GetInvoiceState {}

class GetInvoiceInitial extends GetInvoiceState {}

class GetInvoiceProgress extends GetInvoiceState {}

class GetInvoiceFailure extends GetInvoiceState {
  final String errorMessage;
  GetInvoiceFailure(this.errorMessage);
}

class GetInvoiceSuccess extends GetInvoiceState {
  final String invoiceUrl;
  GetInvoiceSuccess({required this.invoiceUrl});
}

class GetInvoiceCubit extends Cubit<GetInvoiceState> {
  final OrderRepository orderRepository = OrderRepository();
  GetInvoiceCubit() : super(GetInvoiceInitial());
  void getInvoice({required int orderId}) async {
    emit(GetInvoiceProgress());

    try {
      String result = await orderRepository.getOrderInvoice(orderId: orderId);
      emit(GetInvoiceSuccess(invoiceUrl: result));
    } catch (e) {
      emit(GetInvoiceFailure(e.toString()));
    }
  }
}
