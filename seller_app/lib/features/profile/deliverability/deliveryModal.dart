import 'package:value_market_seller/commons/blocs/zoneListCubit.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/core/theme/colors.dart';
import 'package:value_market_seller/features/profile/deliverability/blocs/updateProductDeliverabilityCubit.dart';
import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_seller/features/mainScreen.dart';
import 'package:value_market_seller/features/profile/addProduct/widgets/helper/zoneSelectionDialog.dart';
import 'package:value_market_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_seller/commons/widgets/customDropDownContainer.dart';
import 'package:value_market_seller/commons/widgets/customRoundedButton.dart';
import 'package:value_market_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_seller/commons/widgets/errorScreen.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';

import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:value_market_seller/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class DeliveryModal extends StatefulWidget {
  final List<int> selectedProductIds;
  final Map<String, String> deliverableTypes;
  final Map<String, TextEditingController> controllers;
  final bool isComboProductScreen;
  final Function refreshAPI;
  DeliveryModal(
      {required this.selectedProductIds,
      required this.deliverableTypes,
      required this.controllers,
      required this.isComboProductScreen,
      required this.refreshAPI});

  @override
  _DeliveryModalState createState() => _DeliveryModalState();
}

class _DeliveryModalState extends State<DeliveryModal> {
  String? deliverableType;
  Map<String, dynamic> selectedZones = {};
  Map<String, String?> zoneApiParams = {};
  final Map<String, String> apiFormField = {
    productNameKey: "product_id",
    deliverableTypeKey: "deliverable_type",
    selectZonesKey: "deliverable_zones[]",
  };
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      zoneApiParams = {
        ApiURL.storeIdApiKey: context
            .read<UserDetailsCubit>()
            .getDefaultStoreOfUser(context)
            .storeId
            .toString(),
        //if the seller has set deliverable type to all then we'll remove seller id
        ApiURL.sellerIdApiKey: context
                    .read<UserDetailsCubit>()
                    .getDefaultStoreOfUser(context)
                    .deliverableType ==
                1
            ? null
            : context
                .read<UserDetailsCubit>()
                .getDefaultStoreOfUser(context)
                .sellerId
                .toString()
      };
    });
    if (widget.controllers[deliverableTypeKey]!.text == '2') {
      callZoneApi();
    }
  }

  Future<void> _updateDeliverability() async {
    Map<String, String> params = {
      ApiURL.storeIdApiKey: context
          .read<UserDetailsCubit>()
          .getDefaultStoreOfUser(context)
          .storeId!
          .toString(),
    };

    params[ApiURL.productIdApiKey] = widget.selectedProductIds.join(",");
    params[apiFormField[deliverableTypeKey]!] =
        widget.controllers[deliverableTypeKey]!.text;
    if ((selectedZones[selectZonesKey] ?? {}).isNotEmpty) {
      params[apiFormField[selectZonesKey]!] =
          (selectedZones[selectZonesKey]).keys.join(",");
    }

    context
        .read<UpdateProductDeliverabilityCubit>()
        .updateProductDeliverability(
            params: params,
            apiUrl: widget.isComboProductScreen
                ? ApiURL.updateComboProductDeliverability
                : ApiURL.updateProductDeliverability);
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16.0),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CustomDropDownContainer(
              labelKey: deliverableTypeKey,
              dropDownDisplayLabels: widget.deliverableTypes.values.toList(),
              selectedValue: widget.controllers[deliverableTypeKey]!.text,
              onChanged: (value) {
                setState(() {
                  widget.controllers[deliverableTypeKey]!.text =
                      value.toString();
                });
                if (value == '2') {
                  callZoneApi();
                }
              },
              values: widget.deliverableTypes.keys.toList()),
          if (isSelectZipCode(widget.controllers[deliverableTypeKey]!.text))
            BlocBuilder<ZoneListCubit, ZoneListState>(
              builder: (context, state) {
                if (state is ZoneListFetchSuccess) {
                  if (!selectedZones.containsKey(selectZonesKey)) {
                    selectedZones[selectZonesKey] = {};
                  }
                  return CustomTextFieldContainer(
                    hintTextKey: selectZonesKey,
                    textEditingController: widget.controllers[selectZonesKey]!,
                    labelKey: selectZonesKey,
                    textInputAction: TextInputAction.next,
                    isFieldValueMandatory: false,
                    focusNode: AlwaysDisabledFocusNode(),
                    isSetValidator: true,
                    errmsg: selectZonesKey,
                    maxLines: 3,
                    minLines: 1,
                    suffixWidget: const Icon(Icons.arrow_drop_down),
                    onTap: () {
                      if (state.brandList.isEmpty) return;
                      showDialog(
                        context: context,
                        barrierDismissible: false,
                        builder: (BuildContext mcontext) {
                          return AlertDialog(
                            insetPadding: const EdgeInsets.all(
                                appContentHorizontalPadding),
                            backgroundColor: white,
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 5),
                            shape:
                                DesignConfig.setRoundedBorder(white, 10, false),
                            content: ZoneSelectionDialog(
                              params: zoneApiParams,
                              selectedId: Map<String, String>.from(
                                  selectedZones[selectZonesKey]),
                              onZoneSelect: (Map<String, String> ids) {
                                selectedZones[selectZonesKey].clear();
                                selectedZones[selectZonesKey].addAll(ids);
                                setState(() {
                                  widget.controllers[selectZonesKey]!.text =
                                      ids.values.join(", ");
                                });
                                Navigator.pop(context);
                              },
                              zoneListCubit: context.read<ZoneListCubit>(),
                            ),
                          );
                        },
                      );
                    },
                  );
                }
                if (state is ZoneListFetchFailure) {
                  return ErrorScreen(
                    text: state.errorMessage,
                    child: state is ZoneListFetchProgress
                        ? CustomCircularProgressIndicator(
                            indicatorColor:
                                Theme.of(context).colorScheme.primary,
                          )
                        : null,
                    onPressed: () {
                      callZoneApi();
                    },
                  );
                }
                if (state is ZoneListFetchProgress) {
                  return CustomCircularProgressIndicator(
                      indicatorColor: Theme.of(context).colorScheme.primary);
                } else {
                  return const SizedBox.shrink();
                }
              },
            ),
          SizedBox(height: 16),
          buildUpdateButon()
        ],
      ),
    );
  }

  buildUpdateButon() {
    return BlocConsumer<UpdateProductDeliverabilityCubit,
        UpdateProductDeliverabilityState>(
      listener: (context, state) {
        if (state is UpdateProductDeliverabilitySuccess) {
          Navigator.of(context).pop();
          Utils.showSnackBar(message: state.successMessage);
          widget.refreshAPI();
          if (regularProductsCubit != null) {
            regularProductsCubit!.getProducts(
                storeId: context.read<StoresCubit>().getDefaultStore().id!,
                isComboProduct: false);
          }
          if (comboProductsCubit != null) {
            comboProductsCubit!.getProducts(
                storeId: context.read<StoresCubit>().getDefaultStore().id!,
                isComboProduct: true);
          }
        }
        if (state is UpdateProductDeliverabilityFailure) {
          Navigator.of(context).pop();
          Utils.showSnackBar(message: state.errorMessage);
        }
      },
      builder: (context, state) {
        return CustomRoundedButton(
          widthPercentage: 0.5,
          buttonTitle: submitKey,
          showBorder: false,
          onTap: _updateDeliverability,
          child: state is UpdateProductDeliverabilityProgress
              ? CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.onPrimary)
              : null,
        );
      },
    );
  }

  void callZoneApi() {
    context.read<ZoneListCubit>().getZoneList(context, zoneApiParams);
  }
}
