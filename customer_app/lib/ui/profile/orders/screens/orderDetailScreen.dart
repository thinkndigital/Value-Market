import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/getInvoiceCubit.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/orderCubit.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/sendBankTransferProofCubit.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/setProductReviewCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';

import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

import '../models/order.dart';
import '../widgets/deliveryAndPriceDetailsContainer.dart';
import '../widgets/orderDetailsContainer.dart';
import '../widgets/shipmentDetailsContainer.dart';
import '../widgets/bankReceiptUploadSection.dart';

class OrderDetailScreen extends StatefulWidget {
  final Order order;
  final int? selectedItemId;

  final int? orderId;
  final int? storeId;
  const OrderDetailScreen(
      {Key? key,
      required this.order,
      this.selectedItemId,
      this.orderId,
      this.storeId})
      : super(key: key);
  static Widget getRouteInstance() {
    Map arguments = Get.arguments;
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => GetInvoiceCubit(),
        ),
        BlocProvider(
          create: (context) => SetProductReviewCubit(),
        ),
        BlocProvider(
          create: (context) => OrdersCubit(),
        ),
      ],
      child: OrderDetailScreen(
        order: arguments['order'],
        selectedItemId: arguments['selectedItemId'],
        orderId: arguments['orderId'],
        storeId: arguments['storeId'] as int?,
      ),
    );
  }

  @override
  _OrderDetailScreenState createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  late Order order;
  late int selectedItemId;
  GlobalKey _orderDetailsKey = GlobalKey(),
      _shipmentDetailsKey = GlobalKey(),
      _addressDetailsKey = GlobalKey();
  @override
  void initState() {
    super.initState();
    order = Get.arguments['order'];
    selectedItemId = Get.arguments['selectedItemId'] ?? 0;
    if (widget.orderId != null) {
      Future.delayed(Duration.zero, () {
        context.read<OrdersCubit>().getOrders(
              storeId: widget.storeId != null
                  ? widget.storeId!
                  : context.read<StoresCubit>().getDefaultStore().id!,
              orderId: widget.orderId,
            );
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<OrdersCubit, OrdersState>(
      listener: (context, state) {
        if (state is OrdersFetchSuccess) {
          order = state.orders.firstWhere((o) => o.id == order.id);
          if (selectedItemId == 0) {
            selectedItemId = order.orderItems!.first.id!;
          }
          setState(() {
            _shipmentDetailsKey = GlobalKey();
          });
        }
      },
      child: SafeAreaWithBottomPadding(
        child: Scaffold(
          appBar: const CustomAppbar(titleKey: orderDetailsKey),
          body: BlocListener<SetProductReviewCubit, SetProductReviewState>(
            listener: (context, state) {
              if (state is SetProductReviewSuccess) {
                if (mounted) {
                  Utils.showSnackBar(
                      message: state.successMessage, context: context);
                }
              }
            },
            child: BlocBuilder<OrdersCubit, OrdersState>(
              builder: (context, state) {
                if (state is OrdersFetchSuccess || widget.orderId == null) {
                  return SingleChildScrollView(
            
                    child: Column(
                    children: <Widget>[
                        const SizedBox(height: 12),
                        OrderDetailsContainer(
                          key: _orderDetailsKey,
                          order: order,
                        ),
                        DesignConfig.smallHeightSizedBox,
                        ShipmentDetailsContainer(
                          key: _shipmentDetailsKey,
                          order: order,
                          selectedItemId: selectedItemId,
                        ),
                        if (order.paymentMethod == bankTransferKey)
                          BlocProvider(
                            create: (context) => SendBankTransferProofCubit(),
                            child: BankReceiptUploadSection(order: order),
                          ),
                        DeliveryAndPriceDetailsContainer(
                          key: _addressDetailsKey,
                          order: order,
                          selectedItemId: selectedItemId,
                        ),
                      ],
                    ),
                  );
                }
                if (state is OrdersFetchFailure) {
                  return ErrorScreen(
                      text: state.errorMessage,
                      onPressed: () {
                        context.read<OrdersCubit>().getOrders(
                              storeId: context
                                  .read<StoresCubit>()
                                  .getDefaultStore()
                                  .id!,
                              orderId: widget.orderId,
                            );
                      });
                }
                return Center(
                  child: CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.primary,
                  ),
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}
