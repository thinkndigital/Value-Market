import 'package:value_market_delivery_boy/core/constants/appConstants.dart';
import 'package:value_market_delivery_boy/core/constants/themeConstants.dart';
import 'package:value_market_delivery_boy/commons/widgets/customDropDownContainer.dart';
import 'package:value_market_delivery_boy/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:flutter/material.dart';

import '../../../utils/designConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';

class OrderSearchBar extends StatefulWidget {
final Function? filterCallback;
final Map<String, String>? mainFilterValue;
const OrderSearchBar({Key? key, this.filterCallback, this.mainFilterValue})
: super(key: key);

@override
_OrderSearchBarState createState() => _OrderSearchBarState();
}

class _OrderSearchBarState extends State<OrderSearchBar> {
final searchController = TextEditingController();
Map<String, TextEditingController> controllers = {};
Map<String, FocusNode> focusNodes = {};
DateTime? fromDate, toDate;
String formattedDate = '';
Map apiFields = {
typeKey: "order_type",
statusKey: "active_status",
fromDateKey: "start_date",
toDateKey: "end_date",
searchKey: "search",
};
Map<String, String> filterValue = {};
@override
void initState() {
super.initState();
}

setFilterControllers() {
apiFields.forEach((key, value) {
controllers[key] = TextEditingController();
focusNodes[key] = FocusNode();
});

controllers[typeKey]!.text = orderFilterTypes.entries.first.key;
controllers[statusKey]!.text = orderStatusTypes.entries.first.key;
if (widget.mainFilterValue != null) {
widget.mainFilterValue!.forEach((key, value) {
String? controllerkey = apiFields.keys
.firstWhere((k) => apiFields[k] == key, orElse: () => null);
if (controllerkey != null) {
controllers[controllerkey]!.text =
reverseDateField(controllerkey, value);
}
});
}
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
return Container(
color: Theme.of(context).colorScheme.primaryContainer,
padding: EdgeInsets.all(appContentHorizontalPadding),
child: Row(
children: <Widget>[
Expanded(
flex: 4,
child: CustomTextFieldContainer(
hintTextKey: searchAllOrdersKey,
textEditingController: searchController,
labelKey: '',
textInputAction: TextInputAction.done,
prefixWidget: Icon(
Icons.search,
color: Theme.of(context).inputDecorationTheme.iconColor,
),
onChangeFun: (String? value) {
if (value != null && value.trim().isNotEmpty) {
filterValue[apiFields[searchKey]] = value;
} else if (filterValue.containsKey(apiFields[searchKey])) {
filterValue.remove(apiFields[searchKey]);
}
if (widget.filterCallback != null) {
widget.filterCallback!(filterValue);
}
},
),
),
Expanded(
child: GestureDetector(
onTap: () {
setFilterControllers();
Utils.openModalBottomSheet(context, buildOrderFilter(),
staticContent: true)
.then((value) {});
},
child: Container(
margin: const EdgeInsetsDirectional.only(start: 8),
padding: const EdgeInsets.all(12),
decoration: BoxDecoration(
borderRadius: BorderRadius.circular(8),
border: Border.all(
width: 1,
color: Theme.of(context).inputDecorationTheme.iconColor!),
),
child: Icon(Icons.filter_list,
size: 24,
color: Theme.of(context)
.colorScheme
.secondary
.withValues(alpha: 0.8)),
),
),
)
],
),
);
}

reverseDateField(String key, String value) {
if (key == fromDateKey || key == toDateKey) {
return value.split("-").reversed.join("-");
}
return value;
}

Widget buildOrderFilter() {
final typeNotifier = ValueNotifier<bool>(false);
final statusNotifier = ValueNotifier<bool>(false);
return FilterContainerForBottomSheet(
title: orderFilterKey,
borderedButtonTitle: clearFiltersKey,
primaryButtonTitle: applyKey,
borderedButtonOnTap: () {
filterValue.clear();
if (widget.filterCallback != null) {
widget.filterCallback!(filterValue, isClearFilter: true);
}
Navigator.of(context).pop();
},
primaryButtonOnTap: () {
filterValue.clear();
controllers.forEach((key, value) {
String fieldVal = value.text.trim();
if (fieldVal.isNotEmpty) {
filterValue[apiFields[key]] = reverseDateField(key, fieldVal);
}
});
if (widget.filterCallback != null && filterValue.isNotEmpty) {
widget.filterCallback!(filterValue);
}
Navigator.of(context).pop();
},
content: Column(
children: <Widget>[
ValueListenableBuilder(
valueListenable: typeNotifier,
builder: (context, value, _) {
return CustomDropDownContainer(
labelKey: typeKey,
dropDownDisplayLabels: orderFilterTypes.values.toList(),
selectedValue: controllers[typeKey]!.text,
isFieldValueMandatory: false,
onChanged: (value) {
setState(() {
controllers[typeKey]!.text = value.toString();
});
typeNotifier.value = !(typeNotifier.value);
},
values: orderFilterTypes.keys.toList());
}),
ValueListenableBuilder(
valueListenable: statusNotifier,
builder: (context, value, _) {
return CustomDropDownContainer(
labelKey: statusKey,
dropDownDisplayLabels: orderStatusTypes.values.toList(),
selectedValue: controllers[statusKey]!.text,
isFieldValueMandatory: false,
onChanged: (value) {
setState(() {
controllers[statusKey]!.text = value.toString();
});
statusNotifier.value = !(statusNotifier.value);
},
values: orderStatusTypes.keys.toList());
}),
Row(
children: <Widget>[
Expanded(
child: CustomTextFieldContainer(
hintTextKey: fromDateKey,
textEditingController: controllers[fromDateKey]!,
labelKey: fromDateKey,
readOnly: true,
suffixWidget: const Icon(Icons.date_range_outlined),
isFieldValueMandatory: false,
onTap: () async {
fromDate = await Utils.openDatePicker(
context, fromDate ?? DateTime.now());

if (fromDate != null) {
formattedDate = displayDateFormat.format(fromDate!);

setState(() {
controllers[fromDateKey]!.text =
formattedDate; //set output date to TextField value.
});
}
},
),
),
DesignConfig.defaultWidthSizedBox,
Expanded(
child: CustomTextFieldContainer(
hintTextKey: toDateKey,
textEditingController: controllers[toDateKey]!,
labelKey: toDateKey,
readOnly: true,
isFieldValueMandatory: false,
suffixWidget: const Icon(Icons.date_range_outlined),
onTap: () async {
toDate = await Utils.openDatePicker(
context, toDate ?? DateTime.now());

if (toDate != null) {
formattedDate = displayDateFormat.format(toDate!);

setState(() {
controllers[toDateKey]!.text =
formattedDate; //set output date to TextField value.
});
}
},
),
),
],
)
],
),
);
}
}
