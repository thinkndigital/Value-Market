import 'package:eshopplus_seller/commons/repositories/productRepository.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class DeleteProductState {}

class DeleteProductInitial extends DeleteProductState {}

class DeleteProductProgress extends DeleteProductState {}

class DeleteProductFailure extends DeleteProductState {
  final String errorMessage;
  DeleteProductFailure(this.errorMessage);
}

class ProductDeleted extends DeleteProductState {
  final String successMessage;
  final int productId;
  ProductDeleted({required this.successMessage, required this.productId});
}

class DeleteProductCubit extends Cubit<DeleteProductState> {
  final ProductRepository productRepository = ProductRepository();
  DeleteProductCubit() : super(DeleteProductInitial());
  void deleteProduct({required Map<String, dynamic> params}) async {
    emit(DeleteProductProgress());

    try {
      final result = await productRepository.deleteProduct(params: params);

      emit(ProductDeleted(
          successMessage: result, productId: params[ApiURL.productIdApiKey]));
    } catch (e) {
      emit(DeleteProductFailure(e.toString()));
    }
  }
}
