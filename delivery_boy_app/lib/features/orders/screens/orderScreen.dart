import 'dart:async';
import 'package:eshoppro_deliveryboy/core/constants/appConstants.dart';
import 'package:eshoppro_deliveryboy/features/orders/blocs/orderCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/models/parcel.dart';
import 'package:eshoppro_deliveryboy/core/localization/labelKeys.dart';
import 'package:eshoppro_deliveryboy/features/orders/widgets/orderListWidget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';

import '../../../utils/designConfig.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customAppbar.dart';
import '../../../commons/widgets/errorScreen.dart';

class OrderScreen extends StatefulWidget {
  const OrderScreen({Key? key}) : super(key: key);

  @override
  OrderScreenState createState() => OrderScreenState();
}

class OrderScreenState extends State<OrderScreen> {
  final scrollController = ScrollController();
  bool isSearching = false;
  Map<String, String>? apiParameter;
  List<Parcel> parcelList = [];
  int currOffset = 0;
  String awaiting = "0",
      received = "0",
      shipped = "0",
      delivered = "0",
      cancelled = "0",
      returned = "0",
      processed = "0";
  @override
  void initState() {
    super.initState();
    apiParameter = {};
    isSearching = false;
    setupScrollController(context);
    loadPage(isSetInitialPage: true);
    orderStatusFilter.remove(receivedStatusType);
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, dynamic> parameter = {};
    if (apiParameter != null) {
      parameter.addAll(apiParameter!);
    }
    BlocProvider.of<OrdersCubit>(context)
        .loadOrderList(parameter, isSetInitial: isSetInitialPage);
  }

  setupScrollController(context) {
    scrollController.addListener(() {
      if (scrollController.position.atEdge) {
        if (scrollController.position.pixels != 0) {
          loadPage();
        }
      }
    });
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(
        titleKey: myDeliveryKey,
        showBackButton: false,
      ),
      body: BlocBuilder<OrdersCubit, OrdersState>(
        builder: (context, state) {
          return Column(
            children: <Widget>[
/*OrderSearchBar(
filterCallback: filterOrder, mainFilterValue: apiParameter),
DesignConfig.defaultHeightSizedBox,
buildOrderSatusSection(state),*/
              DesignConfig.defaultHeightSizedBox,
              filterStatusListWidget(),
              DesignConfig.defaultHeightSizedBox,
              Expanded(child: contentWidget(state))
            ],
          );
        },
      ),
    );
  }

  filterOrder(Map<String, String> filterValue, {bool isClearFilter = false}) {
    apiParameter!.clear();
    if (!isClearFilter) {
      apiParameter!.addAll(filterValue);
    }
    loadPage(isSetInitialPage: true);
  }

  contentWidget(OrdersState state) {
    if (state is OrdersFetchInProgress && state.isFirstFetch) {
      return Utils.loadingIndicator();
    } else if (state is OrdersFetchFailure) {
      return ErrorScreen(
          text: state.errorMessage,
          onPressed: () => loadPage(isSetInitialPage: true));
    }
    return RefreshIndicator(onRefresh: refreshList, child: listContent(state));
  }

  Future<void> refreshList() async {
    await Future.delayed(const Duration(seconds: 2), () {
      loadPage(isSetInitialPage: true);
    });
  }

  listContent(OrdersState state) {
    parcelList = [];
    bool isLoading = false;
    if (state is OrdersFetchInProgress) {
      parcelList = state.oldParcelList;
      isLoading = true;
    } else if (state is OrdersFetchSuccess) {
      if (state.parcelList.isEmpty) {
        return ErrorScreen(onPressed: () {}, text: noDataFoundKey);
      }
      parcelList = state.parcelList;
    }

    return OrderListWidget(
        parcelList: parcelList,
        isLoading: isLoading,
        scrollController: scrollController,
        callback: (Parcel parcel) {
          updateOrderItem(parcel);
        });
  }

  updateOrderItem(Parcel parcel) {
    int index = parcelList.indexWhere((element) => element.id == parcel.id);
    if (index != -1) {
      parcelList[index] = parcel;
      BlocProvider.of<OrdersCubit>(context).setOldList(
          currOffset,
          parcelList,
          awaiting,
          received,
          shipped,
          delivered,
          cancelled,
          returned,
          processed);
    }
  }

  filterStatusListWidget() {
    List keys = orderStatusFilter.keys.toList();
    List currFilterStatus = [];
    if (apiParameter!.containsKey("active_status")) {
      currFilterStatus = apiParameter!["active_status"]!.split(",");
    }
    return SizedBox(
      height: 40,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: EdgeInsets.zero,
        separatorBuilder: (context, index) => const SizedBox(width: 8),
        itemCount: orderStatusFilter.length,
        itemBuilder: (context, index) {
          String statustype = keys[index];
          String lbl = orderStatusFilter[statustype] ?? "";
          bool isSelected =
              (statustype.trim().isEmpty && currFilterStatus.isEmpty) ||
                  currFilterStatus.contains(statustype);
          return Padding(
            padding: EdgeInsetsDirectional.only(
                start: index == 0 ? 8 : 0,
                end: index == (orderStatusFilter.length - 1) ? 8 : 0),
            child: ElevatedButton(
              onPressed: () {
                if (!isSelected) {
                  String filterstatus = statustype;
                  if (filterstatus.trim().isNotEmpty &&
                      currFilterStatus.isNotEmpty) {
                    currFilterStatus.clear();
                    currFilterStatus.add(statustype);

                    filterstatus = currFilterStatus.join(",");
                  }

                  filterOrder({"active_status": filterstatus},
                      isClearFilter: filterstatus.trim().isEmpty);
                }
              },
              style: ElevatedButton.styleFrom(
                textStyle: Theme.of(context).textTheme.bodyMedium,
                foregroundColor: isSelected
                    ? Colors.white
                    : Theme.of(context).textTheme.bodyMedium!.color!,
                backgroundColor: isSelected
                    ? Theme.of(context).primaryColor
                    : Theme.of(context).scaffoldBackgroundColor,
                shape: RoundedRectangleBorder(
                    side: BorderSide(
                        color: isSelected
                            ? Theme.of(context).primaryColor
                            : Theme.of(context).textTheme.bodyMedium!.color!),
                    borderRadius: BorderRadius.circular(30)),
              ),
              child: Text(context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: lbl)),
            ),
          );
        },
      ),
    );
  }
}
