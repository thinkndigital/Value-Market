import 'dart:io';

import 'package:eshopplus_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/orders/blocs/parcel/createOrderParcelCubit.dart';
import 'package:eshopplus_seller/features/orders/blocs/parcel/getParcelCubit.dart';
import 'package:eshopplus_seller/features/orders/models/order.dart';
import 'package:eshopplus_seller/features/orders/models/parcel.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class CreateParcelButton extends StatefulWidget {
  final Order order;
  final List<int> selectedOrders;
  final List<Parcel> parcels;
  final ParcelCubit parcelCubit;
  final Function? callback;
  const CreateParcelButton(
      {Key? key,
      required this.order,
      required this.selectedOrders,
      required this.parcels,
      required this.parcelCubit,
      required this.callback})
      : super(key: key);

  @override
  _CreateParcelButtonState createState() => _CreateParcelButtonState();
}

class _CreateParcelButtonState extends State<CreateParcelButton> {
  final GlobalKey<FormState> formkey = GlobalKey<FormState>();
  TextEditingController parcelTitleController = TextEditingController();
  @override
  void dispose() {
    parcelTitleController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return CustomBottomButtonContainer(
      bottomPadding: Platform.isIOS ? 10 : 0,
      child: CustomRoundedButton(
        height: 40,
        widthPercentage: 0.35,
        buttonTitle: createParcelKey,
        showBorder: false,
        backgroundColor: widget.selectedOrders.isEmpty
            ? Theme.of(context).colorScheme.secondary.withValues(alpha: 0.67)
            : Theme.of(context).colorScheme.primary,
        onTap: () {
          List<OrderItems> orderItems = List.from(widget.order.orderItems!);

          orderItems.retainWhere(
              (element) => widget.selectedOrders.contains(element.id!));

          //we need to remove the already parceled items  from the list
          if (widget.selectedOrders.isNotEmpty) {
            Utils.openModalBottomSheet(
                    context,
                    staticContent: false,
                    itemListContainer(
                        orderItems, context.read<CreateOrderParcelCubit>()))
                .then((value) {
              parcelTitleController.clear();
            });
          } else {
            Utils.showSnackBar(message: selectProductForParcelKey);
            return;
          }
        },
      ),
    );
  }

