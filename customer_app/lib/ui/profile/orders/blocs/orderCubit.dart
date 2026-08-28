import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/order.dart';
import '../repositories/orderRepository.dart';

abstract class OrdersState {}

class OrdersInitial extends OrdersState {}

class OrdersFetchInProgress extends OrdersState {
  OrdersFetchInProgress();
}

class OrdersFetchSuccess extends OrdersState {
  final int total;
  final List<Order> orders;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  OrdersFetchSuccess({
    required this.orders,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  OrdersFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<Order>? orders,
  }) {
    return OrdersFetchSuccess(
      orders: orders ?? this.orders,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class OrdersFetchFailure extends OrdersState {
  final String errorMessage;

  OrdersFetchFailure(this.errorMessage);
}

class OrdersCubit extends Cubit<OrdersState> {
  final OrderRepository _orderRepository = OrderRepository();

  OrdersCubit() : super(OrdersInitial());

  void getOrders({
    required int storeId,
    int? orderId,
    String? status,
    String? startDate,
    String? endDate,
    String? search,
  }) async {
    emit(OrdersFetchInProgress());
    try {
      final result = await _orderRepository.getOrders(
          storeId: storeId,
          id: orderId,
          status: status,
          startDate: startDate,
          endDate: endDate,
          search: search);
      emit(OrdersFetchSuccess(
        orders: result.orders,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
      ));
    } catch (e) {
      emit(OrdersFetchFailure(e.toString()));
    }
  }

  List<Order> getOrdersList() {
    if (state is OrdersFetchSuccess) {
      return (state as OrdersFetchSuccess).orders;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is OrdersFetchSuccess) {
      return (state as OrdersFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is OrdersFetchSuccess) {
      return (state as OrdersFetchSuccess).orders.length <
          (state as OrdersFetchSuccess).total;
    }
    return false;
  }

  void loadMore({
    required int storeId,
    int? orderId,
    String? status,
    String? startDate,
    String? endDate,
    String? search,
  }) async {
    if (state is OrdersFetchSuccess) {
      if ((state as OrdersFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as OrdersFetchSuccess).copyWith(fetchMoreInProgress: true));

        final moreOrders = await _orderRepository.getOrders(
            storeId: storeId,
            id: orderId,
            status: status,
            startDate: startDate,
            endDate: endDate,
            search: search,
            offset: (state as OrdersFetchSuccess).orders.length);

        final currentState = (state as OrdersFetchSuccess);

        List<Order> orders = currentState.orders;

        orders.addAll(moreOrders.orders);

        emit(OrdersFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreOrders.total,
          orders: orders,
        ));
      } catch (e) {
        emit((state as OrdersFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  addOrder(int orderId, BuildContext context) async {
    try {
      List<Order> orderData = [];
      int totalOrder = 0;

      ({
        List<Order> orders,
        int total,
      }) result = await OrderRepository().getOrders(
        storeId: context.read<StoresCubit>().getDefaultStore().id!,
        id: orderId,
      );
      Order order = result.orders.first;
      totalOrder = result.total;

      if (state is OrdersFetchSuccess) {
        OrdersFetchSuccess successState = state as OrdersFetchSuccess;
        orderData = successState.orders;

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

      emit((state as OrdersFetchSuccess)
          .copyWith(orders: orderData, total: totalOrder));
    } catch (e) {}
  }
}
