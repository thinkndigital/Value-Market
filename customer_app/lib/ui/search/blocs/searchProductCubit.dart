import 'package:eshop_plus/ui/search/models/searchedProduct.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SearchProductState {}

class SearchProductInitial extends SearchProductState {}

class SearchProductFetchInProgress extends SearchProductState {}

class SearchProductFetchSuccess extends SearchProductState {
  final List<SearchedProduct> searchProducts;

  SearchProductFetchSuccess(this.searchProducts);
}

class SearchProductFetchFailure extends SearchProductState {
  final String errorMessage;

  SearchProductFetchFailure(this.errorMessage);
}

class SearchProductCubit extends Cubit<SearchProductState> {
  final ProductRepository _productRepository = ProductRepository();

  SearchProductCubit() : super(SearchProductInitial());

  void searchProducts({required int storeId, required String query}) {
    emit(SearchProductFetchInProgress());

    _productRepository
        .getSearchProducts(storeId: storeId, query: query)
        .then((value) => emit(SearchProductFetchSuccess(value)))
        .catchError((e) {
      emit(SearchProductFetchFailure(e.toString()));
    });
  }

  resetState() {
    emit(SearchProductInitial());
  }
}
