import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';


class CustomDropDownContainer<T> extends StatelessWidget {
  final String labelKey;
  final T? selectedValue;
  final List<T> values;

  final List<String> dropDownDisplayLabels;
  final Function(T?)? onChanged;
  final EdgeInsetsGeometry? margin;
  final TextStyle? labelStyle;
  final TextStyle? dropDownValueStyle;
  final bool isFieldValueMandatory;
  const CustomDropDownContainer(
      {super.key,
      required this.labelKey,
      required this.dropDownDisplayLabels,
      this.margin,
      required this.selectedValue,
      required this.onChanged,
      required this.values,
      this.dropDownValueStyle,
      this.isFieldValueMandatory = true,
      this.labelStyle})
      : assert(dropDownDisplayLabels.length == values.length,
            "Lenght of values and labels must be same");

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DropdownButton<T>(
            style: dropDownValueStyle,
            isDense: true,
            isExpanded: true,
            underline: const SizedBox(),
            dropdownColor: Colors.white,
            borderRadius: BorderRadius.circular(borderRadius),
            icon: Icon(
              Icons.arrow_drop_down,
              size: 20,
              color: Theme.of(context).colorScheme.secondary,
            ),
            items: [
              if (labelKey.isNotEmpty)
                DropdownMenuItem<T>(
                  value: null,
                  child: CustomTextContainer(
                    textKey: labelKey,
                    style: Theme.of(context).textTheme.bodySmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ...List.generate(dropDownDisplayLabels.length, (index) => index)
                  .map((index) {
                return DropdownMenuItem<T>(
                  value: values[index],
                  child: CustomTextContainer(
                    textKey: dropDownDisplayLabels[index],
                    style: Theme.of(context).textTheme.bodySmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                );
              }),
            ],
            onChanged: onChanged,
            value: selectedValue,
          ),
        ],
      ),
    );
  }
}
