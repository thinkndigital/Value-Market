import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/ui/favorites/models/offlineFavorite.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/ui/favorites/repositories/favoritesRepository.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hive/hive.dart';

abstract class RemoveFavoriteState {}

class RemoveFavoriteInitial extends RemoveFavoriteState {}

class RemoveFavoriteInProgress extends RemoveFavoriteState {
  final List<Product>? products;
  final List<Seller>? sellers;
  RemoveFavoriteInProgress(this.products, this.sellers);
}

class RemoveFavoriteSuccess extends RemoveFavoriteState {
  final int id;

  final String successMessage;
  RemoveFavoriteSuccess({
    required this.id,
    required this.successMessage,
  });
}

class RemoveFavoriteFailure extends RemoveFavoriteState {
  final int id;
  final String errorMessage;
  RemoveFavoriteFailure({required this.id, required this.errorMessage});
}

class RemoveFavoriteCubit extends Cubit<RemoveFavoriteState> {
  final FavoritesRepository _favoritesRepository = FavoritesRepository();
  RemoveFavoriteCubit() : super(RemoveFavoriteInitial());

  void removeFavorite(
      {required Map<String, dynamic> params,
      required BuildContext context,
      List<Product>? products,
      List<Seller>? sellers}) async {
    if (products != null && products.isNotEmpty) {
      products
          .firstWhere((element) => element.id == params[ApiURL.productIdApiKey])
          .removeFavoriteInProgress = true;
    }
    if (sellers != null && sellers.isNotEmpty) {
      sellers
          .firstWhere(
              (element) => element.sellerId == params[ApiURL.sellerIdApiKey])
          .removeFavoriteInProgress = true;
    }
    try {
      var result;
      emit(RemoveFavoriteInProgress(products, sellers));
      if (context.read<UserDetailsCubit>().isGuestUser()) {
        products != null && products.isNotEmpty
            ? removeOfflineFavorite(OfflineFavorite(
                id: params[ApiURL.productIdApiKey],
                productType: params[ApiURL.productTypeApiKey],
                type: 'product'))
            : removeOfflineFavorite(OfflineFavorite(
                id: params[ApiURL.sellerIdApiKey],
                productType: '',
                type: 'seller'));
        result = 'Removed from Favorites';
      } else {
        result =
            await _favoritesRepository.removeFavoriteProduct(params: params);
      }
      emit(RemoveFavoriteSuccess(
        id: products != null
            ? params[ApiURL.productIdApiKey]
            : params[ApiURL.sellerIdApiKey],
        successMessage: result,
      ));
    } catch (e) {
      emit(RemoveFavoriteFailure(
          id: products != null
              ? params[ApiURL.productIdApiKey]
              : params[ApiURL.sellerIdApiKey],
          errorMessage: e.toString()));
    }
  }

  void removeOfflineFavorite(OfflineFavorite favorite) async {
    var box = await Hive.openBox(favoritesBoxKey);

    // Check if the favorite exists in the local storage
    if (box.containsKey(favorite.id)) {
      await box.delete(favorite.id); // Remove the favorite by id
    }
  }
}
