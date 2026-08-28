import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/profile/orders/blocs/updateOrderCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customImageWidget.dart';
import 'package:value_market_customer/commons/widgets/customSearchContainer.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/commons/widgets/error_screen.dart';
import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/utils/dateTimeUtils.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../blocs/orderCubit.dart';
import '../../../../commons/blocs/storesCubit.dart';
import '../models/order.dart';
import '../../../../utils/utils.dart';
import '../../../../commons/widgets/customBottomButtonContainer.dart';
import '../../../../commons/widgets/customLabelContainer.dart';
import '../../../../commons/widgets/customRoundedButton.dart';
import '../../../../commons/widgets/customTextButton.dart';

class MyOrderScreen extends StatefulWidget {
  const MyOrderScreen({Key? key}) : super(key: key);

  static Widget getRouteInstance() => MyOrderScreen();
  @override
  MyOrderScreenState createState() => MyOrderScreenState();
}

class MyOrderScreenState extends State<MyOrderScreen> {
  final _searchController = TextEditingController();
  var prevVal;
  final List<String> _selectedRadio = [allKey, anytimeKey];

  String status = allKey;
  String? startApiDate, endApiDate;
  Map statusList = Map.from(orderStatusTypes);
  @override
  void initState() {
    super.initState();

    statusList.remove(awaitingStatusType);
    statusList.remove(receivedStatusType);
    statusList.remove(processedStatusType);
    Future.delayed(Duration.zero, () {
      getMyOrders();
    });
  }

  getMyOrders({String searchval = ""}) {
    context.read<OrdersCubit>().getOrders(
        storeId: context.read<StoresCubit>().getDefaultStore().id!,
        status: status,
        startDate: startApiDate,
        endDate: endApiDate,
        search: searchval);
  }

  void loadMoreMyOrders({String searchval = ""}) {
    context.read<OrdersCubit>().loadMore(
        storeId: context.read<StoresCubit>().getDefaultStore().id!,
        status: status,
        startDate: startApiDate,
        endDate: endApiDate,
        search: searchval);
  }

