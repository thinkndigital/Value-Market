import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/brand.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';

abstract class BrandListState {}

class BrandListFetchInitial extends BrandListState {}

class BrandListFetchProgress extends BrandListState {
  final List<Brand> oldBrandList;
  final bool isFirstFetch;
  final int currOffset;
  BrandListFetchProgress(this.oldBrandList, this.currOffset,
      {this.isFirstFetch = false});
}

class BrandListFetchSuccess extends BrandListState {
  List<Brand> brandList;
  final int currOffset;
  bool isLoadmore;
  BrandListFetchSuccess(
      {required this.brandList,
      required this.currOffset,
      required this.isLoadmore});
}

class BrandListFetchFailure extends BrandListState {
  final String errorMessage;
  BrandListFetchFailure(this.errorMessage);
}

class BrandListCubit extends Cubit<BrandListState> {
  int offset = 0;
  bool isLoadmore = true;

  BrandListCubit() : super(BrandListFetchInitial());
  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(BrandListFetchInitial());
  }

  setOldList(int moffset, List<Brand> splist, bool isloadmore) {
    offset = moffset;
    isLoadmore = isloadmore;

    emit(BrandListFetchSuccess(
        brandList: splist, currOffset: moffset, isLoadmore: isloadmore));
  }

  getBrandList(BuildContext context, Map<String, String?> parameter,
      {bool isSetInitial = false}) {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is BrandListFetchProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <Brand>[];
    if (currentState is BrandListFetchSuccess) {
      oldPosts = currentState.brandList;
    }
    emit(BrandListFetchProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    getBrandListProcess(context, parameter).then((newPosts) {
      List<Brand> posts = [];
      if (offset != 0) {
        posts = (state as BrandListFetchProgress).oldBrandList;
      }
      List<Brand> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => Brand.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      emit(BrandListFetchSuccess(
          brandList: posts, currOffset: curroffset, isLoadmore: isLoadmore));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(BrandListFetchFailure(e.toString()));
    });
  }

  Future getBrandListProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getBrandList,
          useAuthToken: true,
          queryParameters: parameter);
      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
