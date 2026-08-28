import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../models/location.dart';

abstract class GetPickupLocationState {}

class GetPickupLocationInitial extends GetPickupLocationState {}

class GetPickupLocationProgress extends GetPickupLocationState {
  final List<Location> oldLocationList;
  final bool isFirstFetch;
  final int currPage;
  GetPickupLocationProgress(this.oldLocationList, this.currPage,
      {this.isFirstFetch = false});
}

class GetPickupLocationSuccess extends GetPickupLocationState {
  List<Location> locationList;
  final int currOffset;
  bool isLoadmore;
  GetPickupLocationSuccess(
      {required this.locationList,
      required this.currOffset,
      required this.isLoadmore});
}

class GetPickupLocationFailure extends GetPickupLocationState {
  final String errorMessage;
  GetPickupLocationFailure(this.errorMessage);
}

class GetPickupLocationCubit extends Cubit<GetPickupLocationState> {
  int offset = 0;
  bool isLoadmore = true;

  GetPickupLocationCubit() : super(GetPickupLocationInitial());
  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(GetPickupLocationInitial());
  }

  setOldList(int moffset, List<Location> splist, bool isloadmore) {
    offset = moffset;
    isLoadmore = isloadmore;

    emit(GetPickupLocationSuccess(
        locationList: splist, currOffset: moffset, isLoadmore: isloadmore));
  }

  getPickupLocation(BuildContext context, Map<String, String?> parameter,
      {bool isSetInitial = false}) {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is GetPickupLocationProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <Location>[];
    if (currentState is GetPickupLocationSuccess) {
      oldPosts = currentState.locationList;
    }
    emit(
        GetPickupLocationProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    getPickupLocationProcess(context, parameter).then((newPosts) {
      List<Location> posts = [];
      if (offset != 0) {
        posts = (state as GetPickupLocationProgress).oldLocationList;
      }
      List<Location> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => Location.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      emit(GetPickupLocationSuccess(
          locationList: posts, currOffset: curroffset, isLoadmore: isLoadmore));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(GetPickupLocationFailure(e.toString()));
    });
  }

  Future getPickupLocationProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getPickupLocation,
          useAuthToken: true,
          queryParameters: parameter);
      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
