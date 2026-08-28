import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../commons/models/product.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';

abstract class GetProductByTypeState {}

class GetProductByTypeListInitial extends GetProductByTypeState {}

class GetProductByTypeListProgress extends GetProductByTypeState {
  final List<Product> oldBrandList;
  final bool isFirstFetch;
  final int currOffset;
  GetProductByTypeListProgress(this.oldBrandList, this.currOffset,
      {this.isFirstFetch = false});
}

class GetProductByTypeListSuccess extends GetProductByTypeState {
  List<Product> productList;
  final String type;
  final int currOffset;
  bool isLoadmore;
  GetProductByTypeListSuccess(
      {required this.productList,
      required this.currOffset,
      required this.type,
      required this.isLoadmore});
}

class GetProductByTypeListFailure extends GetProductByTypeState {
  final String errorMessage;
  GetProductByTypeListFailure(this.errorMessage);
}

class GetProductByTypeCubit extends Cubit<GetProductByTypeState> {
  int offset = 0;
  bool isLoadmore = true;

  GetProductByTypeCubit() : super(GetProductByTypeListInitial());
  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(GetProductByTypeListInitial());
  }

  setOldList(int moffset, List<Product> splist, bool isloadmore, String mtype) {
    offset = moffset;
    isLoadmore = isloadmore;

    emit(GetProductByTypeListSuccess(
        productList: splist,
        currOffset: moffset,
        isLoadmore: isloadmore,
        type: mtype));
  }

  getType() {
    if (state is GetProductByTypeListSuccess) {
      return (state as GetProductByTypeListSuccess).type;
    }
    return "";
  }

  getProductList(BuildContext context, Map<String, String?> parameter,
      {bool isSetInitial = false}) {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is GetProductByTypeListProgress || !isLoadmore) return;
    final currentState = state;
    var oldPosts = <Product>[];
    if (currentState is GetProductByTypeListSuccess) {
      oldPosts = currentState.productList;
    }
    emit(GetProductByTypeListProgress(oldPosts, offset,
        isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    getProductListProcess(context, parameter).then((newPosts) {
      List<Product> posts = [];
      if (offset != 0) {
        posts = (state as GetProductByTypeListProgress).oldBrandList;
      }
      List<Product> neworderlist = [];
      List data = newPosts[ApiURL.dataKey];

      neworderlist.addAll(
          data.map((e) => Product.fromJson(e, isComboProduct: false)).toList());

      posts.addAll(neworderlist);
      int total = int.parse(newPosts[ApiURL.totalKey].toString());
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }
      emit(GetProductByTypeListSuccess(
          productList: posts,
          currOffset: curroffset,
          isLoadmore: isLoadmore,
          type: parameter["type"]!));
    }).catchError((e) {
      isLoadmore = false;

      if (offset == 0) emit(GetProductByTypeListFailure(e.toString()));
    });
  }

  Future getProductListProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getProducts,
          useAuthToken: true,
          queryParameters: parameter);

      return result;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
