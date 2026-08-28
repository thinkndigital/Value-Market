import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/api/apiService.dart';
import 'package:value_market_seller/core/configs/appConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/deliveryBoy.dart';

abstract class DeliveryBoyState {}

class DeliveryBoyInitial extends DeliveryBoyState {}

class DeliveryBoyProgress extends DeliveryBoyState {
  final List<DeliveryBoy> oldArchiveList;
  final bool isFirstFetch;
  final int currPage;
  DeliveryBoyProgress(this.oldArchiveList, this.currPage,
      {this.isFirstFetch = false});
}

class DeliveryBoySuccess extends DeliveryBoyState {
  List<DeliveryBoy> deliveryBoyList;
  final int currOffset;
  DeliveryBoySuccess({required this.deliveryBoyList, required this.currOffset});
}

class DeliveryBoyFailure extends DeliveryBoyState {
  final String errorMessage;
  DeliveryBoyFailure(this.errorMessage);
}

class DeliveryBoyCubit extends Cubit<DeliveryBoyState> {
  int offset = 0;
  bool isLoadmore = true;

  DeliveryBoyCubit() : super(DeliveryBoyInitial());
  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(DeliveryBoyInitial());
  }

  setOldList(
    int moffset,
    List<DeliveryBoy> splist,
  ) {
    offset = moffset;
    isLoadmore = true;

    emit(DeliveryBoySuccess(
      deliveryBoyList: splist,
      currOffset: moffset,
    ));
  }

  getDeliveryboyList(BuildContext context, Map<String, String?> parameter,
      {bool isSetInitial = false}) {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is DeliveryBoyProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <DeliveryBoy>[];
    if (currentState is DeliveryBoySuccess) {
      oldPosts = currentState.deliveryBoyList;
    }
    emit(DeliveryBoyProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    getDeliveryboyListProcess(context, parameter).then((newPosts) {
      List<DeliveryBoy> posts = [];
      if (offset != 0) {
        posts = (state as DeliveryBoyProgress).oldArchiveList;
      }
      List<DeliveryBoy> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => DeliveryBoy.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      emit(DeliveryBoySuccess(deliveryBoyList: posts, currOffset: curroffset));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(DeliveryBoyFailure(e.toString()));
    });
  }

  Future getDeliveryboyListProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getDeliveryBoys,
          useAuthToken: true,
          queryParameters: parameter);
      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
