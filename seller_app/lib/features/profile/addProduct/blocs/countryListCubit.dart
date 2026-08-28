import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/country.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';

abstract class CountryListState {}

class CountryListFetchInitial extends CountryListState {}

class CountryListFetchProgress extends CountryListState {
  final List<Country> oldCountryList;
  final bool isFirstFetch;
  final int currOffset;
  CountryListFetchProgress(this.oldCountryList, this.currOffset,
      {this.isFirstFetch = false});
}

class CountryListFetchSuccess extends CountryListState {
  List<Country> countryList;
  final int currOffset;
  bool isLoadmore;
  CountryListFetchSuccess(
      {required this.countryList,
      required this.currOffset,
      required this.isLoadmore});
}

class CountryListFetchFailure extends CountryListState {
  final String errorMessage;
  CountryListFetchFailure(this.errorMessage);
}

class CountryListCubit extends Cubit<CountryListState> {
  int offset = 0;
  bool isLoadmore = true;

  CountryListCubit() : super(CountryListFetchInitial());
  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(CountryListFetchInitial());
  }

  setOldList(int moffset, List<Country> splist, bool isloadmore) {
    offset = moffset;
    isLoadmore = isloadmore;

    emit(CountryListFetchSuccess(
        countryList: splist, currOffset: moffset, isLoadmore: isloadmore));
  }

  getCountryList(BuildContext context, Map<String, String?> parameter,
      {bool isSetInitial = false}) {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is CountryListFetchProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <Country>[];
    if (currentState is CountryListFetchSuccess) {
      oldPosts = currentState.countryList;
    }
    emit(CountryListFetchProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    getCountryListProcess(context, parameter).then((newPosts) {
      List<Country> posts = [];
      if (offset != 0) {
        posts = (state as CountryListFetchProgress).oldCountryList;
      }
      List<Country> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => Country.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      emit(CountryListFetchSuccess(
          countryList: posts, currOffset: curroffset, isLoadmore: isLoadmore));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(CountryListFetchFailure(e.toString()));
    });
  }

  Future getCountryListProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getCountryData,
          useAuthToken: true,
          queryParameters: parameter);

      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
