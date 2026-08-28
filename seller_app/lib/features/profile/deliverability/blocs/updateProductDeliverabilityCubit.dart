import 'package:eshopplus_seller/commons/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class UpdateProductDeliverabilityState {}

class UpdateProductDeliverabilityInitial
    extends UpdateProductDeliverabilityState {}

class UpdateProductDeliverabilityProgress
    extends UpdateProductDeliverabilityState {}

class UpdateProductDeliverabilityFailure
    extends UpdateProductDeliverabilityState {
  final String errorMessage;
  UpdateProductDeliverabilityFailure(this.errorMessage);
}

class UpdateProductDeliverabilitySuccess
    extends UpdateProductDeliverabilityState {
  final String successMessage;

  UpdateProductDeliverabilitySuccess({required this.successMessage});
}

class UpdateProductDeliverabilityCubit
    extends Cubit<UpdateProductDeliverabilityState> {
  final ProductRepository productRepository = ProductRepository();
  UpdateProductDeliverabilityCubit()
      : super(UpdateProductDeliverabilityInitial());
  void updateProductDeliverability(
      {required Map<String, dynamic> params, required String apiUrl}) async {
    emit(UpdateProductDeliverabilityProgress());

    try {
      final result = await productRepository.updateProductDeliverability(
          params: params, apiUrl: apiUrl);

      emit(UpdateProductDeliverabilitySuccess(
        successMessage: result,
      ));
    } catch (e) {
      emit(UpdateProductDeliverabilityFailure(e.toString()));
    }
  }
}
