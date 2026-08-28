import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/ui/favorites/models/offlineFavorite.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/ui/favorites/repositories/favoritesRepository.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hive/hive.dart';

import '../../../commons/product/models/product.dart';

abstract class FavoritesState {}

class FavoritesInitial extends FavoritesState {}

class FavoritesFetchInProgress extends FavoritesState {
  FavoritesFetchInProgress();
}

class FavoritesFetchSuccess extends FavoritesState {
  final int totalProducts;
  final List<Product> products;
  final bool fetchMoreProductsError;
  final bool fetchMoreProductsInProgress;
  final int totalSellers;
  final List<Seller> sellers;
  final bool fetchMoreSellersError;
  final bool fetchMoreSellersInProgress;

  FavoritesFetchSuccess({
    required this.products,
    required this.fetchMoreProductsError,
    required this.fetchMoreProductsInProgress,
    required this.totalProducts,
    required this.sellers,
    required this.fetchMoreSellersError,
    required this.fetchMoreSellersInProgress,
    required this.totalSellers,
  });

  FavoritesFetchSuccess copyWith({
    bool? fetchMoreProductsError,
    bool? fetchMoreProductsInProgress,
    int? totalProducts,
    List<Product>? products,
    bool? fetchMoreSellersError,
    bool? fetchMoreSellersInProgress,
    int? totalSellers,
    List<Seller>? sellers,
  }) {
    return FavoritesFetchSuccess(
      products: products ?? this.products,
      fetchMoreProductsError:
          fetchMoreProductsError ?? this.fetchMoreProductsError,
      fetchMoreProductsInProgress:
          fetchMoreProductsInProgress ?? this.fetchMoreProductsInProgress,
      totalProducts: totalProducts ?? this.totalProducts,
      sellers: sellers ?? this.sellers,
      fetchMoreSellersError:
          fetchMoreSellersError ?? this.fetchMoreSellersError,
      fetchMoreSellersInProgress:
          fetchMoreSellersInProgress ?? this.fetchMoreSellersInProgress,
      totalSellers: totalSellers ?? this.totalSellers,
    );
  }
}

class FavoritesFetchFailure extends FavoritesState {
  final String errorMessage;

  FavoritesFetchFailure(this.errorMessage);
}

class FavoritesCubit extends Cubit<FavoritesState> {
  final FavoritesRepository _favoritesRepository = FavoritesRepository();

  FavoritesCubit() : super(FavoritesInitial());

  void getFavorites(
      {required int storeId, required BuildContext context}) async {
    emit(FavoritesFetchInProgress());

    if (context.read<UserDetailsCubit>().isGuestUser()) {
      var result = await _favoritesRepository.getOfflineFavorites(storeId);

      emit(FavoritesFetchSuccess(
        products: result.products,
        fetchMoreProductsError: false,
        fetchMoreProductsInProgress: false,
        totalProducts: result.productsTotal,
        sellers: result.sellers,
        fetchMoreSellersError: false,
        fetchMoreSellersInProgress: false,
        totalSellers: result.sellersTotal,
      ));
    } else {
      try {
        var result;
        if (!context.read<UserDetailsCubit>().isGuestUser()) {
          result = await _favoritesRepository.getFavorites(storeId: storeId);
        }
        emit(FavoritesFetchSuccess(
          products: result.products,
          fetchMoreProductsError: false,
          fetchMoreProductsInProgress: false,
          totalProducts: result.productsTotal,
          sellers: result.sellers,
          fetchMoreSellersError: false,
          fetchMoreSellersInProgress: false,
          totalSellers: result.sellersTotal,
        ));
      } catch (e) {
        emit(FavoritesFetchFailure(e.toString()));
      }
    }
  }

  List<Product> getFavoritesProductList() {
    if (state is FavoritesFetchSuccess) {
      return (state as FavoritesFetchSuccess).products;
    }
    return [];
  }

  bool fetchMoreProductsError() {
    if (state is FavoritesFetchSuccess) {
      return (state as FavoritesFetchSuccess).fetchMoreProductsError;
    }
    return false;
  }

  bool hasMoreProducts() {
    if (state is FavoritesFetchSuccess) {
      return (state as FavoritesFetchSuccess).products.length <
          (state as FavoritesFetchSuccess).totalProducts;
    }
    return false;
  }

  bool fetchMoreSellersError() {
    if (state is FavoritesFetchSuccess) {
      return (state as FavoritesFetchSuccess).fetchMoreSellersError;
    }
    return false;
  }

  bool hasMoreSellers() {
    if (state is FavoritesFetchSuccess) {
      return (state as FavoritesFetchSuccess).sellers.length <
          (state as FavoritesFetchSuccess).totalSellers;
    }
    return false;
  }

  void loadMoreProducts({
    required int storeId,
  }) async {
    if (state is FavoritesFetchSuccess) {
      if ((state as FavoritesFetchSuccess).fetchMoreProductsInProgress) {
        return;
      }
      try {
        emit((state as FavoritesFetchSuccess)
            .copyWith(fetchMoreProductsInProgress: true));

        final moreFavorites = await _favoritesRepository.getFavorites(
            storeId: storeId,
            productOffset: (state as FavoritesFetchSuccess).products.length);

        final currentState = (state as FavoritesFetchSuccess);

        List<Product> products = currentState.products;

        products.addAll(moreFavorites.products);

        emit((state as FavoritesFetchSuccess).copyWith(
          fetchMoreProductsError: false,
          fetchMoreProductsInProgress: false,
          totalProducts: moreFavorites.productsTotal,
          products: products,
        ));
      } catch (e) {
        emit((state as FavoritesFetchSuccess).copyWith(
            fetchMoreProductsInProgress: false, fetchMoreProductsError: true));
      }
    }
  }

  void loadMoreSellers({
    required int storeId,
  }) async {
    if (state is FavoritesFetchSuccess) {
      if ((state as FavoritesFetchSuccess).fetchMoreSellersInProgress) {
        return;
      }
      try {
        emit((state as FavoritesFetchSuccess)
            .copyWith(fetchMoreSellersInProgress: true));

        final moreFavorites = await _favoritesRepository.getFavorites(
            storeId: storeId,
            sellerOffset: (state as FavoritesFetchSuccess).sellers.length);

        final currentState = (state as FavoritesFetchSuccess);

        List<Seller> sellers = currentState.sellers;

        sellers.addAll(moreFavorites.sellers);
        emit((state as FavoritesFetchSuccess).copyWith(
          fetchMoreProductsError: false,
          fetchMoreProductsInProgress: false,
          totalSellers: moreFavorites.sellersTotal,
          sellers: sellers,
        ));
      } catch (e) {
        emit((state as FavoritesFetchSuccess).copyWith(
            fetchMoreSellersInProgress: false, fetchMoreProductsError: true));
      }
    }
  }

  emitProductSuccessState(List<Product> products, int totalProducts) {
    emit((state as FavoritesFetchSuccess)
        .copyWith(products: products, totalProducts: totalProducts - 1));
  }

  emitSelletSuccessState(List<Seller> sellers, int totalSellers) {
    emit((state as FavoritesFetchSuccess)
        .copyWith(sellers: sellers, totalSellers: totalSellers - 1));
  }

  // Store favorite locally (either product or seller)
  void addFavorite(OfflineFavorite favorite) async {
    var box = Hive.box(favoritesBoxKey);
    box.put(favorite.id, favorite);
  }
}
