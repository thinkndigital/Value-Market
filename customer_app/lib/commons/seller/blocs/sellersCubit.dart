import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/commons/seller/repositories/sellerRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SellersState {}

class SellersInitial extends SellersState {}

class SellersFetchInProgress extends SellersState {}

class SellersFetchSuccess extends SellersState {
  final int total;
  final List<Seller> sellers;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  SellersFetchSuccess(
      {required this.sellers,
      required this.fetchMoreError,
      required this.fetchMoreInProgress,
      required this.total});

  SellersFetchSuccess copyWith(
      {bool? fetchMoreError,
      bool? fetchMoreInProgress,
      int? total,
      List<Seller>? sellers}) {
    return SellersFetchSuccess(
        sellers: sellers ?? this.sellers,
        fetchMoreError: fetchMoreError ?? this.fetchMoreError,
        fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
        total: total ?? this.total);
  }
}

class SellersFetchFailure extends SellersState {
  final String errorMessage;

  SellersFetchFailure(this.errorMessage);
}

class SellersCubit extends Cubit<SellersState> {
  final SellerRepository _sellerRepository = SellerRepository();

  SellersCubit() : super(SellersInitial());

  void getSellers({required int storeId, List<int>? sellerIds}) async {
    emit(SellersFetchInProgress());
    try {
      final result = await _sellerRepository.getSellers(
        storeId: storeId,
        sellerIds: sellerIds,
      );
      if (!isClosed)
        emit(SellersFetchSuccess(
            sellers: result.sellers,
            fetchMoreError: false,
            fetchMoreInProgress: false,
            total: result.total));
    } catch (e) {
      if (!isClosed) emit(SellersFetchFailure(e.toString()));
    }
  }

  bool hasMore() {
    if (state is SellersFetchSuccess) {
      return (state as SellersFetchSuccess).sellers.length <
          (state as SellersFetchSuccess).total;
    }
    return false;
  }

  void loadMore({required int storeId, List<int>? sellerIds}) async {
    if (state is SellersFetchSuccess) {
      if ((state as SellersFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit(
            (state as SellersFetchSuccess).copyWith(fetchMoreInProgress: true));

        final moreSellers = await _sellerRepository.getSellers(
            storeId: storeId,
            sellerIds: sellerIds,
            offset: (state as SellersFetchSuccess).sellers.length);

        final currentState = (state as SellersFetchSuccess);

        List<Seller> sellers = currentState.sellers;

        sellers.addAll(moreSellers.sellers);

        emit(SellersFetchSuccess(
            fetchMoreError: false,
            fetchMoreInProgress: false,
            total: moreSellers.total,
            sellers: sellers));
      } catch (e) {
        emit((state as SellersFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }
}
