import 'package:eshopplus_seller/features/orders/repositories/orderRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class GetInvoiceState {}

class GetInvoiceInitial extends GetInvoiceState {}

class GetInvoiceProgress extends GetInvoiceState {
  final int id;
  GetInvoiceProgress({required this.id});
}

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
  void getInvoice({required int id, required String apiUrl}) async {
    emit(GetInvoiceProgress(id: id));

    try {
      String result =
          await orderRepository.getOrderInvoice(id: id, apiUrl: apiUrl);
      emit(GetInvoiceSuccess(invoiceUrl: result));
    } catch (e) {
      emit(GetInvoiceFailure(e.toString()));
    }
  }
}
