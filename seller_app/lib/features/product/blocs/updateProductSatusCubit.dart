import 'package:eshopplus_seller/commons/repositories/productRepository.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class UpdateProductStatusState {}

class UpdateProductStatusInitial extends UpdateProductStatusState {}

class UpdateProductStatusProgress extends UpdateProductStatusState {}

class UpdateProductStatusFailure extends UpdateProductStatusState {
  final String errorMessage;
  UpdateProductStatusFailure(this.errorMessage);
}

class UpdateProductStatusSuccess extends UpdateProductStatusState {
  final String successMessage;
  final int status;
  UpdateProductStatusSuccess(
      {required this.successMessage, required this.status});
}

class UpdateProductStatusCubit extends Cubit<UpdateProductStatusState> {
  final ProductRepository productRepository = ProductRepository();
  UpdateProductStatusCubit() : super(UpdateProductStatusInitial());
  void updateProductStatus({required Map<String, dynamic> params}) async {
    emit(UpdateProductStatusProgress());

    try {
      final result =
          await productRepository.updateProductStatus(params: params);

      emit(UpdateProductStatusSuccess(
          successMessage: result, status: params[ApiURL.statusApiKey]));
    } catch (e) {
      emit(UpdateProductStatusFailure(e.toString()));
    }
  }
}
