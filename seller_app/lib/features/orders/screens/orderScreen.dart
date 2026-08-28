import 'dart:async';

import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';

import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';

import 'package:eshopplus_seller/features/orders/widgets/orderSearchBar.dart';
import 'package:eshopplus_seller/commons/widgets/circleButton.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';

import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/features/orders/widgets/returnRequestContainer.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/routes/routes.dart';
import '../blocs/orderCubit.dart';
import '../models/order.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customAppbar.dart';
import '../blocs/returnRequestCubit.dart';

class OrderScreen extends StatefulWidget {
  const OrderScreen({Key? key}) : super(key: key);

  @override
  _OrderScreenState createState() => _OrderScreenState();
}

class _OrderScreenState extends State<OrderScreen>
    with SingleTickerProviderStateMixin {
  final scrollController = ScrollController();
  late TabController _tabController;

  bool isSearching = false;
  Map<String, String>? apiParameter;
  List<Order> orderlist = [];
  int currOffset = 0;
  String awaiting = "0",
      received = "0",
      shipped = "0",
      delivered = "0",
      cancelled = "0",
      returned = "0",
      processed = "0";
  bool loadStatus = true;

  @override
  void initState() {
    super.initState();
    apiParameter = {};
    isSearching = false;
    setupScrollController(context);
    _tabController = TabController(length: 2, vsync: this);
    loadPage(isSetInitialPage: true);
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, dynamic> parameter = {
      ApiURL.storeIdApiKey: context.read<StoresCubit>().getDefaultStore().id
    };
    if (apiParameter != null) {
      parameter.addAll(apiParameter!);
    }
    BlocProvider.of<OrdersCubit>(context)
        .loadPosts(parameter, isSetInitial: isSetInitialPage);
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
    return BlocProvider(
      create: (context) => ReturnRequestCubit(),
      child: DefaultTabController(
        length: 2,
        child: Scaffold(
          appBar: const CustomAppbar(
            titleKey: ordersKey,
            showBackButton: false,
          ),
          body: Column(
            children: [
              TabBar(
                controller: _tabController,
                tabs: [
                  Tab(
                      text: context
                          .read<SettingsAndLanguagesCubit>()
                          .getTranslatedValue(labelKey: ordersKey)),
                  Tab(
                      text: context
                          .read<SettingsAndLanguagesCubit>()
                          .getTranslatedValue(labelKey: returnRequestsKey)),
                ],
                labelColor: Theme.of(context).colorScheme.secondary,
                indicatorColor: Theme.of(context).colorScheme.primary,
              ),
              Expanded(
                child: TabBarView(
                  controller: _tabController,
                  children: [ordersContainer(), ReturnRequestContainer()],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  ordersContainer() {
    return BlocConsumer<OrdersCubit, OrdersState>(
      listener: (context, state) {
        if (state is OrdersFetchSuccess) {
          getOrderStatus(state);
          setState(() {
            loadStatus = false;
          });
        }
      },
      builder: (context, state) {
        return Column(
          children: <Widget>[
            OrderSearchBar(
                filterCallback: filterOrder, mainFilterValue: apiParameter),
            DesignConfig.defaultHeightSizedBox,
            buildOrderSatusSection(state),
            DesignConfig.defaultHeightSizedBox,
            Expanded(child: contentWidget(state))
          ],
        );
      },
    );
  }

  filterOrder(Map<String, String> filterValue, {bool isClearFilter = false}) {
    apiParameter!.clear();
    if (!isClearFilter) {
      apiParameter!.addAll(filterValue);
    }
    if (apiParameter!.containsKey('active_status') &&
        apiParameter!['active_status'] == 'all_orders') {
      apiParameter!.remove('active_status');
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
    apiParameter = {};
    setState(() {
      loadStatus = true;
    });
    await Future.delayed(const Duration(seconds: 2), () {
      loadPage(isSetInitialPage: true);
    });
  }

  listContent(OrdersState state) {
    orderlist = [];
    bool isLoading = false;
    if (state is OrdersFetchInProgress) {
      orderlist = state.oldArchiveList;
      isLoading = true;
    } else if (state is OrdersFetchSuccess) {
      orderlist = state.specialityList;
    }
    return Column(
      children: [
        Expanded(
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            controller: scrollController,
            padding: const EdgeInsets.symmetric(
              horizontal: appContentHorizontalPadding,
            ),
            itemBuilder: (context, index) {
              if (index < orderlist.length) {
                if (orderlist[index].orderItems != null &&
                    orderlist[index].orderItems!.isNotEmpty) {
                  return orderInfoContainer(orderlist[index]);
                }
                return const SizedBox.shrink();
              } else {
                Timer(const Duration(milliseconds: 30), () {
                  scrollController
                      .jumpTo(scrollController.position.maxScrollExtent);
                });

                return Utils.loadingIndicator();
              }
            },
            itemCount: orderlist.length + (isLoading ? 1 : 0),
          ),
        ),
      ],
    );
  }

  getOrderStatus(OrdersState state) {
    if (loadStatus) {
      awaiting = "";
      received = "";
      shipped = "";
      delivered = "";
      cancelled = "";
      returned = "";
      processed = "";

      if (state is OrdersFetchSuccess) {
        currOffset = state.currOffset;
        received = state.received;
        awaiting = state.awaiting;
        shipped = state.shipped;
        delivered = state.delivered;
        cancelled = state.cancelled;
        returned = state.returned;
        processed = state.processed;
      }
    }
  }

  buildOrderSatusSection(OrdersState state) {
    return SizedBox(
      height: 90,
      child: ListView(
        shrinkWrap: true,
        scrollDirection: Axis.horizontal,
        padding: EdgeInsets.only(left: appContentHorizontalPadding),
        children: [
          buildOrderStatusContainer(
              receivedKey, received, Icons.call_received, receivedStatusColor),
          buildOrderStatusContainer(processedKey, processed,
              Icons.wifi_protected_setup, processedStatusColor),
          buildOrderStatusContainer(shippedKey, shipped,
              Icons.inventory_2_outlined, shippedStatusColor),
          buildOrderStatusContainer(deliveredKey, delivered,
              Icons.shopping_bag_outlined, deliveredStatusColor),
          buildOrderStatusContainer(cancelledKey, cancelled,
              Icons.cancel_outlined, cancelledStatusColor),
          buildOrderStatusContainer(returnedKey, returned,
              Icons.restart_alt_outlined, returnedStatusColor)
        ],
      ),
    );
  }

  Widget buildOrderStatusContainer(
      String status, String orders, IconData icon, Color color) {
    return GestureDetector(
      onTap: () {
        if (apiParameter != null &&
            apiParameter!.containsKey('active_status') &&
            apiParameter!['active_status'] == status) {
          apiParameter!.remove('active_status');
        } else {
          apiParameter!.addAll({'active_status': status});
        }

        setState(() {});
        loadPage(isSetInitialPage: true);
      },
      child: Container(
        width: 155,
        height: 90,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
        margin: EdgeInsets.only(right: appContentHorizontalPadding),
        decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            color: apiParameter != null &&
                    apiParameter!['active_status'] == status
                ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)
                : Theme.of(context).colorScheme.primaryContainer),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            CircleButton(
              onTap: () {},
              heightAndWidth: 34,
              backgroundColor: color.withValues(alpha: 0.12),
              child: Icon(
                icon,
                color: color,
                size: 24,
              ),
            ),
            const SizedBox(
              width: 12,
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  FittedBox(
                    child: CustomTextContainer(
                      textKey: status,
                      style: Theme.of(context).textTheme.bodyLarge!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.8)),
                      maxLines: 1,
                    ),
                  ),
                  const SizedBox(
                    height: 8,
                  ),
                  Text(
                    orders,
                    style: Theme.of(context).textTheme.titleLarge!.copyWith(
                        color: Theme.of(context).colorScheme.secondary),
                  )
                ],
              ),
            )
          ],
        ),
      ),
    );
  }

  orderInfoContainer(Order order) {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.orderDetailsScreen,
          arguments: {
            'order': order,
            'callback': (Order order) {
              int index =
                  orderlist.indexWhere((element) => element.id == order.id);
              if (index != -1) {
                orderlist[index] = order;
                BlocProvider.of<OrdersCubit>(context).setOldList(
                    currOffset,
                    orderlist,
                    awaiting,
                    received,
                    shipped,
                    delivered,
                    cancelled,
                    returned,
                    processed);
              }
            }
          }),
      child: Container(
        margin: const EdgeInsetsDirectional.only(bottom: 8),
        width: MediaQuery.of(context).size.width,
        padding: EdgeInsets.symmetric(vertical: appContentVerticalSpace),
        decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primaryContainer,
            borderRadius: BorderRadius.circular(8)),
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: appContentHorizontalPadding),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Utils.buildProfilePicture(
                      context, 50, order.userProfileImage ?? ''),
                  DesignConfig.smallWidthSizedBox,
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      CustomTextContainer(
                        textKey: order.username ?? '',
                        style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                            color: Theme.of(context)
                                .colorScheme
                                .secondary
                                .withValues(alpha: 0.8)),
                      ),
                      if (order.mobile != null &&
                          order.mobile!.trim().isNotEmpty)
                        CustomTextContainer(
                          textKey: order.mobile ?? '',
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium!
                              .copyWith(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .secondary
                                      .withValues(alpha: 0.8)),
                        ),
                    ],
                  ),
                  const Spacer(),
                  Text(
                    order.id.toString().withOrderSymbol(),
                    style: Theme.of(context).textTheme.titleMedium!.copyWith(
                        color: Theme.of(context)
                            .colorScheme
                            .secondary
                            .withValues(alpha: 0.8)),
                  ),
                ],
              ),
            ),
            const Divider(
              thickness: 0.3,
            ),
            ListView.separated(
                separatorBuilder: (context, index) =>
                    const SizedBox(height: 10),
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount:
                    order.orderItems!.length > 2 ? 2 : order.orderItems!.length,
                itemBuilder: (context, index) {
                  OrderItems orderItem = order.orderItems![index];
                  return Padding(
                    padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: appContentHorizontalPadding),
                    child: Row(
                      children: <Widget>[
                        CustomImageWidget(
                            url: orderItem.image ?? "",
                            width: 62,
                            height: 72,
                            borderRadius: borderRadius),
                        DesignConfig.smallWidthSizedBox,
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: CustomTextContainer(
                                      textKey: orderItem.productName ?? "",
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleSmall!,
                                    ),
                                  ),
                                  DesignConfig.smallWidthSizedBox,
                                  Utils.buildStatusWidget(
                                      context, orderItem.activeStatus!,
                                      showBorder: false)
                                ],
                              ),
                              DesignConfig.smallHeightSizedBox,
                              CustomTextContainer(
                                textKey:
                                    '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: qtyKey)} : ${orderItem.quantity.toString()} x ${Utils.priceWithCurrencySymbol(price: double.tryParse(orderItem.price.toString()) ?? 0, context: context)}',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodyMedium!
                                    .copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .secondary),
                              )
                            ],
                          ),
                        )
                      ],
                    ),
                  );
                }),
            if (order.orderItems!.length > 2) ...[
              DesignConfig.defaultHeightSizedBox,
              Container(
                alignment: Alignment.centerRight,
                padding: const EdgeInsets.symmetric(
                    horizontal: appContentHorizontalPadding),
                child: CustomTextContainer(
                  textKey:
                      '+${order.orderItems!.length - 2} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: moreProductsKey)}',
                  style: Theme.of(context)
                      .textTheme
                      .bodyMedium!
                      .copyWith(color: Theme.of(context).colorScheme.primary),
                ),
              ),
            ],
            const Divider(
              thickness: 0.3,
            ),
            Padding(
              padding: const EdgeInsetsDirectional.symmetric(
                  horizontal: appContentHorizontalPadding),
              child: Column(
                children: <Widget>[
                  buildPaymentAndPriceRow(
                      paymentTypeKey,
                      paymentGatewayDisplayNames[
                              order.paymentMethod!.toLowerCase()] ??
                          ''),
                  buildPaymentAndPriceRow(
                      orderTotalKey,
                      Utils.priceWithCurrencySymbol(
                          price: order.finalTotal ?? 0, context: context)),
                ],
              ),
            )
          ],
        ),
      ),
    );
  }

  buildPaymentAndPriceRow(String title, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: <Widget>[
        CustomTextContainer(
          textKey: title,
          style: Theme.of(context)
              .textTheme
              .bodyMedium!
              .copyWith(color: Theme.of(context).colorScheme.secondary),
        ),
        CustomTextContainer(
            textKey: value, style: Theme.of(context).textTheme.titleSmall!),
      ],
    );
  }

  buildIcon(IconData iconData) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: Icon(iconData,
          size: 18, color: Theme.of(context).colorScheme.secondary),
    );
  }
}
