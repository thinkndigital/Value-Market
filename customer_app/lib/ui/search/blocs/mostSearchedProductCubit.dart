import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class MostSearchedProductState {}

class MostSearchedProductInitial extends MostSearchedProductState {}

class MostSearchedProductFetchInProgress extends MostSearchedProductState {}

class MostSearchedProductFetchSuccess extends MostSearchedProductState {
  final List<String> searchHistory;

  MostSearchedProductFetchSuccess(this.searchHistory);
}

class MostSearchedProductFetchFailure extends MostSearchedProductState {
  final String errorMessage;

  MostSearchedProductFetchFailure(this.errorMessage);
}

class MostSearchedProductCubit extends Cubit<MostSearchedProductState> {
  final ProductRepository _productRepository = ProductRepository();

  MostSearchedProductCubit() : super(MostSearchedProductInitial());

  void getMostSearchedProducts({required int storeId}) {
    emit(MostSearchedProductFetchInProgress());

    _productRepository
        .getMostSearchedProducts(storeId: storeId)
        .then((value) => emit(MostSearchedProductFetchSuccess(
            value.where((e) => e != 'null' && e != '').toList())))
        .catchError((e) {
      emit(MostSearchedProductFetchFailure(e.toString()));
    });
  }

  resetState() {
    emit(MostSearchedProductInitial());
  }
}
