import 'dart:async';

import 'package:value_market_delivery_boy/core/constants/appConstants.dart';
import 'package:value_market_delivery_boy/core/constants/themeConstants.dart';
import 'package:value_market_delivery_boy/features/orders/blocs/orderUpdateCubit.dart';
import 'package:value_market_delivery_boy/features/orders/models/parcel.dart';
import 'package:value_market_delivery_boy/commons/widgets/customDropDownContainer.dart';
import 'package:value_market_delivery_boy/commons/widgets/customImageWidget.dart';
import 'package:value_market_delivery_boy/commons/widgets/customLabelContainer.dart';
import 'package:value_market_delivery_boy/commons/widgets/customTextFieldContainer.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../models/order.dart';
import 'package:value_market_delivery_boy/core/configs/appConfig.dart';
import '../../../utils/designConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customRoundedButton.dart';
import '../../../commons/widgets/customTextContainer.dart';

class OrderStatusContainer extends StatefulWidget {
  final Parcel parcel;
  final Function callback;

  const OrderStatusContainer(
      {Key? key, required this.parcel, required this.callback})
      : super(key: key);

  @override
  OrderStatusContainerState createState() => OrderStatusContainerState();
}

class OrderStatusContainerState extends State<OrderStatusContainer> {
  Map<String, TextEditingController> controllers = {};

  Map<String, String> statusTypes = Map.from(orderStatusTypes);
  List<int> selectedOrders = [];
  List attributeList = [], variantValues = [];
  final scrollController = ScrollController();
  late BuildContext customerdialogContext;
  bool isMapOpened = false; // Flag to prevent multiple taps
  final List formFields = [orderStatusKey, enterOtpKey];
  @override
  void initState() {
    super.initState();
    for (var key in formFields) {
      controllers[key] = TextEditingController();
    }
    if (widget.parcel.isReturnOrder ?? false) {
      statusTypes = {
        returnPickedupStatusType: returnPickedupKey,
      };
    }
    statusTypes.remove(cancelledStatusType);
    statusTypes.remove(returnedStatusType);
    controllers[orderStatusKey]!.text = context
        .read<SettingsAndLanguagesCubit>()
        .getTranslatedValue(labelKey: selectStatusKey);

    if (widget.parcel.activeStatus != null &&
        widget.parcel.activeStatus!.trim().isNotEmpty) {
      String? selectedkey;
      for (var element in statusTypes.keys) {
        if (element == widget.parcel.activeStatus) {
          selectedkey = element;
          break;
        }
      }
      if (selectedkey != null && statusTypes.containsKey(selectedkey)) {
        controllers[orderStatusKey]!.text = selectedkey;
      } else {
        if (widget.parcel.isReturnOrder ?? false) {
          controllers[orderStatusKey]!.text = returnPickedupStatusType;
        } else {
          controllers[orderStatusKey]!.text = orderStatusTypes.values.first;
        }
      }
    }
  }

