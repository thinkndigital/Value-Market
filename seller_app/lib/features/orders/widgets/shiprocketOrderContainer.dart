import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/widgets/customDropDownContainer.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';

import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../utils/utils.dart';

class ShiprocketOrderContainer extends StatefulWidget {
  const ShiprocketOrderContainer({Key? key}) : super(key: key);

  @override
  _ShiprocketOrderContainerState createState() =>
      _ShiprocketOrderContainerState();
}

class _ShiprocketOrderContainerState extends State<ShiprocketOrderContainer> {
  Map<String, TextEditingController> controllers = {};

  Map<String, FocusNode> focusNodes = {};
  final List formFields = [
    pickupLocationKey,
    weightKey,
    heightKey,
    breadthKey,
    lengthKey
  ];
  Map<String, String> pickupLocations = {'0': 'loc1', '1': 'loc2'};
  @override
  void initState() {
    super.initState();
    formFields.forEach((key) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    });
    controllers[pickupLocationKey]!.text = pickupLocations.entries.first.key;
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
    return FilterContainerForBottomSheet(
      title: createShiprocketOrderParcelKey,
      borderedButtonTitle: backKey,
      primaryButtonTitle: createOrderKey,
      borderedButtonOnTap: () => Utils.popNavigation(context),
      primaryButtonOnTap: () {},
      content: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(borderRadius),
                color: const Color(0xFFDC3545)),
            child: Text.rich(
              TextSpan(
                children: [
                  TextSpan(
                      text:
                          '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: noteKey)} : ',
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium!
                          .copyWith(color: Colors.white)),
                  TextSpan(
                      text: context
                          .read<SettingsAndLanguagesCubit>()
                          .getTranslatedValue(
                              labelKey: shiprocketOrderLocationNoteKey),
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium!
                          .copyWith(color: Colors.white)),
                ],
              ),
            ),
          ),
          DesignConfig.defaultHeightSizedBox,
          CustomDropDownContainer(
              labelKey: pickupLocationKey,
              dropDownDisplayLabels: pickupLocations.values.toList(),
              selectedValue: controllers[pickupLocationKey]!.text,
              isFieldValueMandatory: false,
              onChanged: (value) {
                setState(() {
                  controllers[pickupLocationKey]!.text = value.toString();
                });
              },
              values: pickupLocations.keys.toList()),
          DesignConfig.defaultHeightSizedBox,
          CustomTextContainer(
            textKey: totalWeightOfBoxKey,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          Row(
            children: <Widget>[
              Expanded(
                child: CustomTextFieldContainer(
                    hintTextKey: weightKey,
                    textEditingController: controllers[weightKey]!,
                    labelKey: weightKey,
                    textInputAction: TextInputAction.next,
                    focusNode: focusNodes[weightKey],
                    onFieldSubmitted: (v) => FocusScope.of(context)
                        .requestFocus(focusNodes[heightKey])),
              ),
              DesignConfig.defaultWidthSizedBox,
              Expanded(
                child: CustomTextFieldContainer(
                    hintTextKey: heightKey,
                    textEditingController: controllers[heightKey]!,
                    labelKey: heightKey,
                    textInputAction: TextInputAction.next,
                    focusNode: focusNodes[heightKey],
                    onFieldSubmitted: (v) => FocusScope.of(context)
                        .requestFocus(focusNodes[breadthKey])),
              ),
            ],
          ),
          Row(
            children: <Widget>[
              Expanded(
                child: CustomTextFieldContainer(
                    hintTextKey: breadthKey,
                    textEditingController: controllers[breadthKey]!,
                    labelKey: breadthKey,
                    textInputAction: TextInputAction.next,
                    focusNode: focusNodes[breadthKey],
                    onFieldSubmitted: (v) => FocusScope.of(context)
                        .requestFocus(focusNodes[lengthKey])),
              ),
              DesignConfig.defaultWidthSizedBox,
              Expanded(
                child: CustomTextFieldContainer(
                    hintTextKey: lengthKey,
                    textEditingController: controllers[lengthKey]!,
                    labelKey: lengthKey,
                    textInputAction: TextInputAction.next,
                    focusNode: focusNodes[lengthKey],
                    onFieldSubmitted: (v) => FocusScope.of(context)
                        .requestFocus(focusNodes[selectZipCodeKey])),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
