import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/order.dart';
import '../repositories/orderRepository.dart';

abstract class OrdersState {}

class OrdersInitial extends OrdersState {}

class OrdersFetchInProgress extends OrdersState {
  final List<Order> oldArchiveList;
  final bool isFirstFetch;
  final int currPage;
  OrdersFetchInProgress(this.oldArchiveList, this.currPage,
      {this.isFirstFetch = false});
}

class OrdersFetchSuccess extends OrdersState {
  List<Order> specialityList;
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
      required this.specialityList,
      required this.currOffset});
  OrdersFetchSuccess copyWith({
    List<Order>? specialityList,
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
        specialityList: specialityList ?? this.specialityList,
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
      List<Order> splist,
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
        specialityList: splist,
        currOffset: moffset,
        awaiting: mawaiting,
        received: mreceived,
        shipped: mshipped,
        delivered: mdelivered,
        cancelled: mcancelled,
        returned: mreturned,
        processed: mprocessed));
  }

  void loadPosts(Map<String, dynamic> parameter,
      {bool isSetInitial = false}) async {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is OrdersFetchInProgress || !isLoadmore) return;

    final currentState = state;
    var oldPosts = <Order>[];
    if (currentState is OrdersFetchSuccess) {
      oldPosts = currentState.specialityList;
    }
    emit(OrdersFetchInProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    orderRepository.getOrders(parameter).then((newPosts) {
      List<Order> posts = [];
      if (offset != 0) {
        posts = (state as OrdersFetchInProgress).oldArchiveList;
      }
      List<Order> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => Order.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
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
          specialityList: posts,
          currOffset: curroffset));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(OrdersFetchFailure(e.toString()));
    });
  }

  emitSuccessState({
    required List<Order> orderData,
    required int totalOrder,
    required bool hasMore,
  }) {
    emit((state as OrdersFetchSuccess).copyWith(
      specialityList: orderData,
    ));
  }

  addOrder(int storeId, String orderId, BuildContext context) async {
    try {
      List<Order> orderData = [];
      int totalOrder = 0;
      bool hasMore = false;
      Map result = await OrderRepository().getOrders({
        ApiURL.storeIdApiKey: storeId,
        ApiURL.idApiKey: orderId,
      });
      Order order =
          result[ApiURL.dataKey].map((e) => Order.fromJson(e)).toList().first;
      totalOrder = result[ApiURL.totalKey];
      int curroffset = offset;

      if (state is OrdersFetchSuccess) {
        OrdersFetchSuccess successState = state as OrdersFetchSuccess;
        orderData = successState.specialityList;

        int index = orderData.indexWhere((element) => element.id == order.id);
        if (index != -1) {
          orderData[index] = order;
        } else {
          orderData.insert(0, order);
          totalOrder++;
        }
      } else {
        orderData.add(order);
        totalOrder++;
      }
      if (orderData.length < totalOrder) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }

      emit(OrdersFetchSuccess(
          awaiting: result["awaiting"] ?? "0",
          received: result["received"] ?? "0",
          shipped: result["shipped"] ?? "0",
          delivered: result["delivered"] ?? "0",
          cancelled: result["cancelled"] ?? "0",
          returned: result["returned"] ?? "0",
          processed: result["processed"] ?? "0",
          specialityList: orderData,
          currOffset: curroffset));
      emitSuccessState(
          orderData: orderData, totalOrder: totalOrder, hasMore: hasMore);
    } catch (e) {}
  }
}
