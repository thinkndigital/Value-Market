import 'package:value_market_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/features/orders/models/parcel.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../blocs/orderUpdateCubit.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';

class OrderTrackingContainer extends StatefulWidget {
  final Parcel parcel;
  final Function updatedItem;

  const OrderTrackingContainer(
      {Key? key, required this.parcel, required this.updatedItem})
      : super(key: key);

  @override
  _OrderTrackingContainerState createState() => _OrderTrackingContainerState();
}

class _OrderTrackingContainerState extends State<OrderTrackingContainer> {
  Map<String, TextEditingController> controllers = {};
  final _formKey = GlobalKey<FormState>();
  Map<String, FocusNode> focusNodes = {};

  final Map apiFields = {
    parcelIDKey: "parcel_id",
    courierAgencyKey: "courier_agency",
    trackingIDKey: "tracking_id",
    urlKey: "url"
  };

  @override
  void initState() {
    super.initState();
    apiFields.forEach((key, value) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    });

    controllers[parcelIDKey]!.text = widget.parcel.id.toString();
    if (widget.parcel.trackingDetails != null) {
      controllers[courierAgencyKey]!.text =
          widget.parcel.trackingDetails?.courierAgency ?? "";
      controllers[trackingIDKey]!.text =
          widget.parcel.trackingDetails?.trackingId ?? "";
      controllers[urlKey]!.text = widget.parcel.trackingDetails?.url ?? "";
    }
  }

  @override
  void dispose() {
    // Dispose all text editing controllers
    controllers.forEach((key, controller) {
      controller.dispose();
    });
    focusNodes.forEach((key, focusNode) {
      focusNode.dispose();
    });
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<OrderUpdateCubit, OrderUpdateState>(
      listener: (context, state) {
        if (state is OrderUpdateProgress && state.type == 2) {
          Utils.showLoader(context);
        } else {
          Utils.hideLoader(context);
        }
        if (state is OrderUpdateFailure && state.type == 2) {
          Utils.showSnackBar(message: state.errorMessage);
        } else if (state is OrderUpdateSuccess && state.type == 2) {
          Parcel parcel = widget.parcel;
          if (parcel.trackingDetails == null) {
            parcel.trackingDetails = TrackingDetails();
          }
          parcel.trackingDetails!.courierAgency =
              controllers[courierAgencyKey]!.text;
          parcel.trackingDetails!.trackingId = controllers[trackingIDKey]!.text;
          parcel.trackingDetails!.url = controllers[urlKey]!.text;
          widget.updatedItem(parcel);
          Utils.showSnackBar(message: state.successMsg);
        }
      },
      builder: (context, state) {
        return Form(
            key: _formKey,
            child: FilterContainerForBottomSheet(
              title: orderTrackingKey,
              borderedButtonTitle: resetKey,
              primaryButtonTitle: saveKey,
              borderedButtonOnTap: () {
                apiFields.forEach((key, value) {
                  controllers[key]!.clear();
                });

                setState(() {});
              },
              primaryButtonOnTap: () {
                if (_formKey.currentState!.validate()) {
                  Map<String, String> params = {};
                  apiFields.forEach((key, value) {
                    params[value] = controllers[key]!.text.trim();
                  });
                  BlocProvider.of<OrderUpdateCubit>(context).updateOrder(
                      context, params, 2, ApiURL.editOrderTracking);
                }
              },
              content: Column(
                children: <Widget>[
                  CustomTextFieldContainer(
                      hintTextKey: courierAgencyKey,
                      textEditingController: controllers[courierAgencyKey]!,
                      labelKey: courierAgencyKey,
                      textInputAction: TextInputAction.next,
                      focusNode: focusNodes[courierAgencyKey],
                      isFieldValueMandatory: true,
                      isSetValidator: true,
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[trackingIDKey])),
                  CustomTextFieldContainer(
                      hintTextKey: trackingIDKey,
                      textEditingController: controllers[trackingIDKey]!,
                      labelKey: trackingIDKey,
                      textInputAction: TextInputAction.next,
                      focusNode: focusNodes[trackingIDKey],
                      isFieldValueMandatory: true,
                      isSetValidator: true,
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[urlKey])),
                  CustomTextFieldContainer(
                      hintTextKey: urlKey,
                      textEditingController: controllers[urlKey]!,
                      labelKey: urlKey,
                      textInputAction: TextInputAction.done,
                      focusNode: focusNodes[urlKey],
                      isFieldValueMandatory: true,
                      keyboardType: TextInputType.url,
                      isSetValidator: true,
                      onFieldSubmitted: (v) => focusNodes[urlKey]!.unfocus()),
                ],
              ),
            ));
      },
    );
  }
}