  Widget itemListContainer(List<OrderItems> orderItems,
      CreateOrderParcelCubit createOrderParcelCubit) {
    return StatefulBuilder(builder: (context, StateSetter setState) {
      return GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        child: Form(
            key: formkey,
            child: FilterContainerForBottomSheet(
              title: parcelDetailsKey,
              borderedButtonTitle: cancelKey,
              primaryButtonTitle: createParcelKey,
              borderedButtonOnTap: () {
                Navigator.of(context).pop();
              },
              primaryButtonOnTap: () {},
              primaryChild:
                  BlocConsumer<CreateOrderParcelCubit, CreateOrderParcelState>(
                      bloc: createOrderParcelCubit,
                      listener: (context, state) {
                        if (state is CreateOrderParcelSuccess) {
                          Navigator.of(context).pop();

                          widget.parcels.add(state.parcel);

                          widget.parcelCubit.updateList(widget.parcels);

                          state.parcel.items!.forEach((e) {
                            widget.order.orderItems!
                                .firstWhere(
                                    (element) => element.id == e.orderItemId)
                                .activeStatus = processedStatusType;
                          });
                          widget.callback!(widget.order);

                          setState(() {});
                          Utils.showSnackBar(message: state.successMessage);
                        }
                        if (state is CreateOrderParcelFailure) {
                          Navigator.of(context).pop();
                          Utils.showSnackBar(message: state.errorMessage);
                        }
                      },
                      builder: (context, state) {
                        return CustomRoundedButton(
                            widthPercentage: 1.0,
                            buttonTitle: createParcelKey,
                            showBorder: false,
                            child: state is CreateOrderParcelProgress
                                ? const CustomCircularProgressIndicator()
                                : null,
                            onTap: () {
                              if (formkey.currentState!.validate()) {
                                if (state is CreateOrderParcelProgress) {
                                  return;
                                }
                                final errorMessage = validateSelectedOrders(
                                    orderItems, widget.selectedOrders);
                                if (errorMessage != null) {
                                  Navigator.of(context).pop();
                                  Utils.showSnackBar(message: errorMessage);
                                } else {
                                  if (isDemoApp) {
                                    Navigator.of(context).pop();
                                    Utils.showSnackBar(
                                      message: demoModeOnKey,
                                    );
                                    return;
                                  }

                                  createOrderParcelCubit
                                      .createOrderParcel(params: {
                                    ApiURL.orderIdApiKey:
                                        widget.order.id.toString(),
                                    ApiURL.selectedItemsApiKey:
                                        widget.selectedOrders.join(','),
                                    ApiURL.parcelTitleApiKey:
                                        parcelTitleController.text.trim(),
                                    ApiURL.parcelOrderTypeApiKey: widget
                                        .order.orderItems!
                                        .firstWhere((element) =>
                                            element.id ==
                                            widget.selectedOrders.first)
                                        .orderType,
                                  });
                                }
                              }
                            });
                      }),
              content: Column(
                children: [
                  CustomTextFieldContainer(
                    hintTextKey: parcelTitleKey,
                    textEditingController: parcelTitleController,
                    labelKey: parcelTitleKey,
                    textInputAction: TextInputAction.newline,
                    keyboardType: TextInputType.multiline,
                    maxLines: 3,
                    minLines: 1,
                    validator: (v) =>
                        Validator.emptyValueValidation(v, context),
                  ),
                  DesignConfig.defaultHeightSizedBox,
                  Container(
                    padding: const EdgeInsetsDirectional.all(12),
                    decoration: BoxDecoration(
                        color: Theme.of(context).scaffoldBackgroundColor,
                        borderRadius: BorderRadius.circular(borderRadius)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        CustomTextContainer(
                          textKey: parcelTitleKey,
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        DesignConfig.defaultHeightSizedBox,
                        ListView.separated(
                            separatorBuilder: (context, index) =>
                                DesignConfig.smallHeightSizedBox,
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: orderItems.length,
                            itemBuilder: (context, index) {
                              OrderItems orderItem = orderItems[index];

                              return ListTile(
                                leading: CustomImageWidget(
                                    url: orderItem.image ?? "",
                                    width: 60,
                                    height: 68,
                                    borderRadius: borderRadius),
                                title: CustomTextContainer(
                                  textKey: orderItem.productName ?? "",
                                  maxLines: 2,
                                  style:
                                      Theme.of(context).textTheme.titleSmall!,
                                  overflow: TextOverflow.visible,
                                ),
                                subtitle: Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: [
                                    Utils.buildVariantList(context, orderItem,
                                        backgroundColor: Colors.white),
                                    CustomTextContainer(
                                      textKey: Utils.priceWithCurrencySymbol(
                                          price: double.tryParse(
                                                  orderItem.price.toString()) ??
                                              0,
                                          context: context),
                                      style: Theme.of(context)
                                          .textTheme
                                          .titleMedium
                                          ?.copyWith(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .primary,
                                          ),
                                    ),
                                  ],
                                ),
                                dense: true,
                                contentPadding: EdgeInsets.zero,
                                minVerticalPadding: 1,
                              );
                            }),
                      ],
                    ),
                  ),
                ],
              ),
            )),
      );
    });
  }

  String? validateSelectedOrders(
      List<OrderItems> orderItems, List<int> selectedOrders) {
    // Define invalid statuses
    final Set<String> invalidStatuses = {
      draftStatusType,
      awaitingStatusType,
      cancelledStatusType,
      deliveredStatusType
    };

    // Filter selected orders that have invalid statuses
    final invalidItems = orderItems.where((item) =>
        selectedOrders.contains(item.id) &&
        invalidStatuses.contains(item.activeStatus));

    if (invalidItems.isNotEmpty) {
      return invalidStatusesForParcelKey;
    }

    return null; // Validation passed, no error
  }
}
