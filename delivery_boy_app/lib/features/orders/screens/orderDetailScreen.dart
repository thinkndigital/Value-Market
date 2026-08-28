import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/features/orders/blocs/orderUpdateCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/models/parcel.dart';
import 'package:eshoppro_deliveryboy/features/orders/widgets/orderDetailsContainer.dart';
import 'package:eshoppro_deliveryboy/features/orders/widgets/orderStatusContainer.dart';
import 'package:eshoppro_deliveryboy/utils/designConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';

import '../../../core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customAppbar.dart';
import '../../../commons/widgets/customDefaultContainer.dart';
import '../../../commons/widgets/customRoundedButton.dart';
import '../../../commons/widgets/customTextContainer.dart';

class OrderDetailScreen extends StatefulWidget {
  final Parcel parcel;
  final Function? callback;

  const OrderDetailScreen({
    Key? key,
    required this.parcel,
    this.callback,
  }) : super(key: key);
  static Widget getRouteInstance() {
    Map arguments = Get.arguments;
    return OrderDetailScreen(
      parcel: arguments['parcel'],
      callback:
          arguments.containsKey('callback') ? arguments['callback'] : null,
    );
  }

  @override
  _OrderDetailScreenState createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  late TextStyle bodyMedtextStyle;

  bool isMapOpened = false; // Flag to prevent multiple taps
  bool hasReturnStatus = false; // Will be true if any status contains 'return'

  @override
  void initState() {
    super.initState();
    // Check if any status contains 'return'
    if (widget.parcel.status != null) {
      hasReturnStatus = widget.parcel.status!
          .any((entry) => entry.status.toLowerCase().contains('return'));
    }
  }

