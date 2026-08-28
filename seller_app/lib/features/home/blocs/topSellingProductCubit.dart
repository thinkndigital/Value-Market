import 'package:eshopplus_seller/features/home/models/topSellingProduct.dart';
import 'package:eshopplus_seller/commons/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class TopSellingProductState {}

class TopSellingProductInitial extends TopSellingProductState {}

class TopSellingProductProgress extends TopSellingProductState {}

class TopSellingProductFailure extends TopSellingProductState {
  final String errorMessage;
  TopSellingProductFailure(this.errorMessage);
}

class TopSellingProductSuccess extends TopSellingProductState {
  final List<TopSellingProduct> topSellingProducts;
  TopSellingProductSuccess({required this.topSellingProducts});
}

class TopSellingProductCubit extends Cubit<TopSellingProductState> {
  final ProductRepository productRepository = ProductRepository();
  TopSellingProductCubit() : super(TopSellingProductInitial());
  void getTopSellingProducts({required Map<String, dynamic> params}) async {
    emit(TopSellingProductProgress());

    try {
      final result =
          await productRepository.getTopSellingProducts(params: params);
      if (!isClosed) emit(TopSellingProductSuccess(topSellingProducts: result));
    } catch (e) {
      if (!isClosed) emit(TopSellingProductFailure(e.toString()));
    }
  }
}
