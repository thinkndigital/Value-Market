import 'dart:async';

import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customDropDownContainer.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/orders/models/parcel.dart';
import 'package:eshopplus_seller/features/orders/widgets/orderTrackingContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

import '../blocs/deliveryBoyCubit.dart';
import '../blocs/orderUpdateCubit.dart';
import '../models/deliveryBoy.dart';
import '../models/order.dart';

import '../../../../utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import '../../../commons/widgets/customRoundedButton.dart';
import '../../../commons/widgets/customTextContainer.dart';

class ParcelItemsContainer extends StatefulWidget {
  Parcel parcel;
  final Order order;
  final Function callback;

  ParcelItemsContainer({
    Key? key,
    required this.parcel,
    required this.order,
    required this.callback,
  }) : super(key: key);

  @override
  ParcelItemsContainerState createState() => ParcelItemsContainerState();
}

class ParcelItemsContainerState extends State<ParcelItemsContainer> {
  Map<String, TextEditingController> controllers = {};
  String? selectedDeliveryBoyId;

  final scrollController = ScrollController();
  late BuildContext customerdialogContext;
  final List formFields = [
    orderStatusKey,
    chooseDeliveryBoyKey,
  ];
  Map<String, String> statusTypes = {
    '': selectStatusKey,
  };
  @override
  void initState() {
    super.initState();

    statusTypes.addAll(Map.from(orderStatusTypes));
    statusTypes.remove(cancelledStatusType);
    statusTypes.remove(returnedStatusType);
    for (var key in formFields) {
      controllers[key] = TextEditingController();
    }

    controllers[chooseDeliveryBoyKey]!.text = context
        .read<SettingsAndLanguagesCubit>()
        .getTranslatedValue(labelKey: chooseDeliveryBoyKey);
    controllers[orderStatusKey]!.text = context
        .read<SettingsAndLanguagesCubit>()
        .getTranslatedValue(labelKey: selectStatusKey);

    controllers[orderStatusKey]!.text = widget.parcel.activeStatus ??
        statusTypes.entries.first
            .key; /* context
        .read<SettingsAndLanguagesCubit>()
        .getTranslatedValue(
            labelKey: /*   widget.parcel.activeStatus ?? */ statusTypes
                .entries.first.key); */
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
                  textKey: parcelManagementKey,
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
                        labelKey: parcelStatusKey,
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
                    BlocConsumer<DeliveryBoyCubit, DeliveryBoyState>(
                      listener: (context, state) {
                   
                        if (state is DeliveryBoySuccess) {
                          if (widget.parcel.deliveryBoyDetails != null) {
                            controllers[chooseDeliveryBoyKey]!.text =
                                widget.parcel.deliveryBoyDetails!.username ??
                                    widget.parcel.deliveryBoyDetails!.name ??
                                    '';
                            selectedDeliveryBoyId =
                                widget.parcel.deliveryBoyDetails!.id.toString();
                          }
                        }
                      },
                      builder: (context, state) {
                        if (state is DeliveryBoySuccess) {
                          return CustomTextFieldContainer(
                            hintTextKey: chooseDeliveryBoyKey,
                            textEditingController:
                                controllers[chooseDeliveryBoyKey]!,
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
                                  customerdialogContext = context;

                                  return AlertDialog(
                                    insetPadding: const EdgeInsets.all(
                                        appContentHorizontalPadding),
                                    backgroundColor: white,
                                    contentPadding: const EdgeInsets.symmetric(
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
                                                controllers[
                                                        chooseDeliveryBoyKey]!
                                                    .text = deliveryBoy
                                                        .name ??
                                                    '';
                                              });
                                            }
                                            Navigator.pop(
                                                customerdialogContext);
                                          });
                                    }),
                                  );
                                },
                              );
                            },
                          );
                        } else {
                          return const SizedBox.shrink();
                        }
                      },
                    ),
                    DesignConfig.defaultHeightSizedBox,
                    BlocConsumer<OrderUpdateCubit, OrderUpdateState>(
                      listener: (context, state) {
                        if (state is OrderUpdateFailure && state.type == 1) {
                          Utils.showSnackBar(message: state.errorMessage);
                        } else if (state is OrderUpdateSuccess &&
                            state.type == 1) {
                          Navigator.of(context).pop();
                          Utils.showSnackBar(message: state.successMsg);
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
                                backgroundColor:
                                    Theme.of(context).colorScheme.primary,
                                onTap: () {
                                  updateStatus();
                                },
                              );
                      },
                    ),
                    DesignConfig.defaultHeightSizedBox,
                    GestureDetector(
                      onTap: () => Utils.openModalBottomSheet(
                          context,
                          staticContent: true,
                          BlocProvider<OrderUpdateCubit>(
                            create: (context) => OrderUpdateCubit(),
                            child: OrderTrackingContainer(
                              parcel: widget.parcel,
                              updatedItem: (updatedparcel) {
                                widget.parcel = updatedparcel;
                                Navigator.of(context).pop();
                              },
                            ),
                          )),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: <Widget>[
                          Icon(
                            Icons.location_on_outlined,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                          CustomTextContainer(
                            textKey: addOrderTrackingKey,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium!
                                .copyWith(
                                    color:
                                        Theme.of(context).colorScheme.primary),
                          )
                        ],
                      ),
                    )
                  ],
                ),
              )
            ],
          ),
        ),
        DesignConfig.smallHeightSizedBox,
        buildParcelItemsContainer()
      ],
    );
  }

  updateStatus() {
    Map<String, String> params = {
      ApiURL.parcelIdApiKey: widget.parcel.id.toString(),
    };
    if (controllers[orderStatusKey] != null &&
        controllers[orderStatusKey]!.text.trim().isNotEmpty) {
      params[ApiURL.statusApiKey] = controllers[orderStatusKey]!.text;
    } else {
      Utils.showSnackBar(message: pleaseSelectStatusKey);
      return;
    }
    if (selectedDeliveryBoyId != null &&
        selectedDeliveryBoyId!.trim().isNotEmpty) {
      params[ApiURL.deliverByApiKey] = selectedDeliveryBoyId ?? "0";
    }
    if (isDemoApp) {
      Utils.showSnackBar(message: demoModeOnKey);
      return;
    }
    BlocProvider.of<OrderUpdateCubit>(context)
        .updateOrder(context, params, 1, ApiURL.updateParcelOrderStatus);
  }

  statusUpdateSuccess(OrderUpdateSuccess state) {
    if (selectedDeliveryBoyId != null) {
      widget.parcel.deliveryBoyId = selectedDeliveryBoyId;
      if (context.read<DeliveryBoyCubit>().state is DeliveryBoySuccess) {
        widget.parcel.deliveryBoyDetails = (context
                .read<DeliveryBoyCubit>()
                .state as DeliveryBoySuccess)
            .deliveryBoyList
            .firstWhereOrNull((e) => e.id.toString() == selectedDeliveryBoyId);
      }
    }
    widget.parcel.activeStatus = state.status;
    List<int> orderItemIds =
        widget.parcel.items!.map((e) => e.orderItemId!).toList();
    for (int i = 0; i < orderItemIds.length; i++) {
      OrderItems item = widget.order.orderItems!
          .firstWhere((element) => element.id == orderItemIds[i]);

      item.activeStatus = state.status;
    }
    widget.callback(widget.parcel);
  }

  buildParcelItemsContainer() {
    return Column(
      children: [
        Container(
            padding: const EdgeInsetsDirectional.symmetric(
                vertical: appContentHorizontalPadding),
            color: Theme.of(context).colorScheme.primaryContainer,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                    padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: appContentHorizontalPadding),
                    child: CustomTextContainer(
                      textKey: parcelItemsKey,
                      style: Theme.of(context).textTheme.titleMedium,
                    )),
                const Divider(
                  height: 12,
                  thickness: 0.5,
                ),
                ListView.separated(
                    separatorBuilder: (context, index) =>
                        DesignConfig.smallHeightSizedBox,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: widget.parcel.items!.length,
                    itemBuilder: (context, index) {
                      OrderItems orderItem =
                          widget.parcel.items![index].orderData!.first;

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
                                        url: orderItem.image ?? "",
                                        width: 86,
                                        height: 100,
                                        borderRadius: 8),
                                    DesignConfig.smallWidthSizedBox,
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: <Widget>[
                                          //if we are in the selected item, show all the details
                                          CustomTextContainer(
                                            textKey:
                                                orderItem.productName ?? "",
                                            maxLines: 2,
                                            style: Theme.of(context)
                                                .textTheme
                                                .titleSmall!,
                                          ),
                                          DesignConfig.smallHeightSizedBox,
                                          Utils.buildStatusWidget(
                                              context, orderItem.activeStatus!),

                                          const SizedBox(height: 4),
                                          Wrap(
                                            spacing: 8,
                                            runSpacing: 8,
                                            children: [
                                              Utils.buildVariantList(
                                                  context, orderItem),
                                              CustomTextContainer(
                                                textKey: Utils
                                                    .priceWithCurrencySymbol(
                                                        price: double.tryParse(
                                                                orderItem.price
                                                                    .toString()) ??
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
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ));
                    }),
              ],
            )),
        DesignConfig.smallHeightSizedBox,
      ],
    );
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
}