  @override
  Widget build(BuildContext context) {
    bodyMedtextStyle = Theme.of(context).textTheme.bodyMedium!.copyWith(
        color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8));
    return Scaffold(
      appBar: const CustomAppbar(titleKey: orderDetailsKey),
      body: SingleChildScrollView(
        child: Column(
          children: <Widget>[
            const SizedBox(height: 12),
            OrderDetailsContainer(
              parcel: widget.parcel,
            ),
            const SizedBox(height: 8),
            BlocProvider<OrderUpdateCubit>(
              create: (context) => OrderUpdateCubit(),
              child: OrderStatusContainer(
                parcel: widget.parcel,
                callback: (Parcel parcel) {
                  setState(() {});
                  if (widget.callback != null) {
                    widget.callback!(parcel);
                  }
                },
              ),
            ),
            const SizedBox(height: 8),
            buildShippingDetailsContainer(),
            if ((widget.parcel.notes ?? "").trim().isNotEmpty)
              const SizedBox(height: 8),
            if ((widget.parcel.notes ?? "").trim().isNotEmpty)
              buildDeliveryPreferenceContainer(),
            const SizedBox(height: 8),
            buildPriceDetailsContainer()
          ],
        ),
      ),
    );
  }

  buildShippingDetailsContainer() {
    String address = widget.parcel.userAddress ?? "";

    return commonContainer(
      shippingDetailsKey,
      Padding(
        padding:
            const EdgeInsets.symmetric(horizontal: appContentHorizontalPadding),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if ((widget.parcel.username ?? "").trim().isNotEmpty)
              Text(
                widget.parcel.username ?? '',
                style: Theme.of(context).textTheme.titleSmall,
              ),
            Text(
              address,
              style: bodyMedtextStyle,
            ),
            DesignConfig.smallHeightSizedBox,
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                if ((widget.parcel.mobile ?? "").trim().isNotEmpty) ...[
                  Text(
                    '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: phoneNumberKey)}: ${widget.parcel.mobile}',
                    style: bodyMedtextStyle,
                  ),
                  DesignConfig.defaultWidthSizedBox,
                ],
                Flexible(
                  child: CustomRoundedButton(
                    height: 30,
                    widthPercentage: 0.4,
                    buttonTitle: '',
                    showBorder: false,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        CustomTextContainer(
                          textKey: directionKey,
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium!
                              .copyWith(color: Colors.white),
                        ),
                        const SizedBox(width: 8),
                        const Icon(
                          Icons.my_location,
                          color: Colors.white,
                        )
                      ],
                    ),
                    onTap: () async {
                      if (isMapOpened) return;
                      try {
                        Position? position = await Utils.getCurrentLocation();
                        try {
                          if (position != null)
                            openMapDirections(
                              currentLat:
                                  double.tryParse(position.latitude.toString()),
                              currentLong: double.tryParse(
                                  position.longitude.toString()),
                              destLat:
                                  double.tryParse(widget.parcel.latitude ?? ''),
                              destLong: double.tryParse(
                                  widget.parcel.longitude ?? ''),
                              currentAddress: widget.parcel.userAddress,
                              destinationAddress: widget.parcel.userAddress,
                              context: context,
                            );
                        } catch (e) {}
                      } catch (e) {
                        // Handle errors (e.g., permission denied, service not available)
                        Utils.showSnackBar(
                            context: context, message: e.toString());
                      }
                    },
                  ),
                ),
              ],
            )
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
              '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: deliveryInstructionKey)} : ${widget.parcel.notes}',
              style: bodyMedtextStyle,
            )));
  }

  buildPriceDetailsContainer() {
    return commonContainer(
        priceDetailsKey,
        Column(
          children: <Widget>[
            buildPriceDetailRow(
                '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: subtotalKey)} (${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: taxAlreadyIncludedKey)})',
                Utils.priceWithCurrencySymbol(
                    price: widget.parcel.subTotal ?? 0, context: context)),
            if (!hasReturnStatus) ...[
              buildPriceDetailRow(
                discountAmountKey,
                Utils.priceWithCurrencySymbol(
                    price: widget.parcel.discount ?? 0, context: context),
              ),
              buildPriceDetailRow(
                shippingChargesKey,
                Utils.priceWithCurrencySymbol(
                    price: widget.parcel.deliveryCharge ?? 0, context: context),
              ),
              buildPriceDetailRow(
                walletBalanceKey,
                Utils.priceWithCurrencySymbol(
                    price: widget.parcel.walletBalance ?? 0, context: context),
              ),
              buildPriceDetailRow(
                promoCodeDiscountKey,
                Utils.priceWithCurrencySymbol(
                    price: widget.parcel.promoDiscount ?? 0, context: context),
              ),
              buildPriceDetailRow(
                payableTotalKey,
                Utils.priceWithCurrencySymbol(
                    price: widget.parcel.totalPayable ?? 0, context: context),
              ),
              const Divider(
                height: 8,
                thickness: 0.5,
              ),
              buildPriceDetailRow(
                  totalAmountKey,
                  Utils.priceWithCurrencySymbol(
                      price: widget.parcel.finalTotal ?? 0, context: context),
                  textStye: Theme.of(context).textTheme.titleSmall),
            ],
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
            padding: const EdgeInsets.symmetric(
                horizontal: appContentHorizontalPadding),
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

  void openMapDirections({
    required double? currentLat,
    required double? currentLong,
    required double? destLat,
    required double? destLong,
    required BuildContext context,
    String? currentAddress,
    String? destinationAddress,
  }) async {
    if (isMapOpened) return; // Prevent multiple taps

    setState(() {
      isMapOpened = true; // Set the flag to true
    });
    String googleMapsUrl;
    String? crntAddress = currentLat != null || currentLong != null
        ? '$currentLat,$currentLong'
        : currentAddress;
    String? dstAddress = destLat != null || destLong != null
        ? '$destLat,$destLong'
        : destinationAddress;

// Check if latitude and longitude are available

// Use lat-long to open map
//This will work on both iOS and Android. The key part is that Google Maps uses the same URL scheme across platforms.
    googleMapsUrl =
        'https://www.google.com/maps/dir/?api=1&origin=$crntAddress&destination=$dstAddress';

// Check if Google Maps is available
    if (await canLaunchUrl(Uri.parse(googleMapsUrl))) {
      await launchUrl(Uri.parse(googleMapsUrl),
          mode: LaunchMode.externalApplication);
    } else {
      Utils.showSnackBar(
          message: 'Could not launch Google maps', context: context);
    }
    setState(() {
      isMapOpened = false; // Reset flag after closing the map
    });
  }
}
