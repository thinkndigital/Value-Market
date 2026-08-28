import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/profile/pickupLocation/blocs/addPickupLocationCubit.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import '../../../../utils/utils.dart';

import '../../../../commons/widgets/customRoundedButton.dart';
import '../../../../commons/widgets/customTextFieldContainer.dart';

class AddPickupLocationScreen extends StatefulWidget {
  final Function? callback;
  const AddPickupLocationScreen({Key? key, this.callback}) : super(key: key);

  static Widget getRouteInstance() {
    Map arguments = Get.arguments;
    return BlocProvider<AddPickupLocationCubit>(
      create: (context) => AddPickupLocationCubit(),
      child: AddPickupLocationScreen(
        callback:
            arguments.containsKey('callback') ? arguments['callback'] : null,
      ),
    );
  }

  @override
  AddPickupLocationScreenState createState() => AddPickupLocationScreenState();
}

class AddPickupLocationScreenState extends State<AddPickupLocationScreen> {
  Map<String, TextEditingController> controllers = {};
  Map<String, FocusNode> focusNodes = {};
  final _formKey = GlobalKey<FormState>();
  Map apiField = {
    pickupLocationNameKey: "pickup_location",
    nameKey: "name",
    emailKey: "email",
    phoneNumberKey: "phone",
    countryKey: "country",
    stateKey: "state",
    cityKey: "city",
    pincodeKey: "pincode",
    addressKey: "address",
    additionalAddressKey: "address2",
    latitudeKey: "latitude",
    longitudeKey: "longitude",
  };

