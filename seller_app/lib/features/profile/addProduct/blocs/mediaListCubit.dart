import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/media.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import '../../../../commons/blocs/storesCubit.dart';

abstract class MediaListState {}

class MediaListFetchInitial extends MediaListState {}

class MediaListFetchProgress extends MediaListState {
  final List<Media> oldMediaList;
  final bool isFirstFetch;
  final int currOffset;
  MediaListFetchProgress(this.oldMediaList, this.currOffset,
      {this.isFirstFetch = false});
}

class MediaListFetchSuccess extends MediaListState {
  List<Media> mediaList;
  final int currOffset;
  String mediatype;
  bool isLoadmore;
  MediaListFetchSuccess(
      {required this.mediaList,
      required this.currOffset,
      required this.mediatype,
      required this.isLoadmore});
}

class MediaListFetchFailure extends MediaListState {
  final String errorMessage;
  MediaListFetchFailure(this.errorMessage);
}

class MediaListCubit extends Cubit<MediaListState> {
  int offset = 0;
  bool isLoadmore = true;

  MediaListCubit() : super(MediaListFetchInitial());
  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(MediaListFetchInitial());
  }

  setOldList(int moffset, List<Media> splist, bool isloadmore, String mtype) {
    offset = moffset;
    isLoadmore = isloadmore;

    emit(MediaListFetchSuccess(
        mediaList: splist,
        currOffset: moffset,
        isLoadmore: isloadmore,
        mediatype: mtype));
  }

  getMediaList(BuildContext context, Map<String, String?> parameter,
      {bool isSetInitial = false}) {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is MediaListFetchProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <Media>[];
    if (currentState is MediaListFetchSuccess) {
      oldPosts = currentState.mediaList;
    }
    emit(MediaListFetchProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();
    parameter["order"] = "DESC";
    parameter[ApiURL.storeIdApiKey] =
        context.read<StoresCubit>().getDefaultStore().id.toString();

    getMediaListProcess(context, parameter).then((newPosts) {
      List<Media> posts = [];
      if (offset != 0) {
        posts = (state as MediaListFetchProgress).oldMediaList;
      }
      List<Media> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];
      neworderlist.addAll(data.map((e) => Media.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      emit(MediaListFetchSuccess(
          mediaList: posts,
          currOffset: curroffset,
          isLoadmore: isLoadmore,
          mediatype: parameter["type"] ?? ""));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(MediaListFetchFailure(e.toString()));
    });
  }

  Future getMediaListProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getMedia, useAuthToken: true, queryParameters: parameter);
      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
