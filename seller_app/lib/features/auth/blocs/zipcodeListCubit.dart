import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../models/zipcode.dart';

abstract class ZipcodeListState {}

class ZipcodeListFetchInitial extends ZipcodeListState {}

class ZipcodeListFetchProgress extends ZipcodeListState {
  final List<Zipcode> oldZipcodeList;
  final bool isFirstFetch;
  final int currOffset;
  ZipcodeListFetchProgress(this.oldZipcodeList, this.currOffset,
      {this.isFirstFetch = false});
}

class ZipcodeListFetchSuccess extends ZipcodeListState {
  List<Zipcode> zipcodeList;
  final int currOffset;
  bool isLoadmore;
  ZipcodeListFetchSuccess(
      {required this.zipcodeList,
      required this.currOffset,
      required this.isLoadmore});
}

class ZipcodeListFetchFailure extends ZipcodeListState {
  final String errorMessage;
  ZipcodeListFetchFailure(this.errorMessage);
}

class ZipcodeListCubit extends Cubit<ZipcodeListState> {
  int offset = 0;
  bool isLoadmore = true;

  ZipcodeListCubit() : super(ZipcodeListFetchInitial());
  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(ZipcodeListFetchInitial());
  }

  setOldList(int moffset, List<Zipcode> splist, bool isloadmore) {
    offset = moffset;
    isLoadmore = isloadmore;
    if (!isClosed)
      emit(ZipcodeListFetchSuccess(
          zipcodeList: splist, currOffset: moffset, isLoadmore: isloadmore));
  }

  getZipcodeList(
      BuildContext context, Map<String, String?> parameter, bool isFetchZipcode,
      {bool isSetInitial = false}) {
    if (isSetInitial) {
      setInitialState();
    }

    if (state is ZipcodeListFetchProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <Zipcode>[];
    if (currentState is ZipcodeListFetchSuccess) {
      oldPosts = currentState.zipcodeList;
    }
    emit(ZipcodeListFetchProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    getZipcodeListProcess(context, parameter, isFetchZipcode).then((newPosts) {
      List<Zipcode> posts = [];
      if (offset != 0) {
        posts = (state as ZipcodeListFetchProgress).oldZipcodeList;
      }
      List<Zipcode> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => Zipcode.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      if (!isClosed)
        emit(ZipcodeListFetchSuccess(
            zipcodeList: posts,
            currOffset: curroffset,
            isLoadmore: isLoadmore));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(ZipcodeListFetchFailure(e.toString()));
    });
  }

  Future getZipcodeListProcess(BuildContext context,
      Map<String, String?> parameter, bool isFetchZipcode) async {
    try {
      final result = await Api.get(
          url: isFetchZipcode ? ApiURL.getZipcodes : ApiURL.getCities,
          useAuthToken: true,
          queryParameters: parameter);

      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
