import 'dart:async';

import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customDropDownContainer.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/orders/models/parcel.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../blocs/deliveryBoyCubit.dart';
import '../blocs/orderUpdateCubit.dart';
import '../models/deliveryBoy.dart';
import '../models/order.dart';

import '../../../utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import '../../../commons/widgets/customRoundedButton.dart';
import '../../../commons/widgets/customTextContainer.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';

class OrderStatusContainer extends StatefulWidget {
  final OrderItems orderItem;
  final Order order;
  final Function callback;
  final Parcel? parcel;
  OrderStatusContainer(
      {Key? key,
      required this.orderItem,
      required this.order,
      this.parcel,
      required this.callback})
      : super(key: key);

  @override
  OrderStatusContainerState createState() => OrderStatusContainerState();
}

class OrderStatusContainerState extends State<OrderStatusContainer> {
  Map<String, TextEditingController> controllers = {};
  String? selectedDeliveryBoyId;

  late OrderItems selectedOrder;
  List<int> selectedOrders = [];
  List attributeList = [], variantValues = [];
  final scrollController = ScrollController();
  late BuildContext customerdialogContext;
  final List formFields = [
    orderStatusKey,
    chooseDeliveryBoyKey,
  ];
  Map<String, String> statusTypes = Map.from(orderStatusTypes);
  @override
  void initState() {
    super.initState();
    if (widget.order.type == digitalProductType) {
      statusTypes = {
        receivedStatusType: receivedKey,
        deliveredStatusType: deliveredKey,
      };
    }

    for (var key in formFields) {
      controllers[key] = TextEditingController();
    }
    changeSelectedItem(widget.orderItem.id!);
    selectedDeliveryBoyId = widget.orderItem.deliveryBoyId ?? "";

    if (selectedDeliveryBoyId != null &&
        selectedDeliveryBoyId!.trim().isNotEmpty) {
      controllers[chooseDeliveryBoyKey]!.text =
          widget.orderItem.deliveryBoyName ?? "";
    } else {
      controllers[chooseDeliveryBoyKey]!.text = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: chooseDeliveryBoyKey);
      controllers[orderStatusKey]!.text = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: selectStatusKey);
    }
    if (widget.orderItem.activeStatus != null &&
        widget.orderItem.activeStatus!.trim().isNotEmpty) {
      String? selectedkey;
      for (var element in orderStatusTypes.keys) {
        if (element == widget.orderItem.activeStatus) {
          selectedkey = element;
          break;
        }
      }
      if (selectedkey != null && orderStatusTypes.containsKey(selectedkey)) {
        controllers[orderStatusKey]!.text = selectedkey;
      } else {
        controllers[orderStatusKey]!.text = orderStatusTypes.values.last;
      }
    }
  }

  changeSelectedItem(int id) {
    selectedOrder =
        widget.order.orderItems!.firstWhere((element) => element.id == id);
    if (selectedOrders.contains(id)) {
      selectedOrders.remove(id);
    } else {
      selectedOrders.add(id);
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
                    if (widget.order.type != digitalProductType) ...[
                      BlocBuilder<DeliveryBoyCubit, DeliveryBoyState>(
                        builder: (context, state) {
                          if (state is DeliveryBoySuccess) {
                            return CustomTextFieldContainer(
                              hintTextKey: chooseDeliveryBoyKey,
                              textEditingController:
                                  controllers[chooseDeliveryBoyKey]!,
                              labelKey: chooseDeliveryBoyKey,
                              textInputAction: TextInputAction.next,
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
                                      contentPadding:
                                          const EdgeInsets.symmetric(
                                              horizontal: 8, vertical: 5),
                                      shape: DesignConfig.setRoundedBorder(
                                          white, 10, false),
                                      content: DeliveryBoySelectionDialog(
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
                    ],
                    DesignConfig.defaultHeightSizedBox,
                    BlocConsumer<OrderUpdateCubit, OrderUpdateState>(
                      listener: (context, state) {
                        if (state is OrderUpdateFailure && state.type == 1) {
                          Navigator.of(context).pop();
                          Utils.showSnackBar(
                              message: state.errorMessage);
                        } else if (state is OrderUpdateSuccess &&
                            state.type == 1) {
                          Navigator.of(context).pop();
                          Utils.showSnackBar(
                              message: state.successMsg);
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
        if (widget.order.type == digitalProductType) ...[
          DesignConfig.smallHeightSizedBox,
          buildOrderItemsContainer()
        ]
      ],
    );
  }

  updateStatus() {
    Map<String, String> params = {
      ApiURL.orderIdApiKey: widget.orderItem.orderId.toString(),
      "seller_id": widget.orderItem.sellerId.toString(),
    };
    if (controllers[orderStatusKey] != null &&
        controllers[orderStatusKey]!.text.trim().isNotEmpty) {
      params[ApiURL.statusApiKey] = controllers[orderStatusKey]!.text;
    }
    if (widget.order.type == digitalProductType) {
      //for digital product
      //status : received/delivered

      params[ApiURL.orderItemIdsApiKey] = selectedOrders.join(',');
      params[ApiURL.typeApiKey] = 'digital';
    } else {
      //for physical product
      //status : received,processed,shipped,delivered,cancelled,returned

      params[ApiURL.parcelIdApiKey] = widget.parcel!.id.toString();
      if (selectedDeliveryBoyId != null &&
          selectedDeliveryBoyId!.trim().isNotEmpty) {
        params[ApiURL.deliverByApiKey] = selectedDeliveryBoyId ?? "0";
      }
    }

    BlocProvider.of<OrderUpdateCubit>(context)
        .updateOrder(context, params, 1, ApiURL.updateParcelOrderStatus);
  }

  statusUpdateSuccess(OrderUpdateSuccess state) {
    if (widget.parcel != null) {
      if (selectedDeliveryBoyId != null) {
        widget.parcel!.deliveryBoyId = selectedDeliveryBoyId;
      }
      widget.parcel!.activeStatus = state.status;
      List<int> orderItemIds =
          widget.parcel!.items!.map((e) => e.orderItemId!).toList();
      for (int i = 0; i < orderItemIds.length; i++) {
        OrderItems item = widget.order.orderItems!
            .firstWhere((element) => element.id == orderItemIds[i]);

        item.activeStatus = state.status;
      }
      widget.callback(widget.parcel!);
    } else {
      List<int> orderItemIds = [];
      if (state.orderItemId != null) {
        orderItemIds.add(int.parse(state.orderItemId!));
      }
      for (int i = 0; i < orderItemIds.length; i++) {
        OrderItems item = widget.order.orderItems!
            .firstWhere((element) => element.id == orderItemIds[i]);

        item.activeStatus = state.status;
        widget.callback(item);
      }
    }
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
                Padding(
                    padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: appContentHorizontalPadding),
                    child: CustomTextContainer(
                      textKey: orderItemsKey,
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
                    itemCount: widget.order.orderItems!.length,
                    itemBuilder: (context, index) {
                      OrderItems orderItem = widget.order.orderItems![index];

                      return GestureDetector(
                        onTap: () {
                          setState(() {
                            selectedOrder = orderItem;
                            changeSelectedItem(orderItem.id!);
                          });
                        },
                        child: Container(
                            padding: const EdgeInsetsDirectional.symmetric(
                                horizontal: appContentHorizontalPadding),
                            color:
                                Theme.of(context).colorScheme.primaryContainer,
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
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
                                            buildTextRichWidget(
                                              productStatusKey,
                                              orderItem.activeStatus ?? "",
                                              TextStyle(
                                                  color: Theme.of(context)
                                                      .colorScheme
                                                      .secondary
                                                      .withValues(alpha: 0.8)),
                                              TextStyle(
                                                  color: Theme.of(context)
                                                      .colorScheme
                                                      .primary),
                                            ),
                                            if (orderItem.deliveryBoyName != '')
                                              buildTextRichWidget(
                                                deliverByKey,
                                                orderItem.deliveryBoyName ?? "",
                                                TextStyle(
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .secondary
                                                        .withValues(
                                                            alpha: 0.8)),
                                                TextStyle(
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .secondary
                                                        .withValues(
                                                            alpha: 0.67)),
                                              ),

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
                                                                  orderItem
                                                                      .price
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
                                Radio(
                                    visualDensity: const VisualDensity(
                                        vertical: -4, horizontal: -4),
                                    value:
                                        selectedOrders.contains(orderItem.id),
                                    groupValue: true,
                                    onChanged: (value) {
                                      setState(() {
                                        selectedOrder = orderItem;
                                        changeSelectedItem(orderItem.id!);
                                      });
                                    }),
                              ],
                            )),
                      );
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