class DeliveryBoySelectionDialog extends StatefulWidget {
  final List<DeliveryBoy>? customers;
  final Function onCustomerSelect;
  final int? selectedId;

  const DeliveryBoySelectionDialog(
      {super.key,
      required this.customers,
      required this.onCustomerSelect,
      this.selectedId});

  @override
  State<StatefulWidget> createState() {
    return DeliveryBoySelectionDialogState();
  }
}

class DeliveryBoySelectionDialogState
    extends State<DeliveryBoySelectionDialog> {
  final TextEditingController _searchQuery = TextEditingController();
  String _searchText = "";
  bool isSearching = false;
  final scrollController = ScrollController();
  List<DeliveryBoy> orderlist = [];
  int currOffset = 0;
  Map<String, String>? apiParameter;
  @override
  void initState() {
    super.initState();
    apiParameter = {};
    isSearching = false;
    orderlist.addAll(widget.customers ?? []);
    setupScrollController(context);
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, String> parameter = {
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id.toString()
    };
    if (apiParameter != null) {
      parameter.addAll(apiParameter!);
    }
    context
        .read<DeliveryBoyCubit>()
        .getDeliveryboyList(context, parameter, isSetInitial: isSetInitialPage);
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

  DeliveryBoySelectionDialogState() {
    _searchQuery.addListener(() {
      searchDeliveryBoy();
    });
  }
  searchDeliveryBoy() {
    if (_searchQuery.text.trim().isEmpty) {
      setState(() {
        isSearching = false;
        _searchText = "";
      });
    } else {
      setState(() {
        isSearching = true;
        _searchText = _searchQuery.text;
      });
    }

    Future.delayed(const Duration(milliseconds: 500), () {
      if (_searchText.trim().isEmpty &&
          context.read<DeliveryBoyCubit>().state is DeliveryBoySuccess) {
        DeliveryBoyState successState = context.read<DeliveryBoyCubit>().state;
        context.read<DeliveryBoyCubit>().setOldList(
            (successState as DeliveryBoySuccess).currOffset,
            (successState).deliveryBoyList);
        return;
      }
      apiParameter!["search"] = _searchText;
      loadPage(isSetInitialPage: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      controller: scrollController,
      child: SizedBox(
        width: double.maxFinite,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Align(
            alignment: AlignmentDirectional.topEnd,
            child: IconButton(
                onPressed: () {
                  Navigator.of(context).pop();
                },
                icon: const Icon(Icons.cancel_outlined)),
          ),
          TextField(
            controller: _searchQuery,
            decoration: InputDecoration(
                enabledBorder: DesignConfig.setUnderlineInputBorder(grey),
                focusedBorder: DesignConfig.setUnderlineInputBorder(grey),
                border: DesignConfig.setUnderlineInputBorder(grey),
                prefixIcon: Icon(Icons.search, color: grey),
                hintText: context
                    .read<SettingsAndLanguagesCubit>()
                    .getTranslatedValue(labelKey: searchKey),
                hintStyle: TextStyle(color: grey)),
          ),
          ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(context).size.height * 0.6,
              ),
              child: BlocBuilder<DeliveryBoyCubit, DeliveryBoyState>(
                builder: (context, state) {
                  return dropdownListWidget(state);
                },
              )),
        ]),
      ),
    );
  }

  dropdownListWidget(DeliveryBoyState state) {
    if (state is DeliveryBoyProgress && state.isFirstFetch) {
      return Utils.loadingIndicator();
    } else if (state is DeliveryBoyFailure) {
      return Utils.msgWithTryAgain(
          context, state.errorMessage, () => loadPage(isSetInitialPage: true));
    }
    orderlist = [];
    bool isLoading = false;
    if (state is DeliveryBoyProgress) {
      orderlist = state.oldArchiveList;
      isLoading = true;
    } else if (state is DeliveryBoySuccess) {
      orderlist = state.deliveryBoyList;
    }
    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      shrinkWrap: true,
      itemCount: orderlist.length + (isLoading ? 1 : 0),
      padding: const EdgeInsets.symmetric(vertical: 8),
      separatorBuilder: (context, index) {
        return Divider(
          color: grey,
          thickness: 1,
        );
      },
      itemBuilder: (context, index) {
        if (index < orderlist.length) {
          DeliveryBoy deliveryboy = orderlist[index];

          return GestureDetector(
            onTap: () {
              widget.onCustomerSelect(deliveryboy);
            },
            child: Row(
              mainAxisSize: MainAxisSize.max,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  flex: 1,
                  child: Text(
                    deliveryboy.name ?? '',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium!
                        .merge(TextStyle(color: black)),
                  ),
                ),
                if (widget.selectedId == deliveryboy.id)
                  Icon(Icons.check, color: primaryColor),
              ],
            ),
          );
        } else {
          Timer(const Duration(milliseconds: 30), () {
            scrollController.jumpTo(scrollController.position.maxScrollExtent);
          });

          return Utils.loadingIndicator();
        }
      },
    );
  }
}