  @override
  void dispose() {
    super.dispose();
    _searchController.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(titleKey: myOrdersKey),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          buildSearchAndFilterSection(),
          Expanded(
            child: BlocBuilder<OrdersCubit, OrdersState>(
              builder: (context, state) {
                if (state is OrdersFetchSuccess) {
                  return NotificationListener<ScrollUpdateNotification>(
                    onNotification: (notification) {
                      if (notification.metrics.pixels >=
                          notification.metrics.maxScrollExtent) {
                        if (context.read<OrdersCubit>().hasMore()) {
                          loadMoreMyOrders();
                        }
                      }
                      return true;
                    },
                    child: BlocListener<UpdateOrderCubit, UpdateOrderState>(
                      listener: (context, updatestate) {
                        if (updatestate is UpdateOrderFetchSuccess) {
                          final index = state.orders.indexWhere(
                              (element) => element.id == updatestate.order.id);

                          if (index != -1) {
                            state.orders[index] = updatestate.order;
                          }
                          setState(() {});
                        }
                      },
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (status != allKey)
                            Padding(
                              padding: const EdgeInsetsDirectional.symmetric(
                                  vertical: 12,
                                  horizontal: appContentHorizontalPadding),
                              child: CustomTextContainer(
                                  textKey: '${status.capitalizeFirst} Orders',
                                  style:
                                      Theme.of(context).textTheme.titleMedium),
                            )
                          else
                            DesignConfig.smallHeightSizedBox,
                          Expanded(
                            child: RefreshIndicator(
                              onRefresh: () async {
                                getMyOrders();
                              },
                              child: Container(
                                color: Theme.of(context)
                                    .colorScheme
                                    .primaryContainer,
                                child: ListView.separated(
                                  separatorBuilder: (context, index) => Divider(
                                    color: Theme.of(context)
                                        .inputDecorationTheme
                                        .iconColor,
                                  ),
                                  itemCount: state.orders.length,
                                  shrinkWrap: true,
                                  itemBuilder: (context, index) {
                                    if (context.read<OrdersCubit>().hasMore()) {
                                      if (index == state.orders.length - 1) {
                                        if (context
                                            .read<OrdersCubit>()
                                            .fetchMoreError()) {
                                          return Center(
                                            child: CustomTextButton(
                                                buttonTextKey: retryKey,
                                                onTapButton: () {
                                                  loadMoreMyOrders();
                                                }),
                                          );
                                        }

                                        return Center(
                                          child:
                                              CustomCircularProgressIndicator(
                                                  indicatorColor:
                                                      Theme.of(context)
                                                          .colorScheme
                                                          .primary),
                                        );
                                      }
                                    }

                                    if (state
                                        .orders[index].orderItems!.isNotEmpty) {
                                      return ListView.separated(
                                        separatorBuilder: (context, index) =>
                                            Divider(
                                          color: Theme.of(context)
                                              .inputDecorationTheme
                                              .iconColor,
                                        ),
                                        shrinkWrap: true,
                                        itemCount: state
                                            .orders[index].orderItems!.length,
                                        physics:
                                            const NeverScrollableScrollPhysics(),
                                        itemBuilder: (context, i) {
                                          return buildOrderContainer(
                                              state.orders[index],
                                              state.orders[index]
                                                  .orderItems![i]);
                                        },
                                      );
                                    }
                                    return const SizedBox.shrink();
                                  },
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                }
                if (state is OrdersFetchFailure) {
                  return ErrorScreen(
                      onPressed: getMyOrders,
                      text: state.errorMessage,
                      image: state.errorMessage == noInternetKey
                          ? "no_internet"
                          : "no_order",
                      child: state is OrdersFetchInProgress
                          ? CustomCircularProgressIndicator(
                              indicatorColor:
                                  Theme.of(context).colorScheme.primary,
                            )
                          : null);
                }
                return Center(
                  child: CustomCircularProgressIndicator(
                      indicatorColor: Theme.of(context).colorScheme.primary),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  buildOrderContainer(Order order, OrderItems orderItem) {
   
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.orderDetailsScreen,
          arguments: {
            'order': order,
            'selectedItemId': orderItem.id,
          }),
      child: Container(
          padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
          color: Theme.of(context).colorScheme.primaryContainer,
          child: Row(
            children: <Widget>[
              CustomImageWidget(
                  url: orderItem.image ?? "",
                  width: 86,
                  height: 100,
                  borderRadius: 8),
              DesignConfig.smallWidthSizedBox,
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    CustomTextContainer(
                      textKey: orderItem.productName ?? "",
                      maxLines: 2,
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                    DesignConfig.smallHeightSizedBox,
                    Text.rich(
                      TextSpan(
                        style: Theme.of(context).textTheme.bodyMedium!,
                        children: [
                          TextSpan(
                            text: context
                                .read<SettingsAndLanguagesCubit>()
                                .getTranslatedValue(
                                    labelKey: orderItem.activeStatus!),
                          ),
                          const TextSpan(
                            text: ' ',
                          ),
                          if (orderItem.activeStatus == deliveredStatusType &&
                              orderItem.status!.indexWhere((element) =>
                                      element.status == deliveredStatusType) !=
                                  -1)
                            TextSpan(
                                text: DateTimeUtils.formatDate(
                                    orderItem.status!
                                        .firstWhere(
                                          (element) =>
                                              element.status ==
                                              deliveredStatusType,
                                        )
                                        .timestamp
                                        .split(' ')[0],
                                    'dd MMMM, yyyy')),
                        ],
                      ),
                      textAlign: TextAlign.center,
                    )
                  ],
                ),
              ),
              const Icon(
                Icons.arrow_forward_ios,
                size: 24,
              )
            ],
          )),
    );
  }

  buildSearchAndFilterSection() {
    return Container(
      color: Theme.of(context).colorScheme.primaryContainer,
      padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
      child: Row(
        children: <Widget>[
          Expanded(
            flex: 5,
            child: CustomSearchContainer(
                textEditingController: _searchController,
                autoFocus: false,
                showVoiceIcon: false,
                hintTextKey: searchAllOrdersKey,
                suffixWidget: IconButton(
                  icon: Icon(
                    Icons.close,
                    color: Theme.of(context).colorScheme.secondary,
                  ),
                  onPressed: () {
                    setState(() {
                      _searchController.clear();
                      getMyOrders();
                      FocusScope.of(context).unfocus();
                    });
                  },
                ),
                onChanged: (val) {
                  searchChange(val);
                }),
          ),
          GestureDetector(
            onTap: () => Utils.openModalBottomSheet(context, buildFilterList(),
                staticContent: true, isScrollControlled: true),
            child: Container(
              margin: const EdgeInsetsDirectional.only(start: 8),
              padding: const EdgeInsetsDirectional.all(12),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                    width: 1,
                    color: Theme.of(context).inputDecorationTheme.iconColor!),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    Icons.filter_list,
                    size: 24,
                    color: Theme.of(context)
                        .colorScheme
                        .secondary
                        .withValues(alpha: 0.8),
                  ),
                  DesignConfig.defaultWidthSizedBox,
                  CustomTextContainer(
                    textKey: filterKey,
                    style: Theme.of(context).textTheme.bodyLarge!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.8),
                        ),
                  )
                ],
              ),
            ),
          )
        ],
      ),
    );
  }

  searchChange(String val) {
    if (val.trim() != prevVal) {
      Future.delayed(Duration.zero, () {
        prevVal = val.trim();
        Future.delayed(const Duration(seconds: 1), () {
          getMyOrders(searchval: val);
        });
      });
    }
  }

  Widget buildFilterList() {
    return StatefulBuilder(
        builder: (BuildContext buildcontext, StateSetter setState) {
      return SafeArea(
        child: Container(
          constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * (0.75)),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Padding(
                  padding: const EdgeInsetsDirectional.all(
                      appContentHorizontalPadding),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      CustomTextContainer(
                        textKey: statusKey,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      // create radio buttons for all status plus 'all'
                      buildRadioListTile(0, allKey, allKey, setState),
                      ...List.generate(statusList.length, (index) {
                        return buildRadioListTile(
                            0,
                            statusList.values.elementAt(index),
                            statusList.keys.elementAt(index),
                            setState);
                      }),
                    ],
                  ),
                ),
                Divider(
                  color: Theme.of(context).inputDecorationTheme.iconColor,
                  thickness: 0.5,
                ),
                DesignConfig.defaultHeightSizedBox,
                Padding(
                  padding: const EdgeInsetsDirectional.symmetric(
                      horizontal: appContentVerticalSpace),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      CustomTextContainer(
                        textKey: typeKey,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      buildRadioListTile(1, anytimeKey, anytimeKey, setState),
                      buildRadioListTile(
                          1, last30DaysKey, last30DaysKey, setState),
                      buildRadioListTile(
                          1, lastSixMonthsKey, lastSixMonthsKey, setState),
                      buildRadioListTile(1, lastYearKey, lastYearKey, setState),
                    ],
                  ),
                ),
                buildFilterButtons(),
              ],
            ),
          ),
        ),
      );
    });
  }

  buildRadioListTile(
      int index, String title, String value, StateSetter setState) {
    return RadioListTile(
      contentPadding: EdgeInsetsDirectional.zero,
      visualDensity: const VisualDensity(horizontal: -4, vertical: -2),
      title: CustomLabelContainer(
        textKey: title,
        isFieldValueMandatory: false,
      ),

      value: value, // Assign a value of 1 to this option
      groupValue: _selectedRadio[
          index], // Use _selectedValue to track the selected option
      onChanged: (value) {
        setState(() {
          _selectedRadio[index] =
              value!; // Update _selectedValue when option 1 is selected
          if (index == 0) {
            status = value;
          }
          if (index == 1) {
            if (value == anytimeKey) {
              startApiDate = null;
              endApiDate = null;
            } else {
              DateTime endDate = DateTime.now();
              DateTime startDate = DateTime.now();

              switch (value) {
                case last30DaysKey:
                  startDate = endDate.subtract(const Duration(days: 30));
                  break;
                case lastSixMonthsKey:
                  startDate =
                      DateTime(endDate.year, endDate.month - 6, endDate.day);
                  break;
                case lastYearKey:
                  startDate =
                      DateTime(endDate.year - 1, endDate.month, endDate.day);
                  break;
              }

              DateFormat dateFormat = DateFormat('yyyy-MM-dd');

              startApiDate = dateFormat.format(startDate);
              endApiDate = dateFormat.format(endDate);
            }
          }
        });
      },
    );
  }

  CustomBottomButtonContainer buildFilterButtons() {
    return CustomBottomButtonContainer(
      child: Row(
        children: [
          Expanded(
            child: CustomRoundedButton(
                widthPercentage: 0.4,
                buttonTitle: clearFiltersKey,
                showBorder: true,
                backgroundColor: Theme.of(context).colorScheme.onPrimary,
                borderColor: Theme.of(context).hintColor,
                style: Theme.of(context)
                    .textTheme
                    .titleMedium!
                    .copyWith(color: Theme.of(context).colorScheme.secondary),
                onTap: () {
                  startApiDate = null;
                  endApiDate = null;
                  status = allKey;
                  _selectedRadio[0] = allKey;
                  _selectedRadio[1] = anytimeKey;
                  getMyOrders();
                  Navigator.of(context).pop();
                }),
          ),
          const SizedBox(
            width: 16,
          ),
          Expanded(
            child: CustomRoundedButton(
              widthPercentage: 0.4,
              buttonTitle: applyKey,
              showBorder: false,
              onTap: () {
                getMyOrders();
                Navigator.of(context).pop();
              },
            ),
          )
        ],
      ),
    );
  }
}
