import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customLabelContainer.dart';
import 'package:flutter/material.dart';
import '../../core/theme/colors.dart';
import 'customTextContainer.dart';

class CustomeDropdown extends StatelessWidget {
final String labelKey;
final String text;
final dynamic selectedValue;
final Function(dynamic) onChanged;
final Map<dynamic, String> mapItems;
final bool isFieldValueMandatory;
const CustomeDropdown(
{Key? key,
required this.labelKey,
required this.text,
required this.selectedValue,
required this.onChanged,
this.isFieldValueMandatory = true,
required this.mapItems})
: super(key: key);

@override
Widget build(BuildContext context) {
return Column(
crossAxisAlignment: CrossAxisAlignment.start,
mainAxisAlignment: MainAxisAlignment.center,
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
child: DropdownButton<dynamic>(
isExpanded: true,
borderRadius: BorderRadius.all(Radius.circular(borderRadius)),
underline: const SizedBox(),
dropdownColor: Colors.white,
icon: Icon(Icons.keyboard_arrow_down_sharp,
color: Theme.of(context).colorScheme.secondary),
value: selectedValue,
onChanged: onChanged,
items: mapItems.keys.map((dynamic key) {
return DropdownMenuItem<dynamic>(
value: key,
child: CustomTextContainer(
textKey: mapItems[key]!,
style: Theme.of(context).textTheme.bodyMedium,
),
);
}).toList(),
),
),
],
);
}
}
