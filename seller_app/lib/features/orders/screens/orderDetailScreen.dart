import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/orders/blocs/getInvoiceCubit.dart';
import 'package:eshopplus_seller/features/orders/blocs/orderCubit.dart';
import 'package:eshopplus_seller/features/orders/blocs/parcel/createOrderParcelCubit.dart';
import 'package:eshopplus_seller/features/orders/blocs/parcel/getParcelCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/userDetailsCubit.dart';
import 'package:eshopplus_seller/features/orders/models/parcel.dart';
import 'package:eshopplus_seller/features/orders/widgets/createParcelButton.dart';
import 'package:eshopplus_seller/features/orders/widgets/orderDetailsContainer.dart';
import 'package:eshopplus_seller/features/orders/widgets/orderItemsContainer.dart';
import 'package:eshopplus_seller/features/orders/widgets/parcelContainer.dart';
import 'package:eshopplus_seller/features/orders/widgets/shiprocketOrderContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import '../blocs/orderUpdateCubit.dart';
import '../models/order.dart';

class OrderDetailScreen extends StatefulWidget {
  final Order order;

  final Function? callback;
  const OrderDetailScreen({Key? key, required this.order, this.callback})
      : super(key: key);
  static Widget getRouteInstance() {
    Map arguments = Get.arguments;
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => GetInvoiceCubit(),
        ),
        BlocProvider(
          create: (context) => ParcelCubit(),
        ),
      ],
      child: OrderDetailScreen(
        order: arguments['order'],
        callback:
            arguments.containsKey('callback') ? arguments['callback'] : null,
      ),
    );
  }

  @override
  _OrderDetailScreenState createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  late TextStyle bodyMedtextStyle;
  late OrderItems orderItem;
  late Order order;
  GlobalKey _orderDetailsKey = GlobalKey(), _statusDetailsKey = GlobalKey();
  List<int> selectedOrders = [];
  List<Parcel> parcels = [];
  @override
  void initState() {
    super.initState();
    order = widget.order;
    orderItem = order.orderItems!.first;
    Future.delayed(Duration.zero, () {
      context.read<ParcelCubit>().getParcel(params: {
        ApiURL.storeIdApiKey:
            context.read<StoresCubit>().getDefaultStore().id.toString(),
        ApiURL.orderIdApiKey: widget.order.id.toString(),
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    bodyMedtextStyle = Theme.of(context).textTheme.bodyMedium!.copyWith(
        color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8));
    return MultiBlocListener(
      listeners: [
        BlocListener<OrdersCubit, OrdersState>(
          listener: (context, state) {
            if (state is OrdersFetchSuccess) {
              order = state.specialityList.firstWhere((o) => o.id == order.id);
              orderItem = order.orderItems!.first;

              setState(() {
                _orderDetailsKey = GlobalKey();
                _statusDetailsKey = GlobalKey();
              });
            }
          },
        ),
        BlocListener<ParcelCubit, ParcelState>(
          listener: (context, state) {
            if (state is ParcelFetchSuccess) {
              setState(() {
                parcels = state.parcels;
              });
            }
          },
        ),
      ],
      child: SafeAreaWithBottomPadding(
        child: Scaffold(
          appBar: const CustomAppbar(titleKey: orderDetailsKey),
          bottomNavigationBar: widget.order.type != digitalProductType
              ? BlocProvider(
                  create: (context) => CreateOrderParcelCubit(),
                  child: CreateParcelButton(
                      order: widget.order,
                      selectedOrders: selectedOrders,
                      parcels: parcels,
                      parcelCubit: context.read<ParcelCubit>(),
                      callback: (Order order) {
                        setState(() {
                          selectedOrders.clear();
                        });
                        if (widget.callback != null) {
                          widget.callback!(order);
                        }
                      }),
                )
              : null,
          body: BlocBuilder<ParcelCubit, ParcelState>(
            builder: (context, state) {
              return SingleChildScrollView(
                child: Column(
                  children: <Widget>[
                    const SizedBox(height: 12),
                    OrderDetailsContainer(
                      key: _orderDetailsKey,
                      order: order,
                    ),
                    DesignConfig.smallHeightSizedBox,
                    if (widget.order.type != digitalProductType)
                      MultiBlocProvider(
                        providers: [
                          BlocProvider(
                            create: (context) => CreateOrderParcelCubit(),
                          ),
                        ],
                        child: ParcelContainer(
                          order: order,
                          callback: widget.callback,
                        ),
                      ),
                    if (state is! ParcelFetchInProgress)
                      BlocProvider<OrderUpdateCubit>(
                        create: (context) => OrderUpdateCubit(),
                        child: OrderItemsContainer(
                          key: _statusDetailsKey,
                          order: order,
                          parcels: parcels,
                          editSelectedOrders: (var orders) {
                            selectedOrders = orders;
                            setState(() {});
                          },
                          callback: (var item) {
                            orderItem = item as OrderItems;

                            setState(() {});
                            if (widget.callback != null) {
                              List<OrderItems> orderitems = [];
                              orderitems.addAll(order.orderItems!);

                              int index = orderitems.indexWhere(
                                  (element) => element.id == item.id);
                              if (index != -1) {
                                orderitems[index] = item;
                              }
                              order.orderItems!.clear();
                              order.orderItems!.addAll(orderitems);
                              widget.callback!(order);
                            }
                          },
                        ),
                      ),
                    if (widget.order.type != digitalProductType) ...[
                      if (context
                              .read<UserDetailsCubit>()
                              .getDefaultStoreOfUser(context)
                              .permissions!
                              .customerPrivacy ==
                          1) ...[
                        DesignConfig.smallHeightSizedBox,
                        buildShippingDetailsContainer(),
                      ],
                      if ((order.notes ?? "").trim().isNotEmpty) ...[
                        DesignConfig.smallHeightSizedBox,
                        buildDeliveryPreferenceContainer(),
                      ],
                    ],
                    DesignConfig.smallHeightSizedBox,
                    buildPriceDetailsContainer()
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Container buildShiprocketOrderButton() {
    return Container(
      padding: const EdgeInsets.symmetric(
          vertical: 8, horizontal: appContentHorizontalPadding),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: CustomRoundedButton(
        widthPercentage: 1.0,
        buttonTitle: createShiprocketOrderKey,
        showBorder: false,
        backgroundColor: Theme.of(context).colorScheme.secondary,
        onTap: () => Utils.openModalBottomSheet(
            context, staticContent: true, const ShiprocketOrderContainer()),
      ),
    );
  }

  buildShippingDetailsContainer() {
    String address = order.address ?? "";
    if ((order.mobile ?? "").trim().isNotEmpty) {
      address =
          "$address\n${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: phoneNumberKey)}: ${order.mobile}";
    }
    return commonContainer(
      shippingDetailsKey,
      Padding(
        padding:
            const EdgeInsets.symmetric(horizontal: appContentHorizontalPadding),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if ((order.username ?? "").trim().isNotEmpty)
              Text(
                order.username ?? '',
                style: Theme.of(context).textTheme.titleSmall,
              ),
            Text(
              address,
              style: bodyMedtextStyle,
            ),
          ],
        ),
      ),
    );
  }

  buildDeliveryPreferenceContainer() {
    return commonContainer(
        deliveryPreferenceKey,
        Padding(
            padding: const EdgeInsets.symmetric(
                horizontal: appContentHorizontalPadding),
            child: Text(
              '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: deliveryInstructionKey)} : ${order.notes}',
              style: bodyMedtextStyle,
            )));
  }

  buildPriceDetailsContainer() {
    return commonContainer(
        priceDetailsKey,
        Column(
          children: <Widget>[
            buildPriceDetailRow(
                itemTotalKey,
                Utils.priceWithCurrencySymbol(
                    price: order.itemTotal ?? 0, context: context)),
            buildPriceDetailRow(
              discountAmountKey,
              order.discount != 0
                  ? '-${Utils.priceWithCurrencySymbol(price: order.discount ?? 0, context: context)}'
                  : Utils.priceWithCurrencySymbol(
                      price: order.discount ?? 0, context: context),
            ),
            buildPriceDetailRow(
              couponDiscountKey,
              order.promoDiscount != 0
                  ? '-${Utils.priceWithCurrencySymbol(price: order.promoDiscount ?? 0, context: context)}'
                  : Utils.priceWithCurrencySymbol(
                      price: order.promoDiscount ?? 0, context: context),
            ),
            buildPriceDetailRow(
                shippingChargesKey,
                order.deliveryCharge != 0
                    ? '+${Utils.priceWithCurrencySymbol(price: order.deliveryCharge ?? 0, context: context)}'
                    : Utils.priceWithCurrencySymbol(
                        price: order.deliveryCharge ?? 0, context: context)),
            buildPriceDetailRow(
              walletBalanceKey,
              Utils.priceWithCurrencySymbol(
                  price: order.walletBalance ?? 0, context: context),
            ),
            const Divider(
              height: 8,
              thickness: 0.5,
            ),
            buildPriceDetailRow(
                totalAmountKey,
                Utils.priceWithCurrencySymbol(
                    price: order.totalPayable == 0
                        ? order.finalTotal!
                        : order.totalPayable!,
                    context: context),
                textStye: Theme.of(context).textTheme.titleSmall),
          ],
        ));
  }

  buildPriceDetailRow(String title, String value, {TextStyle? textStye}) {
    return Padding(
      padding: EdgeInsets.only(
          left: appContentHorizontalPadding,
          right: appContentHorizontalPadding,
          bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          CustomTextContainer(
            textKey: title,
            style: textStye ?? bodyMedtextStyle,
          ),
          Text(
            value,
            style: textStye ?? bodyMedtextStyle,
          )
        ],
      ),
    );
  }

  commonContainer(String title, Widget content) {
    return Container(
      padding: EdgeInsets.symmetric(vertical: appContentVerticalSpace),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding:
                EdgeInsets.symmetric(horizontal: appContentHorizontalPadding),
            child: CustomTextContainer(
              textKey: title,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          const Divider(
            height: 8,
            thickness: 0.5,
          ),
          content
        ],
      ),
    );
  }

  buildContainer() {
    return CustomDefaultContainer(
        child: Row(
      children: <Widget>[
        const CustomRoundedButton(
            widthPercentage: 0.4,
            buttonTitle: lblOrderCreatedKey,
            showBorder: false),
        buildIcons(Icons.add_location_alt_outlined, () {}),
        buildIcons(Icons.label_outline, () {}),
        buildIcons(Icons.cancel_outlined, () {}),
        buildIcons(Icons.receipt_long_outlined, () {}),
      ],
    ));
  }

  buildIcons(IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(left: 12),
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(borderRadius),
            color: Theme.of(context).scaffoldBackgroundColor),
        child: Icon(
          icon,
          color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8),
        ),
      ),
    );
  }
}
