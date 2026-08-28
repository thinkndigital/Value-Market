import 'package:eshopplus_seller/features/profile/faq/models/faq.dart';
import 'package:eshopplus_seller/features/profile/faq/repositories/faqRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class AddProductFaqState {}

class AddProductFaqInitial extends AddProductFaqState {}

class AddProductFaqProgress extends AddProductFaqState {}

class AddProductFaqSuccess extends AddProductFaqState {
  final String successMessage;
  final FAQ faq;
  AddProductFaqSuccess({
    required this.successMessage,
    required this.faq,
  });
}

class AddProductFaqFailure extends AddProductFaqState {
  final String errorMessage;

  AddProductFaqFailure(this.errorMessage);
}

class AddProductFaqCubit extends Cubit<AddProductFaqState> {
  final FaqRepository _faqRepository = FaqRepository();

  AddProductFaqCubit() : super(AddProductFaqInitial());

  void addProductFaq(
      {required Map<String, dynamic> params, required bool isEditFaq}) async {
    emit(AddProductFaqProgress());
    _faqRepository
        .addProductFaq(params: params, isEditFaq: isEditFaq)
        .then((value) {
      emit(AddProductFaqSuccess(
          successMessage: value.successMessage, faq: value.faq));
    }).catchError((e) {
      emit(AddProductFaqFailure(e.toString()));
    });
  }
}
