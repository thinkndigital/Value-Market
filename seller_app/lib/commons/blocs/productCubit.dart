import 'package:value_market_seller/commons/models/product.dart';
import 'package:value_market_seller/commons/repositories/productRepository.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ProductsState {}

class ProductsInitial extends ProductsState {}

class ProductsFetchInProgress extends ProductsState {
  ProductsFetchInProgress();
}

class ProductsFetchSuccess extends ProductsState {
  final int total;
  final List<Product> products;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;
  final int? sellerId;

  ProductsFetchSuccess({
    required this.products,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
    this.sellerId,
  });

  ProductsFetchSuccess copyWith(
      {bool? fetchMoreError,
      bool? fetchMoreInProgress,
      int? total,
      List<Product>? products,
      int? sellerId}) {
    return ProductsFetchSuccess(
      sellerId: sellerId ?? this.sellerId,
      products: products ?? this.products,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class ProductsFetchFailure extends ProductsState {
  final String errorMessage;

  ProductsFetchFailure(this.errorMessage);
}

class ProductsCubit extends Cubit<ProductsState> {
  final ProductRepository productRepository = ProductRepository();

  ProductsCubit() : super(ProductsInitial());
  void getProducts(
      {required int storeId,
      int? productId,
      String? apiUrl,
      String? sortBy,
      String? orderBy,
      int? topRatedProduct,
      String? flag,
      String? type,
      bool? isComboProduct,
      int? showOnlyStockroducts,
      int? showOnlyActiveProducts,
      String? searchText}) async {
    emit(ProductsFetchInProgress());
    try {
      final result = await productRepository.getProducts(
          storeId: storeId,
          productId: productId,
          apiUrl: apiUrl,
          orderBy: orderBy,
          sortBy: sortBy,
          topRatedProduct: topRatedProduct,
          flag: flag,
          type: type,
          isComboProduct: isComboProduct ?? false,
          showOnlyStockroducts: showOnlyStockroducts,
          showOnlyActiveProducts: showOnlyActiveProducts,
          searchText: searchText);
      if (!isClosed)
        emit(ProductsFetchSuccess(
          products: result.products,
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: result.total,
        ));
    } catch (e) {
      if (!isClosed) emit(ProductsFetchFailure(e.toString()));
    }
  }

  List<Product> getProductsList() {
    if (state is ProductsFetchSuccess) {
      return (state as ProductsFetchSuccess).products;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is ProductsFetchSuccess) {
      return (state as ProductsFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is ProductsFetchSuccess) {
      return (state as ProductsFetchSuccess).products.length <
          (state as ProductsFetchSuccess).total;
    }
    return false;
  }

  void loadMore(
      {required int storeId,
      int? productId,
      String? sortBy,
      String? orderBy,
      int? topRatedProduct,
      String? flag,
      String? type,
      bool? isComboProduct,
      int? showOnlyStockroducts,
      int? showOnlyActiveProducts,
      String? searchText}) async {
    if (state is ProductsFetchSuccess) {
      if ((state as ProductsFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as ProductsFetchSuccess)
            .copyWith(fetchMoreInProgress: true));

        final moreProducts = await productRepository.getProducts(
            orderBy: orderBy,
            productId: productId,
            sortBy: sortBy,
            topRatedProduct: topRatedProduct,
            storeId: storeId,
            flag: flag,
            type: type,
            isComboProduct: isComboProduct ?? false,
            searchText: searchText,
            showOnlyStockroducts: showOnlyStockroducts,
            showOnlyActiveProducts: showOnlyActiveProducts,
            offset: (state as ProductsFetchSuccess).products.length);

        final currentState = (state as ProductsFetchSuccess);

        List<Product> products = currentState.products;

        products.addAll(moreProducts.products);
        if (!isClosed)
          emit(ProductsFetchSuccess(
            fetchMoreError: false,
            fetchMoreInProgress: false,
            total: moreProducts.total,
            products: products,
          ));
      } catch (e) {
        if (!isClosed)
          emit((state as ProductsFetchSuccess)
              .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  emitSuccessState(List<Product> products, int total) {
    emit((state as ProductsFetchSuccess)
        .copyWith(products: products, total: total));
  }

  updateProductDetails(Product newProduct) {
    if (state is ProductsFetchSuccess) {
      List<Product> products = (state as ProductsFetchSuccess).products;
      int index = products.indexWhere((element) => element.id == newProduct.id);
      if (index != -1) {
        products[index] = newProduct;
      } else {
        products.insert(0, newProduct);
      }

      emit((state as ProductsFetchSuccess).copyWith(products: products));
    } else {
      emit(ProductsFetchSuccess(
          products: [newProduct],
          total: 1,
          fetchMoreError: false,
          fetchMoreInProgress: false));
    }
  }

  setOldList(List<Product> list) {
    emit((state as ProductsFetchSuccess).copyWith(products: list));
  }
}
