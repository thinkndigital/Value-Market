import 'package:eshop_plus/commons/product/models/filterAttribute.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/product/repositories/productRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ProductsState {}

class ProductsInitial extends ProductsState {}

class ProductsFetchInProgress extends ProductsState {
  final int? sellerId;
  ProductsFetchInProgress(this.sellerId);
}

class ProductsFetchSuccess extends ProductsState {
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

  ProductsFetchSuccess(
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

  ProductsFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<Product>? products,
    double? minPrice,
    double? maxPrice,
    List<FilterAttribute>? filterAttributes,
    int? sellerId,
    String? categoryIds,
    String? brandIds,
  }) {
    return ProductsFetchSuccess(
      sellerId: sellerId ?? this.sellerId,
      products: products ?? this.products,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
      minPrice: minPrice ?? this.minPrice,
      maxPrice: maxPrice ?? this.maxPrice,
      categoryIds: categoryIds ?? this.categoryIds,
      brandIds: brandIds ?? this.brandIds,
      filterAttributes: filterAttributes ?? this.filterAttributes,
    );
  }
}

class ProductsFetchFailure extends ProductsState {
  final String errorMessage;
  final int? sellerId;
  ProductsFetchFailure(this.errorMessage, this.sellerId);
}

// New: Multi-seller state
class ProductsMultiSellerState extends ProductsState {
  final Map<int, ProductsState> sellerStates;
  ProductsMultiSellerState({required this.sellerStates});

  ProductsMultiSellerState copyWith({
    Map<int, ProductsState>? sellerStates,
  }) {
    return ProductsMultiSellerState(
      sellerStates: sellerStates ?? this.sellerStates,
    );
  }
}

class ProductsCubit extends Cubit<ProductsState> {
  final ProductRepository productRepository = ProductRepository();

  ProductsCubit() : super(ProductsInitial());

  // For multi-seller (BestSellerSection)
  void setSellerState(int sellerId, ProductsState sellerState) {
    final current = state is ProductsMultiSellerState
        ? (state as ProductsMultiSellerState).sellerStates
        : <int, ProductsState>{};
    final updated = Map<int, ProductsState>.from(current);
    updated[sellerId] = sellerState;
    emit(ProductsMultiSellerState(sellerStates: updated));
  }

  ProductsState? getSellerState(int sellerId) {
    if (state is ProductsMultiSellerState) {
      return (state as ProductsMultiSellerState).sellerStates[sellerId];
    }
    return null;
  }

  // For single-seller screens (legacy)
  void getProducts({
    required int storeId,
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
    int? productId,
    bool? isComboProduct,
    String? searchText,
    String? zipcode,
  }) async {
    emit(ProductsFetchInProgress(sellerId));
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
        productId: productId,
        isComboProduct: isComboProduct ?? false,
        searchText: searchText,
        zipcode: zipcode,
      );
      if (!isClosed)
        emit(ProductsFetchSuccess(
          products: result.products,
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: result.total,
          sellerId: sellerId,
          filterAttributes: result.filterAttributes,
          minPrice: result.minPrice,
          maxPrice: result.maxPrice,
          categoryIds: result.categoryIds,
          brandIds: result.brandIds,
        ));
    } catch (e) {
      if (!isClosed) emit(ProductsFetchFailure(e.toString(), sellerId));
    }
  }

  // For multi-seller: update a single seller's products
  void setProductsForSeller(int sellerId, ProductsFetchSuccess success) {
    setSellerState(sellerId, success);
  }

  void setFailureForSeller(int sellerId, String error) {
    setSellerState(sellerId, ProductsFetchFailure(error, sellerId));
  }

  // Helper for BestSellerSection
  ProductsFetchSuccess? getProductsSuccessForSeller(int sellerId) {
    final s = getSellerState(sellerId);
    if (s is ProductsFetchSuccess) return s;
    return null;
  }

  ProductsFetchFailure? getProductsFailureForSeller(int sellerId) {
    final s = getSellerState(sellerId);
    if (s is ProductsFetchFailure) return s;
    return null;
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

  List<FilterAttribute> filterAttributes() {
    if (state is ProductsFetchSuccess) {
      return (state as ProductsFetchSuccess).filterAttributes;
    }
    return [];
  }

  double minPrice() {
    if (state is ProductsFetchSuccess) {
      return (state as ProductsFetchSuccess).minPrice;
    }
    return 0;
  }

  double maxPrice() {
    if (state is ProductsFetchSuccess) {
      return (state as ProductsFetchSuccess).maxPrice;
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
      int? productId,
      bool? isComboProduct,
      String? searchText,
      String? zipcode}) async {
    if (state is ProductsFetchSuccess) {
      if ((state as ProductsFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as ProductsFetchSuccess)
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
            productId: productId,
            isComboProduct: isComboProduct ?? false,
            searchText: searchText,
            zipcode: zipcode,
            offset: (state as ProductsFetchSuccess).products.length);

        final currentState = (state as ProductsFetchSuccess);

        List<Product> products = currentState.products;

        products.addAll(moreProducts.products);

        emit(ProductsFetchSuccess(
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
        emit((state as ProductsFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  setFavoriteProduct(int productId, bool value) async {
    List<Product> products = (state as ProductsFetchSuccess).products;
    int index = products.indexWhere((element) => element.id == productId);
    if (index != -1) {
      products[index].isFavorite = value ? "1" : "0";
      emit((state as ProductsFetchSuccess).copyWith(products: products));
    }
    emit((state as ProductsFetchSuccess).copyWith(products: products));
  }

  updateProductDetails(Product newProduct) {
    if (state is ProductsFetchSuccess) {
      List<Product> products = (state as ProductsFetchSuccess).products;
      int index = products.indexWhere((element) => element.id == newProduct.id);
      if (index != -1) {
        products[index] = newProduct;
      }

      emit((state as ProductsFetchSuccess).copyWith(products: products));
    }
  }
}
