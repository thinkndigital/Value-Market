import 'package:eshopplus_seller/features/orders/models/parcel.dart';
import 'package:eshopplus_seller/features/orders/repositories/orderRepository.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class ParcelState {}

class ParcelInitial extends ParcelState {}

class ParcelFetchInProgress extends ParcelState {}

class ParcelFetchSuccess extends ParcelState {
  final List<Parcel> parcels;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;
  final int total;

  ParcelFetchSuccess({
    required this.parcels,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  ParcelFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<Parcel>? parcels,
  }) {
    return ParcelFetchSuccess(
      parcels: parcels ?? this.parcels,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class ParcelFetchFailure extends ParcelState {
  final String errorMessage;

  ParcelFetchFailure(this.errorMessage);
}

class ParcelCubit extends Cubit<ParcelState> {
  final OrderRepository orderRepository = OrderRepository();

  ParcelCubit() : super(ParcelInitial());

  void getParcel({required Map<String, dynamic> params}) async {
    emit(ParcelFetchInProgress());
    try {
      final result = await orderRepository.getParcels(queryParameters: params);
      emit(ParcelFetchSuccess(
        parcels: result.parcels,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
      ));
    } catch (e) {
      emit(ParcelFetchFailure(e.toString()));
    }
  }

  List<Parcel> getParcelList() {
    if (state is ParcelFetchSuccess) {
      return (state as ParcelFetchSuccess).parcels;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is ParcelFetchSuccess) {
      return (state as ParcelFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is ParcelFetchSuccess) {
      return (state as ParcelFetchSuccess).parcels.length <
          (state as ParcelFetchSuccess).total;
    }
    return false;
  }

  void loadMore({required Map<String, dynamic> params}) async {
    if (state is ParcelFetchSuccess) {
      if ((state as ParcelFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as ParcelFetchSuccess).copyWith(fetchMoreInProgress: true));
        params.addAll({
          ApiURL.offsetApiKey: (state as ParcelFetchSuccess).parcels.length
        });
        final moreParcel =
            await orderRepository.getParcels(queryParameters: params);

        final currentState = (state as ParcelFetchSuccess);

        List<Parcel> parcels = currentState.parcels;

        parcels.addAll(moreParcel.parcels);

        emit(ParcelFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreParcel.total,
          parcels: parcels,
        ));
      } catch (e) {
        emit((state as ParcelFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  updateList(List<Parcel> list) {
    if (state is ParcelFetchSuccess) {
      emit((state as ParcelFetchSuccess).copyWith(parcels: list));
    } else {
      emit(ParcelFetchSuccess(
        parcels: list,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: list.length,
      ));
    }
  }
}
