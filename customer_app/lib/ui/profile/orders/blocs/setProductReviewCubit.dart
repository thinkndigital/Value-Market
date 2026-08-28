import 'package:eshop_plus/commons/product/models/productRating.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SetProductReviewState {}

class SetProductReviewInitial extends SetProductReviewState {}

class SetProductReviewInProgress extends SetProductReviewState {}

class SetProductReviewSuccess extends SetProductReviewState {
  final String successMessage;
  final RatingData productRating;
  SetProductReviewSuccess(
      {required this.successMessage, required this.productRating});
}

class SetProductReviewFailure extends SetProductReviewState {
  final String errorMessage;

  SetProductReviewFailure(this.errorMessage);
}

class SetProductReviewCubit extends Cubit<SetProductReviewState> {
  final ProductRepository _productRepository = ProductRepository();

  SetProductReviewCubit() : super(SetProductReviewInitial());

  void setProductReview(
      {required Map<String, dynamic> params, required String apiUrl}) {
    emit(SetProductReviewInProgress());
    _productRepository
        .setProductReview(params: params, apiUrl: apiUrl)
        .then((value) => emit(SetProductReviewSuccess(
            successMessage: value.successMessage,
            productRating: value.productRating)))
        .catchError((e) {
      emit(SetProductReviewFailure(e.toString()));
    });
  }
}
