import 'package:value_market_seller/commons/models/mainAttribute.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../../core/theme/colors.dart';
import '../blocs/attributeListCubit.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import '../../../../../utils/designConfig.dart';
import '../../../../core/localization/labelKeys.dart';
import 'helper/attributeSelectionDialog.dart';

class AttributeTab extends StatefulWidget {
  final Map<String, TextEditingController> controllers;
  final Map<String, FocusNode> focusNodes;
  final Map<String, Map<String, dynamic>> selectedAttributes;

  const AttributeTab({
    super.key,
    required this.controllers,
    required this.focusNodes,
    required this.selectedAttributes,
  });

  @override
  AttributeTabState createState() => AttributeTabState();
}

class AttributeTabState extends State<AttributeTab> {
  late BuildContext dialogContext;
  List<String> selectedids = [];
  @override
  void initState() {
    super.initState();
    if (widget.controllers[attributesKey]!.text.trim().isNotEmpty) {
      selectedids.addAll(widget.controllers[attributesKey]!.text.split(","));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
            padding: const EdgeInsets.all(
              appContentHorizontalPadding,
            ),
            child: addAttributeWidget()),
        Expanded(
          child: ListView(
              padding: const EdgeInsetsDirectional.only(
                start: appContentHorizontalPadding,
                end: appContentHorizontalPadding,
                bottom: appContentHorizontalPadding,
              ),
              children: [
                noteWidget(),
                SizedBox(
                  height: appContentVerticalSpace,
                ),
                attributeSelectionWidget()
              ]),
        ),
      ],
    );
  }

  noteWidget() {
    return RichText(
        text:
            TextSpan(style: Theme.of(context).textTheme.bodyMedium, children: [
      TextSpan(
        text: context
            .read<SettingsAndLanguagesCubit>()
            .getTranslatedValue(labelKey: noteKey),
        style: Theme.of(context).textTheme.titleMedium,
      ),
      TextSpan(
        text: ":\t",
        style: Theme.of(context).textTheme.titleMedium,
      ),
      TextSpan(
          text: context
              .read<SettingsAndLanguagesCubit>()
              .getTranslatedValue(labelKey: attributeCheckboxNoteKey))
    ]));
  }

  addAttributeWidget() {
    return Row(children: [
      const CustomTextContainer(textKey: attributesKey),
      const Spacer(),
      outlineButtonWidget(addAttributeKey, () {
        addNewSelectedValue("");
        setState(() {});
      }),
    ]);
  }

  outlineButtonWidget(String lbl, Function callback,
      {Color? textcolor, Size? minsize, bool istranslated = false}) {
    return OutlinedButton(
        onPressed: () {
          callback();
        },
        style: OutlinedButton.styleFrom(
          minimumSize: minsize,
          foregroundColor: textcolor,
          textStyle: Theme.of(context).textTheme.bodyMedium,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(0)),
        ),
        child: CustomTextContainer(
          textKey: lbl,
          isTranslated: istranslated,
        ));
  }

  attributeSelectionWidget() {
    return Wrap(
      children: List.generate(
        widget.selectedAttributes.length,
        (index) {
          String key = widget.selectedAttributes.keys.elementAt(index);
          Map<String, dynamic> value = widget.selectedAttributes[key] ?? {};
          return Container(
            decoration: DesignConfig.boxDecoration(
                Theme.of(context).scaffoldBackgroundColor, 5),
            padding: const EdgeInsetsDirectional.only(
                start: 10, bottom: 10, end: 10),
            child: Column(
              children: [
                Row(
                  children: <Widget>[
                    const CustomTextContainer(textKey: selectAttributeKey),
                    const Spacer(),
                    Checkbox(
                      value: value["forVariation"],
                      onChanged: (value) {
                        widget.selectedAttributes[key]!["forVariation"] = value;
                        setState(() {});
                      },
                    ),
                    IconButton(
                        onPressed: () {
                          deleteSpecificAttributeValues(key);
                          widget.selectedAttributes.remove(key);

                          setState(() {});
                        },
                        icon: const Icon(
                          Icons.delete_outline,
                          color: Colors.red,
                        ))
                  ],
                ),
                outlineButtonWidget(
                    widget.selectedAttributes[key]!["main"]["name"] ??
                        selectAttributesKey, () {
                  openAttributeSelection(key, value, true);
                },
                    istranslated:
                        widget.selectedAttributes[key]!["main"]["name"] != null,
                    textcolor: Theme.of(context).textTheme.bodyMedium!.color!,
                    minsize: const Size.fromHeight(40)),
                outlineButtonWidget(addAttributeValueKey, () {
                  openAttributeSelection(key, value, false);
                },
                    textcolor: Theme.of(context).textTheme.bodyMedium!.color!,
                    minsize: const Size.fromHeight(40)),
                attributeValueListWidget(key, value)
              ],
            ),
          );
        },
      ),
    );
  }

  openAttributeSelection(String attributeid, Map<String, dynamic> attributeinfo,
      bool isMainAttributeSelection) {
    MainAttribute? mainAttribute;
    if (!isMainAttributeSelection) {
      if ((widget.selectedAttributes[attributeid]!["main"]).isEmpty) {
        openAttributeSelection(attributeid, attributeinfo, true);
        return;
      }
      Map<String, dynamic> maindata =
          widget.selectedAttributes[attributeid]!["main"];
      if (maindata.isNotEmpty) {
        mainAttribute = MainAttribute.fromJson(maindata);
      }
    }
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext mcontext) {
        dialogContext = mcontext;
        return AlertDialog(
          insetPadding: const EdgeInsets.all(appContentHorizontalPadding),
          backgroundColor: white,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
          shape: DesignConfig.setRoundedBorder(white, 10, false),
          content: AttributeSelectionDialog(
              isMainAttributeSelection: isMainAttributeSelection,
              mainAttribute: mainAttribute,
              attributeListCubit: context.read<AttributeListCubit>(),
              selectedId:
                  isMainAttributeSelection ? [attributeid] : selectedids,
              onAttributeSelect:
                  (MainAttribute mattribute, Map<String, String> mvalue,
                      {String? removedVid}) {
                if (isMainAttributeSelection) {
                  if (mattribute.id!.toString() != attributeid) {
                    deleteSpecificAttributeValues(attributeid);
                    widget.selectedAttributes.remove(attributeid);
                  }
                  if (!widget.selectedAttributes
                      .containsKey(mattribute.id!.toString())) {
                    addNewSelectedValue(mattribute.id!.toString());
                  }
                  widget.selectedAttributes[mattribute.id!.toString()]![
                      "main"] = mattribute.toMap();
                } else {
                  widget.selectedAttributes[attributeid]!["values"] = mvalue;
                  setSelectedIds(
                      removedVid == null ? mvalue.keys.toList() : [removedVid],
                      isAdd: removedVid == null);
                }

                setState(() {});
                Navigator.pop(dialogContext);
              }),
        );
      },
    );
  }

  setSelectedIds(List<String> keys, {bool isAdd = true}) {
    isAdd
        ? selectedids.addAll(keys)
        : selectedids.removeWhere((element) => keys.contains(element));
    if (selectedids.isNotEmpty) selectedids = selectedids.toSet().toList();
    widget.controllers[attributesKey]!.text = selectedids.join(",");
  }

  addNewSelectedValue(String id) {
    widget.selectedAttributes[id] = {
      "forVariation": false,
      "main": {},
      "values": {}
    };
  }

  attributeValueListWidget(String key, Map<String, dynamic> value) {
    Map attributevalues = value["values"];

    if (attributevalues.isEmpty) {
      return const SizedBox.shrink();
    }
    List keys = attributevalues.keys.toList();
    return Wrap(
        crossAxisAlignment: WrapCrossAlignment.start,
        alignment: WrapAlignment.start,
        runAlignment: WrapAlignment.start,
        spacing: 5,
        children: List.generate(attributevalues.length, (index) {
          return Chip(
            elevation: 10,
            label: Text(
              attributevalues[keys[index]],
            ),
            backgroundColor: Theme.of(context).colorScheme.onPrimary,
            deleteIconColor: primaryColor,
            deleteIcon: const Icon(
              Icons.cancel_outlined,
              size: 20,
            ),
            onDeleted: () {
              attributevalues.remove(keys[index]);
              widget.selectedAttributes[key]!["values"] = attributevalues;
              setSelectedIds([keys[index]], isAdd: false);
              setState(() {});
            },
          );
        }));
  }

  deleteSpecificAttributeValues(String attributeid) {
    if (widget.selectedAttributes[attributeid]!["values"].isNotEmpty) {
      setSelectedIds(
          widget.selectedAttributes[attributeid]!["values"].keys.toList(),
          isAdd: false);
    }
  }
}
