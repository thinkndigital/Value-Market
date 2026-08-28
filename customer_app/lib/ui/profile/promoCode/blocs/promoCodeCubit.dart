import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/promoCode.dart';
import '../repositories/promoCodeRepository.dart';

abstract class PromoCodeState {}

class PromoCodeInitial extends PromoCodeState {}

class PromoCodeFetchInProgress extends PromoCodeState {}

class PromoCodeFetchSuccess extends PromoCodeState {
  final List<PromoCode> promoCodes;

  PromoCodeFetchSuccess(this.promoCodes);
}

class PromoCodeFetchFailure extends PromoCodeState {
  final String errorMessage;

  PromoCodeFetchFailure(this.errorMessage);
}

class PromoCodeCubit extends Cubit<PromoCodeState> {
  final PromoCodeRepository _promoCodeRepository = PromoCodeRepository();

  PromoCodeCubit() : super(PromoCodeInitial());

  void getpromoCodes({required int storeId}) {
    emit(PromoCodeFetchInProgress());

    _promoCodeRepository
        .getPromoCodes(storeId: storeId)
        .then((value) => emit(PromoCodeFetchSuccess(value)))
        .catchError((e) {
      emit(PromoCodeFetchFailure(e.toString()));
    });
  }
}
