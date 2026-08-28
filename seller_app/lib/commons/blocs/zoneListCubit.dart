import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../models/zone.dart';

abstract class ZoneListState {}

class ZoneListFetchInitial extends ZoneListState {}

class ZoneListFetchProgress extends ZoneListState {
  final List<Zone> oldBrandList;
  final bool isFirstFetch;
  final int currOffset;
  ZoneListFetchProgress(this.oldBrandList, this.currOffset,
      {this.isFirstFetch = false});
}

class ZoneListFetchSuccess extends ZoneListState {
  List<Zone> brandList;
  final int currOffset;
  bool isLoadmore;
  ZoneListFetchSuccess(
      {required this.brandList,
      required this.currOffset,
      required this.isLoadmore});
}

class ZoneListFetchFailure extends ZoneListState {
  final String errorMessage;
  ZoneListFetchFailure(this.errorMessage);
}

class ZoneListCubit extends Cubit<ZoneListState> {
  int offset = 0;
  bool isLoadmore = true;

  ZoneListCubit() : super(ZoneListFetchInitial());
  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(ZoneListFetchInitial());
  }

  setOldList(int moffset, List<Zone> splist, bool isloadmore) {
    offset = moffset;
    isLoadmore = isloadmore;

    emit(ZoneListFetchSuccess(
        brandList: splist, currOffset: moffset, isLoadmore: isloadmore));
  }

  getZoneList(BuildContext context, Map<String, String?> parameter,
      {bool isSetInitial = false}) async {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is ZoneListFetchProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <Zone>[];
    if (currentState is ZoneListFetchSuccess) {
      oldPosts = currentState.brandList;
    }
    emit(ZoneListFetchProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    getZoneListProcess(context, parameter).then((newPosts) {
      List<Zone> posts = [];
      if (offset != 0) {
        posts = (state as ZoneListFetchProgress).oldBrandList;
      }
      List<Zone> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => Zone.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      emit(ZoneListFetchSuccess(
          brandList: posts, currOffset: curroffset, isLoadmore: isLoadmore));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(ZoneListFetchFailure(e.toString()));
    });
  }

  Future getZoneListProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getZones, useAuthToken: true, queryParameters: parameter);
      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