  @override
  void initState() {
    super.initState();
    apiField.forEach((key, value) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    });
  }

  @override
  void dispose() {
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
    return Scaffold(
        appBar: const CustomAppbar(
          titleKey: addPickupLocKey,
          showBackButton: true,
        ),
        body: GestureDetector(
          onTap: () => FocusScope.of(context).unfocus(),
          child: Form(
            key: _formKey,
            child: SingleChildScrollView(
              padding:
                  const EdgeInsetsDirectional.all(appContentHorizontalPadding),
              child: Column(
                children: <Widget>[
                  CustomTextFieldContainer(
                      hintTextKey: pickupLocationNameKey,
                      textEditingController:
                          controllers[pickupLocationNameKey]!,
                      labelKey: pickupLocationNameKey,
                      textInputAction: TextInputAction.next,
                      keyboardType: TextInputType.text,
                      focusNode: focusNodes[pickupLocationNameKey],
                      isSetValidator: true,
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[nameKey])),
                  CustomTextFieldContainer(
                      hintTextKey: nameKey,
                      textEditingController: controllers[nameKey]!,
                      labelKey: nameKey,
                      textInputAction: TextInputAction.next,
                      keyboardType: TextInputType.text,
                      isSetValidator: true,
                      focusNode: focusNodes[nameKey],
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[emailKey])),
                  CustomTextFieldContainer(
                      hintTextKey: emailKey,
                      textEditingController: controllers[emailKey]!,
                      labelKey: emailKey,
                      textInputAction: TextInputAction.next,
                      keyboardType: TextInputType.emailAddress,
                      isSetValidator: true,
                      focusNode: focusNodes[emailKey],
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[phoneNumberKey])),
                  CustomTextFieldContainer(
                      hintTextKey: phoneNumberKey,
                      textEditingController: controllers[phoneNumberKey]!,
                      labelKey: phoneNumberKey,
                      textInputAction: TextInputAction.next,
                      keyboardType: TextInputType.phone,
                      isSetValidator: true,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                        LengthLimitingTextInputFormatter(10),
                      ],
                      validator: (v) {
                        if (v == null || v.isEmpty) {
                          return 'The phone must be 10 digits.';
                        }

                        // Regular expression to check if the phone number starts with 9, 8, 7, or 6 and has exactly 10 digits
                        final phoneRegex = RegExp(r'^[6-9]\d{9}$');

                        if (!phoneRegex.hasMatch(v)) {
                          return "Phone no. is not valid. It should start with '9/8/7/6' and should be of length 10.";
                        }

                        return null; // Valid phone number
                      },
                      focusNode: focusNodes[phoneNumberKey],
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[countryKey])),
                  Row(
                    children: [
                      Expanded(
                        child: CustomTextFieldContainer(
                            hintTextKey: countryKey,
                            textEditingController: controllers[countryKey]!,
                            labelKey: countryKey,
                            textInputAction: TextInputAction.next,
                            keyboardType: TextInputType.text,
                            isSetValidator: true,
                            focusNode: focusNodes[countryKey],
                            onFieldSubmitted: (v) => FocusScope.of(context)
                                .requestFocus(focusNodes[stateKey])),
                      ),
                      SizedBox(
                        width: appContentHorizontalPadding,
                      ),
                      Expanded(
                        child: CustomTextFieldContainer(
                            hintTextKey: stateKey,
                            textEditingController: controllers[stateKey]!,
                            labelKey: stateKey,
                            textInputAction: TextInputAction.next,
                            keyboardType: TextInputType.text,
                            isSetValidator: true,
                            focusNode: focusNodes[stateKey],
                            onFieldSubmitted: (v) => FocusScope.of(context)
                                .requestFocus(focusNodes[cityKey])),
                      ),
                    ],
                  ),
                  Row(
                    children: [
                      Expanded(
                        child: CustomTextFieldContainer(
                            hintTextKey: cityKey,
                            textEditingController: controllers[cityKey]!,
                            labelKey: cityKey,
                            textInputAction: TextInputAction.next,
                            keyboardType: TextInputType.text,
                            isSetValidator: true,
                            focusNode: focusNodes[cityKey],
                            onFieldSubmitted: (v) => FocusScope.of(context)
                                .requestFocus(focusNodes[pincodeKey])),
                      ),
                      DesignConfig.defaultWidthSizedBox,
                      Expanded(
                        child: CustomTextFieldContainer(
                            hintTextKey: pincodeKey,
                            textEditingController: controllers[pincodeKey]!,
                            labelKey: pincodeKey,
                            textInputAction: TextInputAction.next,
                            isSetValidator: true,
                            keyboardType: TextInputType.number,
                            inputFormatters: [
                              FilteringTextInputFormatter.digitsOnly,
                              LengthLimitingTextInputFormatter(6),
                            ],
                            validator: (v) {
                              if (v == null || v.isEmpty) {
                                return 'The pincode must be 6 digits.';
                              }

                              // Regular expression to check if the phone number starts with 9, 8, 7, or 6 and has exactly 10 digits
                              final reg = RegExp(r'^[0-9]\d{5}$');

                              if (!reg.hasMatch(v)) {
                                return "Pincode is not valid. It should be of length 6.";
                              }

                              return null; 
                            },
                            focusNode: focusNodes[pincodeKey],
                            onFieldSubmitted: (v) => FocusScope.of(context)
                                .requestFocus(focusNodes[addressKey])),
                      ),
                    ],
                  ),
                  CustomTextFieldContainer(
                      hintTextKey: addressKey,
                      textEditingController: controllers[addressKey]!,
                      labelKey: addressKey,
                      sublabelKey: additionalAddressInfoKey,
                      maxLines: 3,
                      textInputAction: TextInputAction.next,
                      keyboardType: TextInputType.text,
                      isSetValidator: true,
                      focusNode: focusNodes[addressKey],
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Address line 1 cannot be empty.';
                        }
                        if (value.length < 10) {
                          return 'Address line 1 must be at least 10 characters.';
                        }

                        // Regex to check if the address contains House no / Flat no / Road no
                        final regex = RegExp(r'\d+');
                        if (!regex.hasMatch(value)) {
                          return 'Address line 1 should include "House no", "Flat no", or "Road no".';
                        }
                        return null;
                      },
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[additionalAddressKey])),
                  CustomTextFieldContainer(
                      hintTextKey: additionalAddressKey,
                      textEditingController: controllers[additionalAddressKey]!,
                      labelKey: additionalAddressKey,
                      maxLines: 3,
                      textInputAction: TextInputAction.next,
                      isSetValidator: true,
                      keyboardType: TextInputType.text,
                      focusNode: focusNodes[additionalAddressKey],
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[latitudeKey])),
                  CustomTextFieldContainer(
                      hintTextKey: latitudeKey,
                      textEditingController: controllers[latitudeKey]!,
                      labelKey: latitudeKey,
                      textInputAction: TextInputAction.next,
                      keyboardType: TextInputType.number,
                      isSetValidator: true,
                      focusNode: focusNodes[latitudeKey],
                      onFieldSubmitted: (v) => FocusScope.of(context)
                          .requestFocus(focusNodes[longitudeKey])),
                  CustomTextFieldContainer(
                      hintTextKey: longitudeKey,
                      textEditingController: controllers[longitudeKey]!,
                      labelKey: longitudeKey,
                      textInputAction: TextInputAction.done,
                      keyboardType: TextInputType.number,
                      isSetValidator: true,
                      focusNode: focusNodes[longitudeKey],
                      onFieldSubmitted: (v) =>
                          focusNodes[longitudeKey]!.unfocus()),
                ],
              ),
            ),
          ),
        ),
        bottomNavigationBar:
            BlocConsumer<AddPickupLocationCubit, AddPickupLocationState>(
          listener: (context, state) {
            if (state is AddPickupLocationProgress) {
              Utils.showLoader(context);
            } else {
              Utils.hideLoader(context);
            }
            if (state is AddPickupLocationFailure) {
              Utils.showSnackBar(
                  message: state.errorMessage,
    
                  msgDuration: const Duration(seconds: 4));
            } else if (state is AddPickupLocationSuccess) {
              Utils.showSnackBar(message: state.successMsg);
              if (widget.callback != null) {
                widget.callback!(state.location, true);
              }
              Future.delayed(const Duration(seconds: 1), () {
                Navigator.of(context).pop();
              });
            }
          },
          builder: (context, state) {
            return CustomBottomButtonContainer(
              child: Row(
                children: [
                  Expanded(
                      child: CustomRoundedButton(
                          widthPercentage: 1,
                          buttonTitle: backKey,
                          showBorder: true,
                          backgroundColor: Theme.of(context)
                              .colorScheme
                              .onPrimary,
                          borderColor: Theme.of(context).hintColor,
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium!
                              .copyWith(
                                  color:
                                      Theme.of(context).colorScheme.secondary),
                          onTap: () => Utils.popNavigation(context))),
                  const SizedBox(
                    width: 16,
                  ),
                  Expanded(
                    child: CustomRoundedButton(
                      widthPercentage: 1,
                      buttonTitle: addLocationKey,
                      showBorder: false,
                      onTap: () {
                        if (_formKey.currentState!.validate()) {
                          addLocationProcess();
                        }
                      },
                    ),
                  ),
                ],
              ),
            );
          },
        ));
  }

  addLocationProcess() {
    if (isDemoApp) {
      Utils.showSnackBar(message: demoModeOnKey);
      return;
    }
    Map<String, String> params = {};
    apiField.forEach((key, value) {
      params[value] = controllers[key]!.text.trim();
    });
    context.read<AddPickupLocationCubit>().addLocation(context, params);
  }
}
