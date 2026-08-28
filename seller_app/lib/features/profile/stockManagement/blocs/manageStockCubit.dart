import 'package:eshopplus_seller/commons/models/product.dart';
import 'package:eshopplus_seller/commons/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ManageStockState {}

class ManageStockInitial extends ManageStockState {}

class ManageStockProgress extends ManageStockState {}

class ManageStockFailure extends ManageStockState {
  final String errorMessage;
  ManageStockFailure(this.errorMessage);
}

class ManageStockSuccess extends ManageStockState {
  final String successMessage;

  final Product product;
  ManageStockSuccess({required this.successMessage, required this.product});
}

class ManageStockCubit extends Cubit<ManageStockState> {
  final ProductRepository productRepository = ProductRepository();
  ManageStockCubit() : super(ManageStockInitial());
  void manageStock(
      {required Map<String, dynamic> params,
      required String apiUrl,
      required bool isComboProduct}) async {
    emit(ManageStockProgress());

    try {
      final result = await productRepository.manageStock(
          params: params, apiUrl: apiUrl, isComboProduct: isComboProduct);

      emit(ManageStockSuccess(
        product: result.product,
        successMessage: result.message,
      ));
    } catch (e) {
      emit(ManageStockFailure(e.toString()));
    }
  }
}
