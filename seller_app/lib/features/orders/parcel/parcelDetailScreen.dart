import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/features/orders/blocs/getInvoiceCubit.dart';
import 'package:value_market_seller/features/orders/blocs/orderUpdateCubit.dart';

import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_seller/features/orders/models/order.dart';
import 'package:value_market_seller/features/orders/models/parcel.dart';
import 'package:value_market_seller/features/orders/parcel/parcelDetailsContainer.dart';
import 'package:value_market_seller/features/orders/parcel/parcelItemsContainer.dart';

import 'package:value_market_seller/commons/widgets/customAppbar.dart';
import 'package:value_market_seller/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_seller/commons/widgets/customRoundedButton.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';

import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import '../blocs/deliveryBoyCubit.dart';

class ParcelDetailScreen extends StatefulWidget {
  final Parcel parcel;
  final Order order;

  final Function? callback;
  const ParcelDetailScreen(
      {Key? key, required this.parcel, required this.order, this.callback})
      : super(key: key);
  static Widget getRouteInstance() {
    Map arguments = Get.arguments;
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => GetInvoiceCubit(),
        ),
        BlocProvider(
          create: (context) => DeliveryBoyCubit(),
        ),
      ],
      child: ParcelDetailScreen(
        parcel: arguments['parcel'],
        order: arguments['order'],
        callback:
            arguments.containsKey('callback') ? arguments['callback'] : null,
      ),
    );
  }

  @override
  _ParcelDetailScreenState createState() => _ParcelDetailScreenState();
}

class _ParcelDetailScreenState extends State<ParcelDetailScreen> {
  late TextStyle bodyMedtextStyle;

  late Parcel parcel;

  List<int> selectedOrders = [];
  List<Parcel> parcels = [];
  @override
  void initState() {
    super.initState();
    parcel = widget.parcel;

    Future.delayed(Duration.zero, () {
      if (context.read<DeliveryBoyCubit>().state is! DeliveryBoySuccess) {
        context.read<DeliveryBoyCubit>().getDeliveryboyList(context, {
          ApiURL.storeIdApiKey:
              context.read<StoresCubit>().getDefaultStore().id.toString()
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    bodyMedtextStyle = Theme.of(context).textTheme.bodyMedium!.copyWith(
        color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8));
    return BlocListener<DeliveryBoyCubit, DeliveryBoyState>(
        listener: (context, state) {
          if (state is DeliveryBoyFailure) {
            Utils.showSnackBar(message: state.errorMessage);
          }
        },
        child: Scaffold(
            appBar: const CustomAppbar(titleKey: parcelDetailsKey),
            body: SingleChildScrollView(
              child: Column(
                children: <Widget>[
                  const SizedBox(height: 12),
                  ParcelDetailsContainer(
                    parcel: parcel,
                  ),
                  DesignConfig.smallHeightSizedBox,
                  BlocProvider<OrderUpdateCubit>(
                    create: (context) => OrderUpdateCubit(),
                    child: ParcelItemsContainer(
                      parcel: widget.parcel,
                      order: widget.order,
                      callback: (var item) {
                        setState(() {});
                        if (widget.callback != null) {
                          List<OrderItems> orderitems = [];
                          orderitems.addAll(widget.order.orderItems!);

                          int index = orderitems
                              .indexWhere((element) => element.id == item.id);
                          if (index != -1) {
                            orderitems[index] = item;
                          }
                          widget.order.orderItems!.clear();
                          widget.order.orderItems!.addAll(orderitems);
                          widget.callback!(widget.order);
                        }
                      },
                    ),
                  ),
                  if (context
                          .read<UserDetailsCubit>()
                          .getDefaultStoreOfUser(context)
                          .permissions!
                          .customerPrivacy ==
                      1)
                    buildShippingDetailsContainer(),
                  if ((parcel.notes ?? "").trim().isNotEmpty) ...[
                    DesignConfig.smallHeightSizedBox,
                    buildDeliveryPreferenceContainer(),
                    DesignConfig.smallHeightSizedBox,
                  ],
                  buildPriceDetailsContainer()
                ],
              ),
            )));
  }

  // Container buildShiprocketOrderButton() {
  //   return Container(
  //     padding: const EdgeInsets.symmetric(
  //         vertical: 8, horizontal: appContentHorizontalPadding),
  //     color: Theme.of(context).colorScheme.primaryContainer,
  //     child: CustomRoundedButton(
  //       widthPercentage: 1.0,
  //       buttonTitle: createShiprocketOrderKey,
  //       showBorder: false,
  //       backgroundColor: Theme.of(context).colorScheme.secondary,
  //       onTap: () => Utils.openModalBottomSheet(
  //           context, const ShiprocketOrderContainer()),
  //     ),
  //   );
  // }

  buildShippingDetailsContainer() {
    String address = parcel.userAddress ?? "";
    if ((parcel.mobile ?? "").trim().isNotEmpty) {
      address =
          "$address\n${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: phoneNumberKey)}: ${parcel.mobile}";
    }
    return commonContainer(
      shippingDetailsKey,
      Padding(
        padding:
            const EdgeInsets.symmetric(horizontal: appContentHorizontalPadding),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if ((parcel.username ?? "").trim().isNotEmpty)
              Text(
                parcel.username ?? '',
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
              '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: deliveryInstructionKey)} : ${parcel.notes}',
              style: bodyMedtextStyle,
            )));
  }

  buildPriceDetailsContainer() {
    return commonContainer(
        priceDetailsKey,
        Column(
          children: <Widget>[
            buildPriceDetailRow(
                subtotalKey,
                Utils.priceWithCurrencySymbol(
                    price: parcel.totalUnitPrice ?? 0, context: context)),
            buildPriceDetailRow(
                shippingChargesKey,
                parcel.deliveryCharge != 0
                    ? '+${Utils.priceWithCurrencySymbol(price: parcel.deliveryCharge ?? 0, context: context)}'
                    : Utils.priceWithCurrencySymbol(
                        price: parcel.deliveryCharge ?? 0, context: context)),
            // if (parcel.promoDiscount != 0)
            //   buildPriceDetailRow(couponDiscountKey,
            //       '${Utils.priceWithCurrencySymbol(price: parcel.promoDiscount ?? 0, context: context)}'),
            const Divider(
              height: 8,
              thickness: 0.5,
            ),
            buildPriceDetailRow(
                totalAmountKey,
                Utils.priceWithCurrencySymbol(
                    price:
                        (parcel.totalUnitPrice ?? 0) + parcel.deliveryCharge!,
                    context: context),
                textStye: Theme.of(context).textTheme.titleSmall),
          ],
        ));
  }

  buildPriceDetailRow(String title, String value, {TextStyle? textStye}) {
    return Padding(
      padding: const EdgeInsets.only(
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
