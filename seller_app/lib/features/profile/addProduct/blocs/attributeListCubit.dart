import 'package:eshopplus_seller/commons/models/mainAttribute.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../repositories/attributeRepository.dart';

abstract class AttributeListState {}

class AttributeListFetchInitial extends AttributeListState {}

class AttributeListFetchProgress extends AttributeListState {
  final List<MainAttribute> oldAttributeList;
  final bool isFirstFetch;
  final int currOffset;
  AttributeListFetchProgress(this.oldAttributeList, this.currOffset,
      {this.isFirstFetch = false});
}

class AttributeListFetchSuccess extends AttributeListState {
  List<MainAttribute> attributeList;
  final int currOffset;
  bool isLoadmore;
  AttributeListFetchSuccess(
      {required this.attributeList,
      required this.currOffset,
      required this.isLoadmore});
}

class AttributeListFetchFailure extends AttributeListState {
  final String errorMessage;
  AttributeListFetchFailure(this.errorMessage);
}

class AttributeListCubit extends Cubit<AttributeListState> {
  final AttributeRepository attributeRepository;
  int offset = 0;
  bool isLoadmore = true;

  AttributeListCubit(
    this.attributeRepository,
  ) : super(AttributeListFetchInitial());

  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(AttributeListFetchInitial());
  }

  setOldList(int moffset, List<MainAttribute> splist, bool isloadmore) {
    offset = moffset;
    isLoadmore = isloadmore;

    emit(AttributeListFetchSuccess(
        attributeList: splist, currOffset: moffset, isLoadmore: isloadmore));
  }

  getAttributeList(BuildContext context, Map<String, String?> parameter,
      {bool isSetInitial = false}) {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is AttributeListFetchProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <MainAttribute>[];
    if (currentState is AttributeListFetchSuccess) {
      oldPosts = currentState.attributeList;
    }
    emit(AttributeListFetchProgress(oldPosts, offset,
        isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    attributeRepository.getAttributeListProcess(parameter).then((newPosts) {
      List<MainAttribute> posts = [];
      if (offset != 0) {
        posts = (state as AttributeListFetchProgress).oldAttributeList;
      }
      List<MainAttribute> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => MainAttribute.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      emit(AttributeListFetchSuccess(
          attributeList: posts,
          currOffset: curroffset,
          isLoadmore: isLoadmore));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(AttributeListFetchFailure(e.toString()));
    });
  }
}
