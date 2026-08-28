import 'package:value_market_delivery_boy/core/constants/appConstants.dart';
import 'package:value_market_delivery_boy/features/orders/models/parcel.dart';
import 'package:value_market_delivery_boy/features/orders/repositories/orderRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class RecentOrdersState {}

class RecentOrdersInitial extends RecentOrdersState {}

class RecentOrdersFetchInProgress extends RecentOrdersState {
  RecentOrdersFetchInProgress();
}

class RecentOrdersFetchSuccess extends RecentOrdersState {
  List<Parcel> recentOrderList;

  String awaiting,
      received,
      shipped,
      delivered,
      cancelled,
      returned,
      processed,
      total;
  RecentOrdersFetchSuccess({
    required this.awaiting,
    required this.received,
    required this.shipped,
    required this.delivered,
    required this.cancelled,
    required this.returned,
    required this.processed,
    required this.total,
    required this.recentOrderList,
  });
}

class RecentOrdersFetchFailure extends RecentOrdersState {
  final String errorMessage;

  RecentOrdersFetchFailure(this.errorMessage);
}

class RecentOrdersCubit extends Cubit<RecentOrdersState> {
  final OrderRepository orderRepository;

  RecentOrdersCubit(
    this.orderRepository,
  ) : super(RecentOrdersInitial());

  setInitialState() {
    emit(RecentOrdersInitial());
  }

  void loadRecentOrders() async {
    emit(RecentOrdersFetchInProgress());
    Map<String, dynamic> parameter = {"offset": "0", "limit": "15"};

    orderRepository.getOrders(parameter).then((newPosts) {
      List<Parcel> neworderlist = [];
      List data = newPosts['data'];
      neworderlist.addAll(data
          .map((e) => Parcel.fromJson(
              e,
              parameter.containsKey("active_status") &&
                  parameter["active_status"] == returnedStatusType))
          .toList());

      emit(RecentOrdersFetchSuccess(
        awaiting: newPosts["awaiting"] ?? "0",
        received: newPosts["received"] ?? "0",
        shipped: newPosts["shipped"] ?? "0",
        delivered: newPosts["delivered"] ?? "0",
        cancelled: newPosts["cancelled"] ?? "0",
        returned: newPosts["returned"] ?? "0",
        processed: newPosts["processed"] ?? "0",
        total: newPosts["total"].toString(),
        recentOrderList: neworderlist,
      ));
    }).catchError((e) {
      emit(RecentOrdersFetchFailure(e.toString()));
    });
  }

  setOldList(
      List<Parcel> splist,
      String mawaiting,
      String mreceived,
      String mshipped,
      String mdelivered,
      String mcancelled,
      String mreturned,
      String total,
      String mprocessed) {
    emit(RecentOrdersFetchSuccess(
        recentOrderList: splist,
        awaiting: mawaiting,
        received: mreceived,
        shipped: mshipped,
        delivered: mdelivered,
        cancelled: mcancelled,
        returned: mreturned,
        total: total,
        processed: mprocessed));
  }
}