  @override
  void dispose() {
// Dispose all text editing controllers
    controllers.forEach((key, controller) {
      controller.dispose();
    });

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          padding: EdgeInsets.symmetric(vertical: appContentVerticalSpace),
          color: Theme.of(context).colorScheme.primaryContainer,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Padding(
                padding: const EdgeInsets.symmetric(
                    horizontal: appContentHorizontalPadding),
                child: CustomTextContainer(
                  textKey: orderStatusKey,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              const Divider(
                thickness: 0.5,
              ),
              Padding(
                padding: const EdgeInsets.symmetric(
                    horizontal: appContentHorizontalPadding),
                child: Column(
                  children: <Widget>[
                    CustomDropDownContainer(
                        labelKey: orderStatusKey,
                        dropDownDisplayLabels: statusTypes.values.toList(),
                        selectedValue: controllers[orderStatusKey]!.text,
                        isFieldValueMandatory: false,
                        onChanged: (value) {
                          setState(() {
                            controllers[orderStatusKey]!.text =
                                value.toString();
                          });
                        },
                        values: statusTypes.keys.toList()),
                    if (controllers[orderStatusKey]!.text ==
                        deliveredStatusType)
                      CustomTextFieldContainer(
                          hintTextKey: enterOtpKey,
                          textEditingController: controllers[enterOtpKey]!,
                          labelKey: enterOtpKey,
                          textInputAction: TextInputAction.next,
                          inputFormatters: [
                            FilteringTextInputFormatter
                                .digitsOnly, // Allow only digits
                          ],
                          isSetValidator: false,
                          onFieldSubmitted: (v) =>
                              FocusScope.of(context).unfocus()),
                    DesignConfig.defaultHeightSizedBox,
                    BlocConsumer<OrderUpdateCubit, OrderUpdateState>(
                      listener: (context, state) {
                        if (state is OrderUpdateFailure && state.type == 1) {
                          FocusScope.of(context).unfocus();
                          Utils.showSnackBar(
                              message: state.errorMessage, context: context);
                        } else if (state is OrderUpdateSuccess &&
                            state.type == 1) {
                          FocusScope.of(context).unfocus();
                          Utils.showSnackBar(
                              message: state.successMsg, context: context);
                          statusUpdateSuccess(state);
                        }
                      },
                      builder: (context, state) {
                        return state is OrderUpdateProgress && state.type == 1
                            ? const CircularProgressIndicator()
                            : CustomRoundedButton(
                                widthPercentage: 1.0,
                                buttonTitle: updateKey,
                                showBorder: false,
                                onTap: () {
                                  updateStatus();
                                },
                              );
                      },
                    ),
                  ],
                ),
              )
            ],
          ),
        ),
        DesignConfig.smallHeightSizedBox,
        buildOrderItemsContainer()
      ],
    );
  }

  updateStatus() {
    Map<String, String> params = {};
    if (isDemoApp) {
      Utils.showSnackBar(message: demoModeOnKey, context: context);
      return;
    }
    if (controllers[orderStatusKey] != null &&
        controllers[orderStatusKey]!.text.trim().isNotEmpty) {
      params["status"] = controllers[orderStatusKey]!.text;
      if (widget.parcel.isReturnOrder == true) {
        params['order_item_id'] = widget.parcel.orderItems!.id.toString();
      } else {
        params['id'] = widget.parcel.id.toString();
        if (controllers[orderStatusKey]!.text == deliveredStatusType) {
          if (controllers[enterOtpKey]!.text.trim().isEmpty) {
            Utils.showSnackBar(message: enterOtpKey, context: context);
            return;
          } else {
            params['otp'] = controllers[enterOtpKey]!.text.trim();
          }
        }
      }
    }
    BlocProvider.of<OrderUpdateCubit>(context).updateOrder(
        context,
        params,
        1,
        widget.parcel.isReturnOrder == true
            ? ApiURL.updateReturnedOrderItemStatus
            : ApiURL.updateOrderItemStatus);
  }

  statusUpdateSuccess(OrderUpdateSuccess state) {
    if (widget.parcel.isReturnOrder == true &&
        widget.parcel.orderItems != null) {
      widget.parcel.orderItems!.activeStatus = state.status;
    }
    widget.parcel.activeStatus = state.status;

    widget.callback(widget.parcel);
  }

  buildOrderItemsContainer() {
    return Column(
      children: [
        Container(
            padding: const EdgeInsetsDirectional.symmetric(
                vertical: appContentHorizontalPadding),
            color: Theme.of(context).colorScheme.primaryContainer,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (widget.parcel.items != null &&
                    widget.parcel.items!.isNotEmpty)
                  ListView.separated(
                      separatorBuilder: (context, index) =>
                          DesignConfig.smallHeightSizedBox,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: widget.parcel.items!.length,
                      itemBuilder: (context, index) {
                        ParcelItems parcelItem = widget.parcel.items![index];
                        if (parcelItem.orderData != null &&
                            parcelItem.orderData!.isNotEmpty)
                          return buildProductContainer(
                              parcelItem.orderData!.first.image ?? "",
                              parcelItem.orderData!.first.productName ?? "",
                              parcelItem.orderData!.first,
                              parcelItem.orderData!.first.price.toString(),
                              parcelItem.orderData!.first.activeStatus!);
                        else
                          return const SizedBox.shrink();
                      }),
                if (widget.parcel.orderItems != null)
                  buildProductContainer(
                      widget.parcel.orderItems!.image ?? "",
                      widget.parcel.orderItems!.productName ?? "",
                      widget.parcel.orderItems!,
                      widget.parcel.orderItems!.price.toString(),
                      widget.parcel.orderItems!.activeStatus!),
                const Divider(
                  height: 12,
                  thickness: 0.5,
                ),
                if (widget.parcel.sellerDetails != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: appContentHorizontalPadding),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text.rich(
                                TextSpan(
                                  style: Theme.of(context).textTheme.bodyMedium,
                                  children: [
                                    TextSpan(
                                      text: context
                                          .read<SettingsAndLanguagesCubit>()
                                          .getTranslatedValue(
                                              labelKey: sellerKey),
                                      style: const TextStyle(
                                          fontSize: 16,
                                          fontWeight: FontWeight.bold),
                                    ),
                                    const TextSpan(
                                      text: ' : ',
                                    ),
                                    TextSpan(
                                        text: widget.parcel.sellerDetails!
                                                .sellerName ??
                                            "",
                                        style: TextStyle(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .primary,
                                            fontSize: 16,
                                            fontWeight: FontWeight.bold)),
                                  ],
                                ),
                              ),
                            ),
                            IconButton(
                                onPressed: () async {
                                  if (isMapOpened) return;
                                  openMapLocation(
                                    latitude: double.tryParse(widget
                                        .parcel.sellerDetails!.latitude
                                        .toString()),
                                    longitude: double.tryParse(widget
                                        .parcel.sellerDetails!.longitude
                                        .toString()),
                                    address:
                                        widget.parcel.sellerDetails!.address,
                                    context: context,
                                  );
                                },
                                icon: Icon(
                                  Icons.location_on_outlined,
                                  color: Theme.of(context).colorScheme.primary,
                                ))
                          ],
                        ),
                        CustomTextContainer(
                          textKey: widget.parcel.sellerDetails!.address ?? '',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        if ((widget.parcel.sellerDetails!.mobile ?? "")
                            .trim()
                            .isNotEmpty)
                          Text(
                            '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: phoneNumberKey)}: ${widget.parcel.sellerDetails!.mobile ?? ""}',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                      ],
                    ),
                  )
              ],
            )),
        DesignConfig.smallHeightSizedBox,
      ],
    );
  }

  Container buildProductContainer(String image, String productName,
      OrderItems orderItem, String price, String status) {
    return Container(
        padding: const EdgeInsetsDirectional.symmetric(
            horizontal: appContentHorizontalPadding),
        color: Theme.of(context).colorScheme.primaryContainer,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  CustomImageWidget(
                      url: image, width: 86, height: 100, borderRadius: 8),
                  DesignConfig.smallWidthSizedBox,
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
//if we are in the selected item, show all the details
                        CustomTextContainer(
                          textKey: productName,
                          maxLines: 2,
                          style: Theme.of(context).textTheme.titleSmall!,
                        ),
                        DesignConfig.smallHeightSizedBox,

                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            buildVariantList(orderItem),
                            CustomTextContainer(
                              textKey: Utils.priceWithCurrencySymbol(
                                  price: double.tryParse(price) ?? 0,
                                  context: context),
                              style: Theme.of(context)
                                  .textTheme
                                  .titleMedium
                                  ?.copyWith(
                                    color:
                                        Theme.of(context).colorScheme.primary,
                                  ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 2),
                        FittedBox(
                          child: Container(
                              padding: const EdgeInsetsDirectional.symmetric(
                                  horizontal: 12, vertical: 2),
                              decoration: ShapeDecoration(
                                color:
                                    Utils.getOrderStatusTextAndColor(status)[1]
                                        .withValues(alpha: 0.12),
                                shape: RoundedRectangleBorder(
                                  side: BorderSide(
                                      width: 0.50,
                                      color: Utils.getOrderStatusTextAndColor(
                                          status)[1]),
                                  borderRadius:
                                      BorderRadius.circular(borderRadius),
                                ),
                              ),
                              child: CustomLabelContainer(
                                textKey:
                                    Utils.getOrderStatusTextAndColor(status)[0],
                                isFieldValueMandatory: false,
                                textAlign: TextAlign.center,
                                style: Theme.of(context)
                                    .textTheme
                                    .bodyMedium!
                                    .copyWith(
                                        color: Utils.getOrderStatusTextAndColor(
                                            status)[1]),
                              )),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ));
  }

  Text buildTextRichWidget(
      String key, String value, TextStyle keyStyle, TextStyle valueStyle) {
    return Text.rich(
      TextSpan(
        style: Theme.of(context).textTheme.bodyMedium,
        children: [
          TextSpan(
              text: context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: key),
              style: keyStyle),
          const TextSpan(
            text: ' : ',
          ),
          TextSpan(
              text: context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: value),
              style: valueStyle),
        ],
      ),
      textAlign: TextAlign.center,
    );
  }

  buildVariantList(OrderItems orderItm) {
    if (orderItm.attrName!.isNotEmpty) {
      attributeList = orderItm.attrName!.split(',');

      variantValues = orderItm.variantValues!.split(',');
    }
    return Wrap(spacing: 8, runSpacing: 8, children: [
      if (attributeList.isNotEmpty)
        ...List.generate(attributeList.length, (index) {
          return Utils.buildVariantContainer(
              context,
              attributeList[index].trim().toString().capitalizeFirst!,
              variantValues[index]);
        }),
      if (orderItm.quantity != 0)
        Utils.buildVariantContainer(
            context, 'Qty', orderItm.quantity.toString())
    ]);
  }

  Future<void> openMapLocation({
    required double? latitude,
    required double? longitude,
    required BuildContext context,
    String? address,
  }) async {
    if (isMapOpened) return; // Prevent multiple taps

    setState(() {
      isMapOpened = true; // Set the flag to true
    });
    var googleMapsUrl = '';
    if (latitude != null || longitude != null) {
      googleMapsUrl =
          'https://www.google.com/maps/search/?api=1&query=$latitude,$longitude';
    } else {
      googleMapsUrl =
          'https://www.google.com/maps/search/?api=1&query=$address';
    }
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
