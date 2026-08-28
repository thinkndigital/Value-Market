import 'package:eshop_plus/ui/profile/orders/repositories/orderRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ProductFileDownloadLinkState {}

class ProductFileDownloadLinkInitial extends ProductFileDownloadLinkState {}

class ProductFileDownloadLinkProgress extends ProductFileDownloadLinkState {}

class ProductFileDownloadLinkFailure extends ProductFileDownloadLinkState {
  final String errorMessage;
  ProductFileDownloadLinkFailure(this.errorMessage);
}

class ProductFileDownloadLinkSuccess extends ProductFileDownloadLinkState {
  final String downloadLink;
  ProductFileDownloadLinkSuccess({required this.downloadLink});
}

class ProductFileDownloadLinkCubit extends Cubit<ProductFileDownloadLinkState> {
  final OrderRepository orderRepository = OrderRepository();
  ProductFileDownloadLinkCubit() : super(ProductFileDownloadLinkInitial());
  void getProductFileDownloadLink({required int orderItemId}) async {
    emit(ProductFileDownloadLinkProgress());

    try {
      await orderRepository.getFileDownloadLink(orderItemId: orderItemId).then(
          (value) => emit(ProductFileDownloadLinkSuccess(downloadLink: value)));
    } catch (e) {
      emit(ProductFileDownloadLinkFailure(e.toString()));
    }
  }
}
