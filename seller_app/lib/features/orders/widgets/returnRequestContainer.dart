import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_seller/commons/widgets/customImageWidget.dart';
import 'package:value_market_seller/commons/widgets/customRoundedButton.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/commons/widgets/customTextButton.dart';
import 'package:value_market_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_seller/commons/widgets/errorScreen.dart';
import 'package:value_market_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/core/theme/colors.dart';
import 'package:value_market_seller/features/orders/blocs/deliveryBoyCubit.dart';
import 'package:value_market_seller/features/orders/blocs/returnRequestCubit.dart';

import 'package:value_market_seller/features/orders/blocs/updateReturnRequestCubit.dart';
import 'package:value_market_seller/features/orders/models/deliveryBoy.dart';
import 'package:value_market_seller/features/orders/models/return_request.dart';
import 'package:value_market_seller/features/orders/parcel/parcelItemsContainer.dart';
import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/utils/inputValidators.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ReturnRequestContainer extends StatefulWidget {
  const ReturnRequestContainer({Key? key}) : super(key: key);

  @override
  _ReturnRequestContainerState createState() => _ReturnRequestContainerState();
}

class _ReturnRequestContainerState extends State<ReturnRequestContainer> {
  Map<int, String> statusTypes = {
    0: pendingKey,
    1: approvedKey,
    2: rejectedKey,
    3: returnedKey,
    8: returnPickedupKey
  };
  String? selectedDeliveryBoyId;
  TextEditingController deliveryBoyController = TextEditingController();

  bool _isStatusDisabled(int currentStatus, int newStatus) {
    // If same status is selected, it's not disabled (user can keep same status)

    if (currentStatus == newStatus) {
      return false;
    }

    // If current status is rejected (2), disable all other statuses
    if (currentStatus == 2) {
      return true;
    }

    // If current status is approved (1), disable pending (0)
    if (currentStatus == 1 && newStatus != 3) {
      return true;
    }

    // If current status is returned (3), disable all other statuses
    if (currentStatus == 3) {
      return true;
    }

    // If current status is return picked up (8), disable all other statuses
    if (currentStatus == 8 && newStatus != 3) {
      return true;
    }
    if (newStatus == 8) {
      return true;
    }
    //if status is approved then enable Returned status
    if (newStatus == 3 && currentStatus == 1) {
      return false;
    }
    return false;
  }

  @override
  void initState() {
    super.initState();

    getReturnRequests();
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ReturnRequestCubit, ReturnRequestState>(
      builder: (context, state) {
        if (state is ReturnRequestLoading) {
          return Center(child: CircularProgressIndicator());
        } else if (state is ReturnRequestFailure) {
          return ErrorScreen(text: state.error, onPressed: getReturnRequests);
        } else if (state is ReturnRequestSuccess) {
          if (state.requests.isEmpty) {
            return ErrorScreen(
              text: dataNotAvailableKey,
              onPressed: getReturnRequests,
            );
          }
          return NotificationListener<ScrollUpdateNotification>(
            onNotification: (notification) {
              if (notification.metrics.pixels >=
                  notification.metrics.maxScrollExtent) {
                if (context.read<ReturnRequestCubit>().hasMore()) {
                  loadMoreReturnRequests();
                }
              }
              return true;
            },
            child: RefreshIndicator(
              onRefresh: getReturnRequests,
              child: ListView.separated(
                separatorBuilder: (context, index) =>
                    const SizedBox(height: 10),
                itemCount: state.requests.length +
                    (context.read<ReturnRequestCubit>().hasMore() ? 1 : 0),
                padding: const EdgeInsets.all(appContentHorizontalPadding),
                itemBuilder: (context, index) {
                  if (context.read<ReturnRequestCubit>().hasMore()) {
                    if (index == state.requests.length) {
                      if (context.read<ReturnRequestCubit>().fetchMoreError()) {
                        return Center(
                          child: CustomTextButton(
                            buttonTextKey: retryKey,
                            onTapButton: () {
                              loadMoreReturnRequests();
                            },
                          ),
                        );
                      }
                      return Center(
                        child: CustomCircularProgressIndicator(
                          indicatorColor: Theme.of(context).colorScheme.primary,
                        ),
                      );
                    }
                  }
                  final req = state.requests[index];
                  return orderInfoContainerForReturnRequest(
                      req, context.read<ReturnRequestCubit>());
                },
              ),
            ),
          );
        }
        return SizedBox.shrink();
      },
    );
  }

  Future<void> getReturnRequests() {
    return context.read<ReturnRequestCubit>().fetchReturnRequests({
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id.toString(),
    });
  }

