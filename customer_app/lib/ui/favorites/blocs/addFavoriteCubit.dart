import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/ui/favorites/models/offlineFavorite.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/ui/favorites/repositories/favoritesRepository.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/constants/hiveConstants.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hive/hive.dart';

abstract class AddFavoriteState {}

class AddFavoriteInitial extends AddFavoriteState {}

class AddFavoriteProgress extends AddFavoriteState {}

class AddFavoriteSuccess extends AddFavoriteState {
  final String successMessage;
  final int id;
  AddFavoriteSuccess({
    required this.successMessage,
    required this.id,
  });
}

class AddFavoriteFailure extends AddFavoriteState {
  final int id;
  final String errorMessage;

  AddFavoriteFailure(this.errorMessage, this.id);
}

class AddFavoriteCubit extends Cubit<AddFavoriteState> {
  final FavoritesRepository _favoritesRepository = FavoritesRepository();

  AddFavoriteCubit() : super(AddFavoriteInitial());

  void addToFavorites(
      {required Map<String, dynamic> params,
      required BuildContext context,
      List<Product>? products,
      List<Seller>? sellers,
      OfflineFavorite? favorite}) async {
    emit(AddFavoriteProgress());
    var result;
    try {
      // Store favorite locally (either product or seller)
      if (context.read<UserDetailsCubit>().isGuestUser()) {
        var box = await Hive.openBox(favoritesBoxKey);
        box.put(favorite!.id, favorite.toMap());
        emit(AddFavoriteSuccess(
          successMessage: 'Added in Favorites',
          id: favorite.id,
        ));
      } else {
        result = await _favoritesRepository.addFavoriteProduct(params: params);
        emit(AddFavoriteSuccess(
          successMessage: result,
          id: products != null
              ? params[ApiURL.productIdApiKey]
              : params[ApiURL.sellerIdApiKey],
        ));
      }
    } catch (e) {
      emit(AddFavoriteFailure(
        e.toString(),
        products != null
            ? params[ApiURL.productIdApiKey]
            : params[ApiURL.sellerIdApiKey],
      ));
    }
  }
}
