import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class MostSellingProductsState {}

class MostSellingProductsInitial extends MostSellingProductsState {}

class MostSellingProductsFetchInProgress extends MostSellingProductsState {}

class MostSellingProductsFetchSuccess extends MostSellingProductsState {
  final List<Product> products;

  MostSellingProductsFetchSuccess({
    required this.products,
  });
}

class MostSellingProductsFetchFailure extends MostSellingProductsState {
  final String errorMessage;

  MostSellingProductsFetchFailure(this.errorMessage);
}

class MostSellingProductsCubit extends Cubit<MostSellingProductsState> {
  MostSellingProductsCubit() : super(MostSellingProductsInitial()) {}

  void getMostSellingProducts(
      {required int storeId, required int userId, String? zipcode}) async {
    emit(MostSellingProductsFetchInProgress());
    try {
      final result = await ProductRepository().getMostSellingProducts(
          storeId: storeId, userId: userId, zipcode: zipcode);
      if (!isClosed) emit(MostSellingProductsFetchSuccess(products: result));
    } catch (e) {
      if (!isClosed) emit(MostSellingProductsFetchFailure(e.toString()));
    }
  }
}
