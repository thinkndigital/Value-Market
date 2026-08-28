import 'package:value_market_seller/commons/models/product.dart';
import 'package:value_market_seller/commons/widgets/customDropDownContainer.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/routes/routes.dart';
import '../blocs/mediaListCubit.dart';
import '../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import '../../../../utils/designConfig.dart';
import '../../../../core/localization/labelKeys.dart';
import '../../../../utils/utils.dart';
import '../../../../commons/widgets/customCuperinoSwitch.dart';
import '../../../../commons/widgets/customLabelContainer.dart';
import '../../../../commons/widgets/customTextFieldContainer.dart';
import '../screens/addProductScreen.dart';
import 'helper/helperWidgets.dart';

class GeneralInfoTab extends StatefulWidget {
  Map<String, TextEditingController> controllers;
  Map<String, FocusNode> focusNodes;
  Function? refreshPage;

  Product? product;
  GeneralInfoTab(
      {Key? key,
      required this.controllers,
      required this.focusNodes,
      this.refreshPage,
      this.product})
      : super(key: key);

  @override
  State<GeneralInfoTab> createState() => _GeneralInfoTabState();
}

class _GeneralInfoTabState extends State<GeneralInfoTab> {
  @override
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(
        appContentHorizontalPadding,
      ),
      child: Column(
        children: <Widget>[
          if (widget.controllers[productTypeKey]!.text != digitalProductType &&
              !isAddEditComboProduct)
            CustomDropDownContainer(
                labelKey: typeOfProductKey,
                isReadOnly: widget.product != null,
                dropDownDisplayLabels: productTypes.values.toList(),
                selectedValue: widget.controllers[productTypeKey]!.text,
                onChanged: (value) {
                  if (widget.controllers[productTypeKey]!.text != value) {
                    widget.controllers[productTypeKey]!.text = value!;
                    if (widget.refreshPage != null) {
                      widget.refreshPage!();
                    }
                    setState(() {});
                  }
                },
                values: productTypes.keys.toList()),
          if (widget.controllers[productTypeKey]!.text == simpleProductType)
            simpleProductTypeWidget(),
          if (widget.controllers[productTypeKey]!.text == digitalProductType)
            digitalProductTypeWidget(),
          if (widget.controllers[productTypeKey]!.text != digitalProductType)
            stockMngWidget(),
          SizedBox(
            height: appContentVerticalSpace,
          ),
        ],
      ),
    );
  }

  stockMngWidget() {
    if (widget.controllers[stockStatusKey]!.text.trim().isEmpty) {
      widget.controllers[stockStatusKey]!.text = stockStatusTypes.keys.first;
    }
    return Column(children: [
      Row(
        children: <Widget>[
          const CustomLabelContainer(
            textKey: enableStockManagementKey,
          ),
          DesignConfig.defaultWidthSizedBox,
          CustomCuperinoSwitch(
              value: widget.controllers[enableStockManagementKey]!.text == "1"
                  ? true
                  : false,
              onChanged: (value) {
                if (!value) {
                  widget.controllers[SKUKey]!.text = "";
                  widget.controllers[totalStockKey]!.text = "";
                }
                setState(() {
                  widget.controllers[enableStockManagementKey]!.text =
                      value == true ? "1" : "0";
                });
              })
        ],
      ),
      if (widget.controllers[enableStockManagementKey]!.text == "1") ...[
        if (widget.controllers[productTypeKey]!.text == variableProductType)
          CustomDropDownContainer(
              labelKey: chooseStockMgmtTypeKey,
              dropDownDisplayLabels: stockMgmtTypes.values.toList(),
              selectedValue: widget.controllers[chooseStockMgmtTypeKey]!.text,
              onChanged: (value) {
                setState(() {
                  widget.controllers[chooseStockMgmtTypeKey]!.text = value!;
                });
              },
              values: stockMgmtTypes.keys.toList()),
        if (widget.controllers[productTypeKey]!.text == simpleProductType ||
            widget.controllers[chooseStockMgmtTypeKey]!.text ==
                productLevelStockMagmtType) ...[
          Row(
            children: <Widget>[
              Expanded(
                child: CustomTextFieldContainer(
                    hintTextKey: SKUKey,
                    textEditingController: widget.controllers[SKUKey]!,
                    labelKey: SKUKey,
                    textInputAction: TextInputAction.next,
                    focusNode: widget.focusNodes[SKUKey],
                    isSetValidator: true,
                    errmsg: SKUKey,
                    onFieldSubmitted: (v) => FocusScope.of(context)
                        .requestFocus(widget.focusNodes[totalStockKey])),
              ),
              const SizedBox(
                width: appContentHorizontalPadding,
              ),
              Expanded(
                child: CustomTextFieldContainer(
                    hintTextKey: totalStockKey,
                    textEditingController: widget.controllers[totalStockKey]!,
                    labelKey: totalStockKey,
                    keyboardType: TextInputType.number,
                    textInputAction: TextInputAction.next,
                    focusNode: widget.focusNodes[totalStockKey],
                    isSetValidator: true,
                    errmsg: totalStockKey,
                    onFieldSubmitted: (v) => FocusScope.of(context).unfocus()),
              ),
            ],
          ),
          CustomDropDownContainer(
              labelKey: stockStatusKey,
              dropDownDisplayLabels: stockStatusTypes.values.toList(),
              selectedValue: widget.controllers[stockStatusKey]!.text,
              onChanged: (value) {
                setState(() {
                  widget.controllers[stockStatusKey]!.text = value.toString();
                });
              },
              values: stockStatusTypes.keys.toList()),
        ]
      ]
    ]);
  }

  priceWidget() {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CustomTextFieldContainer(
          hintTextKey: priceKey,
          textEditingController: widget.controllers[priceKey]!,
          labelKey: priceKey,
          textInputAction: TextInputAction.next,
          focusNode: widget.focusNodes[priceKey],
          errmsg: enterPriceKey,
          keyboardType: TextInputType.number,
          isSetValidator: true,
          onFieldSubmitted: (v) => FocusScope.of(context)
              .requestFocus(widget.focusNodes[specialPriceKey])),
      CustomTextFieldContainer(
          hintTextKey: specialPriceKey,
          textEditingController: widget.controllers[specialPriceKey]!,
          labelKey: specialPriceKey,
          textInputAction: TextInputAction.next,
          focusNode: widget.focusNodes[specialPriceKey],
          keyboardType: TextInputType.number,
          isSetValidator: true,
          validator: (String val) {
            String mainprice = widget.controllers[priceKey]!.text;
            if (mainprice.isEmpty) {
              return context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: enterPriceKey);
            } else if (val.trim().isEmpty) {
              return context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: enterSpecialPriceKey);
            } else {
              double price = double.parse(widget.controllers[priceKey]!.text);
              double spprice =
                  double.parse(widget.controllers[specialPriceKey]!.text);
              if (spprice >= price) {
                return context
                    .read<SettingsAndLanguagesCubit>()
                    .getTranslatedValue(labelKey: specialPriceErrMsgKey);
              } else {
                return null;
              }
            }
          },
          onFieldSubmitted: (v) =>
              FocusScope.of(context).requestFocus(widget.focusNodes[weightKey]))
    ]);
  }

  digitalProductTypeWidget() {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      priceWidget(),
      const SizedBox(
        height: 5,
      ),
      HelperWidgets.taxWithSwitchWidget(context, isDownloadAllowedKey,
          widget.controllers[isDownloadAllowedKey]!.text == "1",
          changeCallback: (bool value) {
        widget.controllers[isDownloadAllowedKey]!.text = value ? "1" : "0";
        setState(() {});
      }),
      if (widget.controllers[isDownloadAllowedKey]!.text == "1") ...[
        CustomDropDownContainer(
            labelKey: downloadLinkTypeKey,
            isFieldValueMandatory: false,
            dropDownDisplayLabels: downloadLinkTypes.values.toList(),
            selectedValue: widget.controllers[downloadLinkTypeKey]!.text,
            onChanged: (value) {
              setState(() {
                widget.controllers[downloadLinkTypeKey]!.text = value!;
              });
            },
            values: downloadLinkTypes.keys.toList()),
        if (widget.controllers[downloadLinkTypeKey]!.text == addLinkType)
          CustomTextFieldContainer(
            hintTextKey: digitalProductLinkKey,
            textEditingController: widget.controllers[digitalProductLinkKey]!,
            labelKey: digitalProductLinkKey,
            textInputAction: TextInputAction.next,
            focusNode: widget.focusNodes[digitalProductLinkKey],
            keyboardType: TextInputType.url,
            isSetValidator: true,
          ),
        if (widget.controllers[downloadLinkTypeKey]!.text == selfHostedKey)
          Material(
            color: Colors.grey[300],
            child: ListTile(
                title: Text(widget.controllers[selectDownloadableMediaKey]!.text
                    .split("/")
                    .last),
                titleTextStyle: Theme.of(context).textTheme.bodySmall,
                dense: true,
                contentPadding: const EdgeInsetsDirectional.only(
                    start: 8, top: 2, bottom: 2, end: 8),
                trailing: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(5.0),
                      ),
                    ),
                    child: Text(
                      context
                          .read<SettingsAndLanguagesCubit>()
                          .getTranslatedValue(
                              labelKey: widget
                                      .controllers[selectDownloadableMediaKey]!
                                      .text
                                      .trim()
                                      .isEmpty
                                  ? selectVideoKey
                                  : deleteKey),
                      style: Theme.of(context).textTheme.titleSmall!.copyWith(
                          color: Theme.of(context).colorScheme.primary),
                    ),
                    onPressed: () {
                      if (widget.controllers[selectDownloadableMediaKey]!.text
                          .trim()
                          .isEmpty) {
                        selectVideo();
                      } else {
                        widget.controllers[selectDownloadableMediaKey]!.text =
                            "";
                      }
                      setState(() {});
                    })),
          )
      ]
    ]);
  }

  selectVideo() {
    if (videoMediaCubit!.state is! MediaListFetchSuccess) {
      videoMediaCubit!
          .getMediaList(context, {"type": mediaTypeVideo}, isSetInitial: true);
    }
    Utils.navigateToScreen(context, Routes.mediaListScreen, arguments: {
      'mediaListCubit': videoMediaCubit,
      'mediaType': mediaTypeVideo,
      'isMultipleSelect': false,
      "onMediaSelect": (Map<String, String> path) {
        if (path.isNotEmpty) {
          widget.controllers[selectDownloadableMediaKey]!.text =
              path.keys.first;

          setState(() {});
        }
      }
    });
  }

  simpleProductTypeWidget() {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      priceWidget(),
      if (widget.controllers[productTypeKey]!.text == simpleProductType)
        Row(
          children: <Widget>[
            Expanded(
              child: CustomTextFieldContainer(
                  hintTextKey: weightKey,
                  textEditingController: widget.controllers[weightKey]!,
                  labelKey: weightKey,
                  textInputAction: TextInputAction.next,
                  focusNode: widget.focusNodes[weightKey],
                  isFieldValueMandatory: false,
                  keyboardType: TextInputType.number,
                  onFieldSubmitted: (v) => FocusScope.of(context)
                      .requestFocus(widget.focusNodes[heightKey])),
            ),
            const SizedBox(
              width: appContentHorizontalPadding,
            ),
            Expanded(
              child: CustomTextFieldContainer(
                  hintTextKey: heightKey,
                  textEditingController: widget.controllers[heightKey]!,
                  labelKey: heightKey,
                  textInputAction: TextInputAction.next,
                  focusNode: widget.focusNodes[heightKey],
                  isFieldValueMandatory: false,
                  keyboardType: TextInputType.number,
                  onFieldSubmitted: (v) => FocusScope.of(context)
                      .requestFocus(widget.focusNodes[breadthKey])),
            ),
          ],
        ),
      if (widget.controllers[productTypeKey]!.text == simpleProductType)
        Row(
          children: <Widget>[
            Expanded(
              child: CustomTextFieldContainer(
                  hintTextKey: breadthKey,
                  textEditingController: widget.controllers[breadthKey]!,
                  labelKey: breadthKey,
                  textInputAction: TextInputAction.next,
                  keyboardType: TextInputType.number,
                  focusNode: widget.focusNodes[breadthKey],
                  isFieldValueMandatory: false,
                  onFieldSubmitted: (v) => FocusScope.of(context)
                      .requestFocus(widget.focusNodes[lengthKey])),
            ),
            const SizedBox(
              width: appContentHorizontalPadding,
            ),
            Expanded(
              child: CustomTextFieldContainer(
                  hintTextKey: lengthKey,
                  textEditingController: widget.controllers[lengthKey]!,
                  labelKey: lengthKey,
                  textInputAction: TextInputAction.next,
                  keyboardType: TextInputType.number,
                  focusNode: widget.focusNodes[lengthKey],
                  isFieldValueMandatory: false,
                  onFieldSubmitted: (v) => FocusScope.of(context).unfocus()),
            ),
          ],
        ),
    ]);
  }
}
