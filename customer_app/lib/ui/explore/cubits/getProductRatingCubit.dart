import 'package:eshop_plus/commons/product/models/productRating.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ProductRatingState {}

class ProductRatingInitial extends ProductRatingState {}

class ProductRatingFetchInProgress extends ProductRatingState {}

class ProductRatingSuccess extends ProductRatingState {
  final int total;
  final ProductRating productRating;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  ProductRatingSuccess({
    required this.productRating,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  ProductRatingSuccess copyWith({
    int? total,
    ProductRating? productRating,
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
  }) {
    return ProductRatingSuccess(
      productRating: productRating ?? this.productRating,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class ProductRatingFailure extends ProductRatingState {
  final String errorMessage;

  ProductRatingFailure(this.errorMessage);
}

class ProductRatingCubit extends Cubit<ProductRatingState> {
  final ProductRepository _productRepository = ProductRepository();
  ProductRatingCubit() : super(ProductRatingInitial());

  Future<void> getProductRating({
    required Map<String, dynamic> params,
    required String apiUrl,
  }) async {
    try {
      emit(ProductRatingFetchInProgress());
      final result = await _productRepository.getProductRatings(
          params: params, apiUrl: apiUrl);
      emit(ProductRatingSuccess(
        productRating: result.productRating,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
      ));
    } catch (e) {
      emit(ProductRatingFailure(e.toString()));
    }
  }

  bool fetchMoreError() {
    if (state is ProductRatingSuccess) {
      return (state as ProductRatingSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is ProductRatingSuccess) {
      return (state as ProductRatingSuccess).productRating.ratingData.length <
          (state as ProductRatingSuccess).total;
    }
    return false;
  }

  void loadMore({
    required Map<String, dynamic> params,
    required String apiUrl,
  }) async {
    if (state is ProductRatingSuccess) {
      if ((state as ProductRatingSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as ProductRatingSuccess)
            .copyWith(fetchMoreInProgress: true));
        params.addAll({
          ApiURL.offsetApiKey:
              (state as ProductRatingSuccess).productRating.ratingData.length
        });
        final moreRating = await _productRepository.getProductRatings(
            params: params, apiUrl: apiUrl);

        final currentState = (state as ProductRatingSuccess);

        List<RatingData> ratings = currentState.productRating.ratingData;

        ratings.addAll(moreRating.productRating.ratingData);

        emit(ProductRatingSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreRating.total,
          productRating: moreRating.productRating,
        ));
      } catch (e) {
        emit((state as ProductRatingSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  resetState() {
    emit(ProductRatingInitial());
  }
}
