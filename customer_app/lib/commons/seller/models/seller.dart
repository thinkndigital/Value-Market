import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/ui/favorites/repositories/favoritesRepository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../product/models/product.dart';

class Seller {
  int? sellerId;
  double? totalCommission;
  String? storeLogo;
  String? storeName;
  int? userId;
  double? rating;
  int? noOfRatings;
  String? storeThumbnail;
  String? sellerName;
  String? address;
  double? totalSales;
  int? totalProducts;
  String? storeUrl;
  String? email;
  String? mobile;
  String? slug;
  String? sellerRating;
  String? storeDescription;
  String? sellerProfile;
  List<Product>? products;
  String? isFavorite;
  bool? removeFavoriteInProgress;
  Seller(
      {this.sellerId,
      this.totalCommission,
      this.storeLogo,
      this.storeName,
      this.userId,
      this.rating,
      this.noOfRatings,
      this.storeThumbnail,
      this.sellerName,
      this.address,
      this.totalSales,
      this.totalProducts,
      this.email,
      this.mobile,
      this.slug,
      this.sellerRating,
      this.storeDescription,
      this.sellerProfile,
      this.isFavorite,
      this.products,
      this.removeFavoriteInProgress});

  Seller.fromJson(Map<String, dynamic> json) {
    sellerId = json['seller_id'];
    totalCommission = double.parse((json['total_commission'] ?? 0).toString());

    storeLogo = json['store_logo'];
    storeName = json['store_name'];
    userId = json['user_id'];
    storeUrl = json['store_url'];
    email = json['email'];
    mobile = json['mobile'];
    slug = json['slug'];
    sellerRating = (json['rating'] ?? 0).toString();
    rating = double.parse((json['rating'] ?? 0).toString());
    noOfRatings = json['no_of_ratings'];
    storeThumbnail = json['store_thumbnail'];
    sellerName = json['seller_name'];
    address = json['address'] ?? json['seller_address'];
    totalSales = double.parse((json['total_sales'] ?? 0).toString());

    totalProducts = int.parse((json['total_products'] ?? 0).toString());
    storeDescription = json['store_description'];
    sellerProfile = json['seller_profile'];
    isFavorite = json['is_favorite'].toString();
    removeFavoriteInProgress = false;
    products = [];
  }
  bool isFavoriteSeller(BuildContext context) {
    if (context.read<UserDetailsCubit>().isGuestUser()) {
      List fav = FavoritesRepository().getOfflineFavoriteIds();

      if (fav[2].contains(sellerId)) {
        return true;
      }
    }
    return isFavorite == "1";
  }

  setFavoriteSeller(bool value) {
    isFavorite = value ? "1" : "0";
  }
}