  void loadMoreReturnRequests() {
    context.read<ReturnRequestCubit>().loadMore({
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id.toString(),
    });
  }

  Widget orderInfoContainerForReturnRequest(
      ReturnRequest req, ReturnRequestCubit returnCubit) {
    return GestureDetector(
      onTap: () => _showReturnRequestStatusModal(req, returnCubit),
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
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      CustomTextContainer(
                        textKey: req.username,
                        style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                            color: Theme.of(context)
                                .colorScheme
                                .secondary
                                .withValues(alpha: 0.8)),
                      ),
                    ],
                  ),
                  const Spacer(),
                  Text(
                    req.orderId.toString().withOrderSymbol(),
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
            Padding(
              padding: const EdgeInsetsDirectional.symmetric(
                  horizontal: appContentHorizontalPadding),
              child: Row(
                children: <Widget>[
                  CustomImageWidget(
                      url: req.productImage ,
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
                                textKey: req.productName,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: Theme.of(context).textTheme.titleSmall!,
                              ),
                            ),
                            DesignConfig.smallWidthSizedBox,
                            Utils.buildReturnRequestStatusWidget(
                                context, req.status,
                                showBorder: false)
                          ],
                        ),
                        DesignConfig.smallHeightSizedBox,
                        CustomTextContainer(
                          textKey:
                              '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: qtyKey)} : ${req.quantity.toString()} x ${Utils.priceWithCurrencySymbol(price: double.tryParse(req.price.toString()) ?? 0, context: context)}',
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium!
                              .copyWith(
                                  color:
                                      Theme.of(context).colorScheme.secondary),
                        )
                      ],
                    ),
                  )
                ],
              ),
            ),
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
                              req.paymentMethod.toLowerCase()] ??
                          ''),
                  buildPaymentAndPriceRow(
                      orderTotalKey,
                      Utils.priceWithCurrencySymbol(
                          price: req.subTotal, context: context)),
                ],
              ),
            )
          ],
        ),
      ),
    );
  }

  void _showReturnRequestStatusModal(
      ReturnRequest req, ReturnRequestCubit returnRequestCubit) {
    int selectedStatus = req.status;
    TextEditingController deliverBoyController = TextEditingController();
    TextEditingController remarksController =
        TextEditingController(text: req.reason ?? '');
    Utils.openModalBottomSheet(
        context,
        BlocProvider(
          create: (context) => ReturnRequestUpdateCubit(),
          child: FilterContainerForBottomSheet(
            title: updateReturnRequestKey,
            borderedButtonTitle: '',
            primaryButtonTitle: 'sdsf',
            borderedButtonOnTap: () {},
            primaryButtonOnTap: () {},
            content: StatefulBuilder(
              builder: (context, setState) {
                return SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CustomTextContainer(
                          textKey: statusKey,
                          style: Theme.of(context).textTheme.bodyMedium),
                      DesignConfig.smallHeightSizedBox,
                      Container(
                        width: MediaQuery.of(context).size.width,
                        decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(borderRadius),
                            border: Border.all(
                                color: borderColor.withValues(alpha: 0.4))),
                        padding: const EdgeInsetsDirectional.symmetric(
                            horizontal: 15),
                        child: DropdownButton<int>(
                          isExpanded: true,
                          dropdownColor: Colors.white,
                          borderRadius: BorderRadius.circular(8),
                          underline: const SizedBox(),
                          icon: Icon(Icons.keyboard_arrow_down_sharp,
                              color: Theme.of(context).colorScheme.secondary),
                          items: statusTypes.entries.map((entry) {
                            bool isDisabled =
                                _isStatusDisabled(req.status, entry.key);
                            return DropdownMenuItem<int>(
                              value: entry.key,
                              enabled: !isDisabled,
                              child: CustomTextContainer(
                                textKey: entry.value,
                                style: Theme.of(context)
                                    .textTheme
                                    .bodyMedium!
                                    .copyWith(
                                      color: isDisabled
                                          ? Theme.of(context)
                                              .colorScheme
                                              .secondary
                                              .withValues(alpha: 0.4)
                                          : null,
                                    ),
                              ),
                            );
                          }).toList(),
                          onChanged: (value) {
                            if (value != null &&
                                !_isStatusDisabled(req.status, value)) {
                              setState(() {
                                selectedStatus = value;
                         
                                if (selectedStatus == 1) {
                                  if (context.read<DeliveryBoyCubit>().state
                                      is! DeliveryBoySuccess) {
                                    context
                                        .read<DeliveryBoyCubit>()
                                        .getDeliveryboyList(context, {
                                      ApiURL.storeIdApiKey: context
                                          .read<StoresCubit>()
                                          .getDefaultStore()
                                          .id
                                          .toString()
                                    });
                                  }
                                }
                              });
                            }
                          },
                          value: selectedStatus,
                        ),
                      ),
                      if (selectedStatus == 1)
                        BlocBuilder<DeliveryBoyCubit, DeliveryBoyState>(
                          builder: (context, state) {
                            if (state is DeliveryBoySuccess) {
                              return CustomTextFieldContainer(
                                hintTextKey: chooseDeliveryBoyKey,
                                textEditingController: deliverBoyController,
                                labelKey: chooseDeliveryBoyKey,
                                textInputAction: TextInputAction.next,
                                isFieldValueMandatory: false,
                                focusNode: AlwaysDisabledFocusNode(),
                                isSetValidator: true,
                                errmsg: selectStoreKey,
                                suffixWidget: const Icon(Icons.arrow_drop_down),
                                onTap: () {
                                  if (state.deliveryBoyList.isEmpty) return;

                                  showDialog(
                                    context: context,
                                    barrierDismissible: false,
                                    builder: (BuildContext context) {
                                      return AlertDialog(
                                        insetPadding: const EdgeInsets.all(
                                            appContentHorizontalPadding),
                                        backgroundColor: white,
                                        contentPadding:
                                            const EdgeInsets.symmetric(
                                                horizontal: 8, vertical: 5),
                                        shape: DesignConfig.setRoundedBorder(
                                            white, 10, false),
                                        content: StatefulBuilder(builder:
                                            (context, StateSetter setState) {
                                          return DeliveryBoySelectionDialog(
                                              customers: state.deliveryBoyList,
                                              onCustomerSelect:
                                                  (DeliveryBoy deliveryBoy) {
                                                if (selectedDeliveryBoyId !=
                                                    deliveryBoy.id.toString()) {
                                                  selectedDeliveryBoyId =
                                                      deliveryBoy.id.toString();
                                                  setState(() {
                                                    deliverBoyController.text =
                                                        deliveryBoy.name ?? '';
                                                  });
                                                }
                                                Navigator.pop(context);
                                              });
                                        }),
                                      );
                                    },
                                  );
                                },
                              );
                            }
                            if (state is DeliveryBoyProgress) {
                              return CustomCircularProgressIndicator(
                                indicatorColor:
                                    Theme.of(context).colorScheme.primary,
                              );
                            }
                            if (state is DeliveryBoyFailure) {
                              return CustomTextContainer(
                                  textKey: state.errorMessage);
                            } else {
                              return const SizedBox.shrink();
                            }
                          },
                        ),
                      DesignConfig.defaultHeightSizedBox,
                      CustomTextFieldContainer(
                        hintTextKey: addRemarksKey,
                        textEditingController: remarksController,
                        labelKey: remarksKey,
                        textInputAction: TextInputAction.done,
                        isFieldValueMandatory: false,
                        maxLines: 3,
                      ),
                    ],
                  ),
                );
              },
            ),
            primaryChild: BlocConsumer<ReturnRequestUpdateCubit,
                ReturnRequestUpdateState>(
              listener: (context, state) {
                if (state is ReturnRequestUpdateSuccess) {
                  Navigator.of(context).pop();
                  // Refresh the list after update
                  final storeId =
                      this.context.read<StoresCubit>().getDefaultStore().id;
                  returnRequestCubit.fetchReturnRequests({
                    ApiURL.storeIdApiKey: storeId.toString(),
                  });
                  Utils.showSnackBar(message: state.message);
                } else if (state is ReturnRequestUpdateFailure) {
                  Utils.showSnackBar(message: state.error);
                }
              },
              builder: (context, state) {
                return CustomRoundedButton(
                  widthPercentage: 1.0,
                  buttonTitle: updateKey,
                  showBorder: false,
                  child: state is ReturnRequestUpdateLoading
                      ? const CustomCircularProgressIndicator()
                      : null,
                  onTap: () {
                    context
                        .read<ReturnRequestUpdateCubit>()
                        .updateReturnRequestStatus(
                          status: selectedStatus,
                          returnRequestId: req.id,
                          orderItemId: req.orderItemId,
                          deliverBy: selectedStatus == 1 &&
                                  deliverBoyController.text.isNotEmpty
                              ? int.tryParse(selectedDeliveryBoyId ?? '')
                              : null,
                          remarks: remarksController.text.trim().isNotEmpty
                              ? remarksController.text.trim()
                              : null,
                        );
                  },
                );
              },
            ),
          ),
        ),
        staticContent: false);
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
}
