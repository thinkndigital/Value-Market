import 'package:eshop_plus/core/api/apiService.dart';
import 'package:eshop_plus/ui/cart/repositories/cartRepository.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

abstract class CheckCartProductDeliverabilityState {}

class CheckCartProductDeliverabilityInitial
    extends CheckCartProductDeliverabilityState {}

class CheckCartProductDeliverabilityInProgress
    extends CheckCartProductDeliverabilityState {}

class CheckCartProductDeliverabilitySuccess
    extends CheckCartProductDeliverabilityState {
  final String successMessage;
  CheckCartProductDeliverabilitySuccess({required this.successMessage});
}

class CheckCartProductDeliverabilityFailure
    extends CheckCartProductDeliverabilityState {
  final String errorMessage;
  final List<Map<String, dynamic>>? errorData;
  CheckCartProductDeliverabilityFailure(this.errorMessage, this.errorData);
}

class CheckCartProductDeliverabilityCubit
    extends Cubit<CheckCartProductDeliverabilityState> {
  final CartRepository _cartRepository = CartRepository();

  CheckCartProductDeliverabilityCubit()
      : super(CheckCartProductDeliverabilityInitial());

  Future<void> checkDeliverability(
      {required int storeId, required int addressId}) async {
    if (isClosed) return;

    try {
      emit(CheckCartProductDeliverabilityInProgress());

      if (storeId <= 0 || addressId <= 0) {
        throw Exception('Invalid store ID or address ID');
      }

      final value = await _cartRepository.checkDeliverability(
        storeId: storeId,
        addressId: addressId,
      );

      if (isClosed) return;

      if (value.isEmpty) {
        emit(CheckCartProductDeliverabilityFailure(
          'Failed to verify deliverability',
          null,
        ));
        return;
      }

      emit(CheckCartProductDeliverabilitySuccess(successMessage: value));
    } catch (e) {
      // if (isClosed) return;

      final errorData =
          e is ApiException && e.errorData != null ? e.errorData : null;

      emit(CheckCartProductDeliverabilityFailure(
        e.toString(),
        errorData,
      ));
    }
  }
}
