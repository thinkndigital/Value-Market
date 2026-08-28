import 'package:value_market_delivery_boy/core/constants/appConstants.dart';
import 'package:value_market_delivery_boy/features/orders/models/parcel.dart';
import 'package:value_market_delivery_boy/features/orders/repositories/orderRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:value_market_delivery_boy/core/configs/appConfig.dart';

abstract class OrdersState {}

class OrdersInitial extends OrdersState {}

class OrdersFetchInProgress extends OrdersState {
  final List<Parcel> oldParcelList;
  final bool isFirstFetch;
  final int currPage;
  OrdersFetchInProgress(this.oldParcelList, this.currPage,
      {this.isFirstFetch = false});
}

class OrdersFetchSuccess extends OrdersState {
  List<Parcel> parcelList;
  final int currOffset;
  String awaiting, received, shipped, delivered, cancelled, returned, processed;
  OrdersFetchSuccess(
      {required this.awaiting,
      required this.received,
      required this.shipped,
      required this.delivered,
      required this.cancelled,
      required this.returned,
      required this.processed,
      required this.parcelList,
      required this.currOffset});
  OrdersFetchSuccess copyWith({
    List<Parcel>? specialityList,
    int? currOffset,
    String? awaiting,
    received,
    shipped,
    delivered,
    cancelled,
    returned,
    processed,
  }) {
    return OrdersFetchSuccess(
        parcelList: specialityList ?? this.parcelList,
        currOffset: currOffset ?? this.currOffset,
        awaiting: awaiting ?? this.awaiting,
        received: received ?? this.received,
        shipped: shipped ?? this.shipped,
        delivered: delivered ?? this.delivered,
        cancelled: cancelled ?? this.cancelled,
        returned: returned ?? this.returned,
        processed: processed ?? this.processed);
  }
}

class OrdersFetchFailure extends OrdersState {
  final String errorMessage;

  OrdersFetchFailure(this.errorMessage);
}

class OrdersCubit extends Cubit<OrdersState> {
  final OrderRepository orderRepository;
  int offset = 0;
  bool isLoadmore = true;

  OrdersCubit(
    this.orderRepository,
  ) : super(OrdersInitial());

  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(OrdersInitial());
  }

  setOldList(
      int moffset,
      List<Parcel> splist,
      String mawaiting,
      String mreceived,
      String mshipped,
      String mdelivered,
      String mcancelled,
      String mreturned,
      String mprocessed) {
    offset = moffset;
    isLoadmore = true;

    emit(OrdersFetchSuccess(
        parcelList: splist,
        currOffset: moffset,
        awaiting: mawaiting,
        received: mreceived,
        shipped: mshipped,
        delivered: mdelivered,
        cancelled: mcancelled,
        returned: mreturned,
        processed: mprocessed));
  }

  void loadOrderList(Map<String, dynamic> parameter,
      {bool isSetInitial = false}) async {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is OrdersFetchInProgress || !isLoadmore) return;

    final currentState = state;
    var oldPosts = <Parcel>[];
    if (currentState is OrdersFetchSuccess) {
      oldPosts = currentState.parcelList;
    }
    emit(OrdersFetchInProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    orderRepository.getOrders(parameter).then((newPosts) {
      List<Parcel> posts = [];
      if (offset != 0) {
        posts = (state as OrdersFetchInProgress).oldParcelList;
      }
      List<Parcel> newParcelList = [];
      List data = newPosts['data'];
      newParcelList.addAll(data
          .map((e) => Parcel.fromJson(
              e,
              parameter.containsKey("active_status") &&
                  parameter["active_status"] == returnedStatusType))
          .toList());

      posts.addAll(newParcelList);
      int total = newPosts["total"];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }

      emit(OrdersFetchSuccess(
          awaiting: newPosts["awaiting"] ?? "0",
          received: newPosts["received"] ?? "0",
          shipped: newPosts["shipped"] ?? "0",
          delivered: newPosts["delivered"] ?? "0",
          cancelled: newPosts["cancelled"] ?? "0",
          returned: newPosts["returned"] ?? "0",
          processed: newPosts["processed"] ?? "0",
          parcelList: posts,
          currOffset: curroffset));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(OrdersFetchFailure(e.toString()));
    });
  }
}
