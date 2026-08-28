import 'package:eshopplus_seller/commons/widgets/customLabelContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import 'package:flutter/material.dart';

class CustomDropDownContainer<T> extends StatelessWidget {
  final String labelKey;
  final T selectedValue;
  final List<T> values;
  final bool isDenceDropdown;
  final List<String> dropDownDisplayLabels;
  final Function(T?)? onChanged;
  final EdgeInsetsGeometry? margin;
  final TextStyle? labelStyle;
  final TextStyle? dropDownValueStyle;
  final bool isFieldValueMandatory;
  final Widget? dropDownWidget;
  final bool isReadOnly;
  const CustomDropDownContainer(
      {super.key,
      this.dropDownWidget,
      required this.labelKey,
      required this.dropDownDisplayLabels,
      this.margin,
      required this.selectedValue,
      required this.onChanged,
      required this.values,
      this.dropDownValueStyle,
      this.isFieldValueMandatory = true,
      this.isDenceDropdown = false,
      this.labelStyle,
      this.isReadOnly = false})
      : assert(dropDownDisplayLabels.length == values.length,
            "Lenght of values and labels must be same");

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: margin ?? const EdgeInsets.symmetric(vertical: 7.5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          labelKey.isEmpty
              ? const SizedBox()
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CustomLabelContainer(
                      textKey: labelKey,
                      isFieldValueMandatory: isFieldValueMandatory,
                    ),
                    const SizedBox(
                      height: 10,
                    ),
                  ],
                ),
          Container(
            width: MediaQuery.of(context).size.width,
            decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(borderRadius),
                border: Border.all(color: borderColor.withValues(alpha: 0.4))),
            padding: const EdgeInsetsDirectional.symmetric(horizontal: 15),
            child: IgnorePointer(
              ignoring: isReadOnly,
              child: dropDownWidget ??
                  DropdownButton<T>(
                    isDense: isDenceDropdown,
                    style: dropDownValueStyle,
                    isExpanded: true,
                    dropdownColor: Colors.white,
                    borderRadius: BorderRadius.circular(8),
                    underline: const SizedBox(),
                    icon: Icon(Icons.keyboard_arrow_down_sharp,
                        color: Theme.of(context).colorScheme.secondary),
                    items: List.generate(
                            dropDownDisplayLabels.length, (index) => index)
                        .map((index) {
                      return DropdownMenuItem<T>(
                          value: values[index],
                          child: CustomTextContainer(
                            textKey: dropDownDisplayLabels[index],
                            style: Theme.of(context).textTheme.bodyMedium,
                          ));
                    }).toList(),
                    onChanged: onChanged,
                    value: selectedValue,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}
