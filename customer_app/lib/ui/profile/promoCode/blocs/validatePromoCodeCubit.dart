import 'package:eshop_plus/ui/profile/promoCode/models/promoCode.dart';
import 'package:eshop_plus/ui/profile/promoCode/repositories/promoCodeRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ValidatePromoCodeState {}

class ValidatePromoCodeInitial extends ValidatePromoCodeState {}

class ValidatePromoCodeFetchInProgress extends ValidatePromoCodeState {}

class ValidatePromoCodeFetchSuccess extends ValidatePromoCodeState {
  final PromoCode promoCode;
  ValidatePromoCodeFetchSuccess({required this.promoCode});
}

class ValidatePromoCodeFetchFailure extends ValidatePromoCodeState {
  final String errorMessage;
  final double finalTotal;
  ValidatePromoCodeFetchFailure(this.errorMessage, this.finalTotal);
}

class ValidatePromoCodeCubit extends Cubit<ValidatePromoCodeState> {
  final PromoCodeRepository _promoCodeRepository = PromoCodeRepository();

  ValidatePromoCodeCubit() : super(ValidatePromoCodeInitial());

  void validatePromoCode({required Map<String, dynamic> params}) {
    emit(ValidatePromoCodeFetchInProgress());

    //promocode will be apply on cart subtotal ( total without delivery charge)
    _promoCodeRepository.validatePromoCode(params: params).then((value) {
      if (!isClosed) emit(ValidatePromoCodeFetchSuccess(promoCode: value));
    }).catchError((e) {
      if (!isClosed)
        emit(ValidatePromoCodeFetchFailure(
            e.toString(),
            double.tryParse(
                (e.errorData![0]['final_total'] ?? 0).toString().isEmpty
                    ? "0"
                    : (e.errorData![0]['final_total'] ?? 0).toString())!));
    });
  }
}
