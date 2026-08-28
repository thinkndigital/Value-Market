import 'package:eshop_plus/commons/product/models/filterAttribute.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ComboProductsState {}

class ComboProductsInitial extends ComboProductsState {}

class ComboProductsFetchInProgress extends ComboProductsState {
  final int? sellerId;
  ComboProductsFetchInProgress(this.sellerId);
}

class ComboProductsFetchSuccess extends ComboProductsState {
  final int total;
  final List<Product> products;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;
  final int? sellerId;
  final double minPrice;
  final double maxPrice;
  final List<FilterAttribute> filterAttributes;
  String? categoryIds;
  String? brandIds;

  ComboProductsFetchSuccess(
      {required this.products,
      required this.fetchMoreError,
      required this.fetchMoreInProgress,
      required this.total,
      this.sellerId,
      required this.minPrice,
      required this.maxPrice,
      required this.filterAttributes,
      required this.categoryIds,
      required this.brandIds});

  ComboProductsFetchSuccess copyWith(
      {bool? fetchMoreError,
      bool? fetchMoreInProgress,
      int? total,
      List<Product>? products,
      double? minPrice,
      double? maxPrice,
      List<FilterAttribute>? filterAttributes,
      String? categoryIds,
      String? brandIds,
      int? sellerId}) {
    return ComboProductsFetchSuccess(
        sellerId: sellerId ?? this.sellerId,
        products: products ?? this.products,
        fetchMoreError: fetchMoreError ?? this.fetchMoreError,
        fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
        total: total ?? this.total,
        minPrice: minPrice ?? this.minPrice,
        maxPrice: maxPrice ?? this.maxPrice,
        categoryIds: categoryIds ?? this.categoryIds,
        brandIds: brandIds ?? this.brandIds,
        filterAttributes: filterAttributes ?? this.filterAttributes);
  }
}

class ComboProductsFetchFailure extends ComboProductsState {
  final String errorMessage;
  final int? sellerId;
  ComboProductsFetchFailure(this.errorMessage, this.sellerId);
}

class ComboProductsCubit extends Cubit<ComboProductsState> {
  final ProductRepository productRepository = ProductRepository();

  ComboProductsCubit() : super(ComboProductsInitial()) {}
  void getProducts(
      {required int storeId,
      String? apiUrl,
      String? sortBy,
      String? orderBy,
      int? topRatedProduct,
      int? sellerId,
      String? categoryIds,
      String? brandIds,
      List<int>? attributeValueIds,
      String? discount,
      String? rating,
      double? minPrice,
      double? maxPrice,
      List<int>? productIds,
      bool? isComboProduct,
      String? searchText,
      String? zipcode}) async {
    emit(ComboProductsFetchInProgress(sellerId));
    try {
      final result = await productRepository.getProducts(
          storeId: storeId,
          apiUrl: apiUrl,
          orderBy: orderBy,
          sortBy: sortBy,
          sellerId: sellerId,
          topRatedProduct: topRatedProduct,
          categoryIds: categoryIds,
          brandIds: brandIds,
          attributeValueIds: attributeValueIds,
          discount: discount,
          rating: rating,
          maxPrice: maxPrice,
          minPrice: minPrice,
          productIds: productIds,
          isComboProduct: isComboProduct ?? false,
          searchText: searchText,
          zipcode: zipcode);
      if (!isClosed)
        emit(ComboProductsFetchSuccess(
            products: result.products,
            fetchMoreError: false,
            fetchMoreInProgress: false,
            total: result.total,
            sellerId: sellerId,
            filterAttributes: result.filterAttributes,
            minPrice: result.minPrice,
            maxPrice: result.maxPrice,
            categoryIds: result.categoryIds,
            brandIds: result.brandIds));
      // ProductRepository().setProducts(result.products);
    } catch (e) {
      if (!isClosed) emit(ComboProductsFetchFailure(e.toString(), sellerId));
    }
  }

  List<Product> getProductsList() {
    if (state is ComboProductsFetchSuccess) {
      return (state as ComboProductsFetchSuccess).products;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is ComboProductsFetchSuccess) {
      return (state as ComboProductsFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is ComboProductsFetchSuccess) {
      return (state as ComboProductsFetchSuccess).products.length <
          (state as ComboProductsFetchSuccess).total;
    }
    return false;
  }

  List<FilterAttribute> filterAttributes() {
    if (state is ComboProductsFetchSuccess) {
      return (state as ComboProductsFetchSuccess).filterAttributes;
    }
    return [];
  }

  double minPrice() {
    if (state is ComboProductsFetchSuccess) {
      return (state as ComboProductsFetchSuccess).minPrice;
    }
    return 0;
  }

  double maxPrice() {
    if (state is ComboProductsFetchSuccess) {
      return (state as ComboProductsFetchSuccess).maxPrice;
    }
    return 0;
  }

  void loadMore(
      {required int storeId,
      String? sortBy,
      String? orderBy,
      int? topRatedProduct,
      int? sellerId,
      String? categoryIds,
      String? brandIds,
      List<int>? attributeValueIds,
      String? discount,
      String? rating,
      double? minPrice,
      double? maxPrice,
      List<int>? productIds,
      bool? isComboProduct,
      String? searchText,
      String? zipcode}) async {
    if (state is ComboProductsFetchSuccess) {
      if ((state as ComboProductsFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as ComboProductsFetchSuccess)
            .copyWith(fetchMoreInProgress: true));

        final moreProducts = await productRepository.getProducts(
            orderBy: orderBy,
            sortBy: sortBy,
            topRatedProduct: topRatedProduct,
            storeId: storeId,
            sellerId: sellerId,
            attributeValueIds: attributeValueIds,
            brandIds: brandIds,
            categoryIds: categoryIds,
            discount: discount,
            rating: rating,
            minPrice: minPrice,
            maxPrice: maxPrice,
            productIds: productIds,
            isComboProduct: isComboProduct ?? false,
            searchText: searchText,
            offset: (state as ComboProductsFetchSuccess).products.length,
            zipcode: zipcode);

        final currentState = (state as ComboProductsFetchSuccess);

        List<Product> products = currentState.products;

        products.addAll(moreProducts.products);

        emit(ComboProductsFetchSuccess(
            fetchMoreError: false,
            fetchMoreInProgress: false,
            total: moreProducts.total,
            sellerId: sellerId,
            products: products,
            filterAttributes: moreProducts.filterAttributes,
            minPrice: moreProducts.minPrice,
            maxPrice: moreProducts.maxPrice,
            categoryIds: moreProducts.categoryIds,
            brandIds: moreProducts.brandIds));
      } catch (e) {
        emit((state as ComboProductsFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  setFavoriteProduct(int productId, bool value) async {
    List<Product> products = (state as ComboProductsFetchSuccess).products;
    int index = products.indexWhere((element) => element.id == productId);
    if (index != -1) {
      products[index].isFavorite = value ? "1" : "0";
      emit((state as ComboProductsFetchSuccess).copyWith(products: products));
    }
    emit((state as ComboProductsFetchSuccess).copyWith(products: products));
  }

  updateProductDetails(Product newProduct) {
    if (state is ComboProductsFetchSuccess) {
      List<Product> products = (state as ComboProductsFetchSuccess).products;
      int index = products.indexWhere((element) => element.id == newProduct.id);
      if (index != -1) {
        products[index] = newProduct;
      }

      emit((state as ComboProductsFetchSuccess).copyWith(products: products));
    }
  }
}
