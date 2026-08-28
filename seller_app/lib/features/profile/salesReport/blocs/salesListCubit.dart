import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/features/profile/salesReport/models/salesReport.dart';
import 'package:eshopplus_seller/features/profile/salesReport/repositories/salesRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SalesListState {}

class SalesListInitial extends SalesListState {}

class SalesListFetchProgress extends SalesListState {
  final List<SalesReport> oldArchiveList;
  final bool isFirstFetch;
  final int currPage;
  SalesListFetchProgress(this.oldArchiveList, this.currPage,
      {this.isFirstFetch = false});
}

class SalesListFetchSuccess extends SalesListState {
  List<SalesReport> specialityList;
  String totalOrder, grandTotal, totalDeliveryCharge, grandFinalTotal;
  final int currOffset;
  SalesListFetchSuccess(
      {required this.specialityList,
      required this.currOffset,
      required this.grandTotal,
      required this.totalOrder,
      required this.totalDeliveryCharge,
      required this.grandFinalTotal});
}

class SalesListFetchFailure extends SalesListState {
  final String errorMessage;

  SalesListFetchFailure(this.errorMessage);
}

class SalesListCubit extends Cubit<SalesListState> {
  final SalesRepository salesRepository;
  int offset = 0;
  bool isLoadmore = true;

  SalesListCubit(
    this.salesRepository,
  ) : super(SalesListInitial());

  setInitialState() {
    offset = 0;
    isLoadmore = true;
    emit(SalesListInitial());
  }

  setOldList(
      int moffset,
      List<SalesReport> splist,
      String mgrandTotal,
      String mtotalDeliveryCharge,
      String mgrandFinalTotal,
      String mtotalOrder) {
    offset = moffset;
    isLoadmore = true;

    emit(SalesListFetchSuccess(
        specialityList: splist,
        currOffset: moffset,
        totalOrder: mtotalOrder,
        grandFinalTotal: mgrandFinalTotal,
        grandTotal: mgrandTotal,
        totalDeliveryCharge: mtotalDeliveryCharge));
  }

  void loadPosts(Map<String, String?> parameter,
      {bool isSetInitial = false}) async {
    if (isSetInitial) {
      setInitialState();
    }
    if (state is SalesListFetchProgress || !isLoadmore) return;

    final currentState = state;
    var oldPosts = <SalesReport>[];
    if (currentState is SalesListFetchSuccess) {
      oldPosts = currentState.specialityList;
    }
    emit(SalesListFetchProgress(oldPosts, offset, isFirstFetch: offset == 0));
    parameter["offset"] = offset.toString();
    parameter["limit"] = loadLimit.toString();

    salesRepository.getSales(parameter).then((newPosts) {
      List<SalesReport> posts = [];
      if (offset != 0) {
        posts = (state as SalesListFetchProgress).oldArchiveList;
      }
      List<SalesReport> neworderlist = [];
      List data = newPosts['rows'];
      neworderlist.addAll(data.map((e) => SalesReport.fromJson(e)).toList());

      posts.addAll(neworderlist);
      int total = newPosts[ApiURL.totalKey];
      int curroffset = offset;
      if (posts.length < total) {
        offset = offset + loadLimit;
        isLoadmore = true;
      } else {
        isLoadmore = false;
      }

      emit(SalesListFetchSuccess(
        specialityList: posts,
        currOffset: curroffset,
        totalOrder: newPosts[ApiURL.totalKey].toString(),
        grandFinalTotal: newPosts["grand_final_total"],
        grandTotal: newPosts["grand_total"],
        totalDeliveryCharge: newPosts["total_delivery_charge"],
      ));
    }).catchError((e) {
      isLoadmore = false;
      if (offset == 0) emit(SalesListFetchFailure(e.toString()));
    });
  }
}
