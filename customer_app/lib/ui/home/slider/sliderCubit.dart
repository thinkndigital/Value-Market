import 'package:value_market_customer/ui/home/slider/slider.dart';
import 'package:value_market_customer/ui/home/offer/offerRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SliderState {}

class SliderInitial extends SliderState {}

class SliderFetchInProgress extends SliderState {}

class SliderFetchSuccess extends SliderState {
  final List<Sliders> sliders;

  SliderFetchSuccess(this.sliders);
}

class SliderFetchFailure extends SliderState {
  final String errorMessage;

  SliderFetchFailure(this.errorMessage);
}

class SliderCubit extends Cubit<SliderState> {
  final OfferRepository _offerRepository = OfferRepository();

  SliderCubit() : super(SliderInitial());

  void getSliders({required int storeId}) {
    emit(SliderFetchInProgress());

    _offerRepository.getSliders(storeId: storeId).then((value) {
      if (!isClosed) emit(SliderFetchSuccess(value));
    }).catchError((e) {
      if (!isClosed) emit(SliderFetchFailure(e.toString()));
    });
  }
}
