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
import '../../../../commons/widgets/customTextContainer.dart';
import '../../../../commons/widgets/customTextFieldContainer.dart';
import '../screens/addProductScreen.dart';
import 'helper/helperWidgets.dart';

class VariationTab extends StatefulWidget {
  final Map<String, TextEditingController> controllers;
  final Map<String, Map<String, dynamic>> selectedAttributes;
  final Product? product;

  VariationTab({
    super.key,
    required this.controllers,
    required this.selectedAttributes,
    this.product,
  });

  @override
  VariationTabState createState() => VariationTabState();
}

class VariationTabState extends State<VariationTab> {
  @override
  void initState() {
    super.initState();
    setMapList();
  }

  setMapList() {
    Map<String, dynamic> currvariations = variations;
    variations = {};

    variations = HelperWidgets.generateVariationCombinations(
        widget.selectedAttributes, currvariations);
  }

  @override
  Widget build(BuildContext context) {
    return attributeSelectionWidget();
  }

  attributeSelectionWidget() {
    List<String> combinationKeys = variations.keys.toList();
    return ListView(
      children: List.generate(
        variations.length,
        (index) {
          String key = combinationKeys[index];
          Map<String, dynamic> mapitem = variations[key];

          return Container(
            decoration: DesignConfig.boxDecoration(
                Theme.of(context).scaffoldBackgroundColor, 5),
            padding: const EdgeInsetsDirectional.only(
                start: 10, bottom: 10, end: 10),
            child: ExpansionTile(
              tilePadding: const EdgeInsetsDirectional.symmetric(horizontal: 8),
              childrenPadding:
                  const EdgeInsetsDirectional.symmetric(horizontal: 8),
              backgroundColor: variations[key]["isDeleted"]
                  ? Colors.red.shade100
                  : Theme.of(context).colorScheme.primaryContainer,
              collapsedBackgroundColor: variations[key]["isDeleted"]
                  ? Colors.red.shade100
                  : Theme.of(context).colorScheme.primaryContainer,
              collapsedShape: const ContinuousRectangleBorder(
                  borderRadius: BorderRadius.all(Radius.circular(20))),
              shape: const ContinuousRectangleBorder(
                  borderRadius: BorderRadius.all(Radius.circular(20))),
              title: Row(
                children: [
                  Expanded(
                    child: CustomTextContainer(
                      textKey: mapitem["main"].values.join(" ,"),
                      style: Theme.of(context).textTheme.titleMedium,
                      isTranslated: true,
                    ),
                  ),
                  IconButton(
                      onPressed: () {
                        bool isDeleted = variations[key]["isDeleted"] ?? false;
                        variations[key]["isDeleted"] = !isDeleted;
                        setState(() {});
                      },
                      icon: Icon(
                        variations[key]["isDeleted"]
                            ? Icons.restore
                            : Icons.delete_outline,
                        color: variations[key]["isDeleted"]
                            ? Colors.blue
                            : Colors.red,
                      ))
                ],
              ),
              children: [
                basicInfoWidget(key, mapitem),
                if (widget.controllers[chooseStockMgmtTypeKey]!.text ==
                    variableLevelStockMgmtType)
                  stockInfoWidget(key, mapitem),
                Utils.buildImageUploadWidget(
                    context: context,
                    labelKey: otherImagesKey,
                    file: null,
                    onTapUpload: () {
                      openImageMediaSelection(key, mapitem);
                    },
                    onTapClose: () {}),
                selectedOtherImageListWidgets(key, mapitem),
              ],
            ),
          );
        },
      ),
    );
  }

  selectedOtherImageListWidgets(String mainkey, Map<String, dynamic> mapitem) {
    Map<String, String> imglist =
        Map<String, String>.from(variations[mainkey][otherImagesKey] ?? {});
    Size size = MediaQuery.of(context).size;
    return Center(
      child: Wrap(
          direction: Axis.horizontal,
          spacing: 12,
          runSpacing: 12,
          children: List.generate(
            imglist.length,
            (index) {
              String key = imglist.keys.elementAt(index);
              String value = imglist[key] ?? "";
              return Stack(
                clipBehavior: Clip.none,
                children: [
                  Container(
                    width: size.width * 0.25,
                    height: size.width * 0.25,
                    decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(8),
                        image: DecorationImage(
                          image: NetworkImage(value),
                          fit: BoxFit.cover,
                        )),
                  ),
                  Positioned(
                    right: 5,
                    top: 5,
                    child: GestureDetector(
                      onTap: () {
                        imglist.remove(key);
                        variations[mainkey][otherImagesKey] = imglist;
                        setState(() {});
                      },
                      child: Container(
                        height: 34,
                        width: 34,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                            color: Theme.of(context).scaffoldBackgroundColor,
                            shape: BoxShape.circle),
                        child: Icon(
                          Icons.close_rounded,
                          size: 18,
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
          )),
    );
  }

  openImageMediaSelection(String key, Map<String, dynamic> mapitem) {
    if (imageMediaCubit!.state is! MediaListFetchSuccess) {
      imageMediaCubit!
          .getMediaList(context, {"type": mediaTypeImage}, isSetInitial: true);
    }
    Utils.navigateToScreen(context, Routes.mediaListScreen, arguments: {
      'mediaListCubit': imageMediaCubit,
      'mediaType': mediaTypeImage,
      'isMultipleSelect': true,
      "onMediaSelect": (Map<String, String> path) {
        if (path.isNotEmpty) {
          Map<String, String> imglist =
              Map<String, String>.from(variations[key][otherImagesKey] ?? {});
          imglist.addAll(path);
          variations[key][otherImagesKey] = imglist;

          setState(() {});
        }
      }
    });
  }

  basicInfoWidget(String key, Map<String, dynamic> mapitem) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      priceWidget(key, mapitem),
      Row(
        children: <Widget>[
          Expanded(
            child: CustomTextFieldContainer(
              hintTextKey: weightKey,
              textEditingController: mapitem[weightKey]!,
              labelKey: weightKey,
              textInputAction: TextInputAction.next,
              isFieldValueMandatory: false,
              keyboardType: TextInputType.number,
            ),
          ),
          const SizedBox(
            width: appContentHorizontalPadding,
          ),
          Expanded(
            child: CustomTextFieldContainer(
              hintTextKey: heightKey,
              textEditingController: mapitem[heightKey]!,
              labelKey: heightKey,
              textInputAction: TextInputAction.next,
              isFieldValueMandatory: false,
              keyboardType: TextInputType.number,
            ),
          ),
        ],
      ),
      Row(
        children: <Widget>[
          Expanded(
            child: CustomTextFieldContainer(
              hintTextKey: breadthKey,
              textEditingController: mapitem[breadthKey]!,
              labelKey: breadthKey,
              textInputAction: TextInputAction.next,
              keyboardType: TextInputType.number,
              isFieldValueMandatory: false,
            ),
          ),
          const SizedBox(
            width: appContentHorizontalPadding,
          ),
          Expanded(
            child: CustomTextFieldContainer(
              hintTextKey: lengthKey,
              textEditingController: mapitem[lengthKey]!,
              labelKey: lengthKey,
              textInputAction: TextInputAction.next,
              keyboardType: TextInputType.number,
              isFieldValueMandatory: false,
            ),
          ),
        ],
      ),
    ]);
  }

  stockInfoWidget(String key, Map<String, dynamic> mapitem) {
    return Column(children: [
      Row(
        children: <Widget>[
          Expanded(
            child: CustomTextFieldContainer(
              hintTextKey: SKUKey,
              textEditingController: variations[key][SKUKey]!,
              labelKey: SKUKey,
              textInputAction: TextInputAction.next,
              isSetValidator: true,
              errmsg: SKUKey,
            ),
          ),
          const SizedBox(
            width: appContentHorizontalPadding,
          ),
          Expanded(
            child: CustomTextFieldContainer(
              hintTextKey: totalStockKey,
              textEditingController: variations[key][totalStockKey]!,
              labelKey: totalStockKey,
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.next,
              isSetValidator: true,
              errmsg: totalStockKey,
            ),
          ),
        ],
      ),
      CustomDropDownContainer(
          labelKey: stockStatusKey,
          dropDownDisplayLabels: stockStatusTypes.values.toList(),
          selectedValue: variations[key][stockStatusKey]!.text,
          onChanged: (value) {
            setState(() {
              variations[key][stockStatusKey]!.text = value.toString();
            });
          },
          values: stockStatusTypes.keys.toList()),
    ]);
  }

  priceWidget(String key, Map<String, dynamic> mapitem) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      CustomTextFieldContainer(
        hintTextKey: priceKey,
        textEditingController: mapitem[priceKey]!,
        labelKey: priceKey,
        textInputAction: TextInputAction.next,
        errmsg: enterPriceKey,
        keyboardType: TextInputType.number,
        isSetValidator: true,
      ),
      CustomTextFieldContainer(
        hintTextKey: specialPriceKey,
        textEditingController: mapitem[specialPriceKey]!,
        labelKey: specialPriceKey,
        textInputAction: TextInputAction.next,
        keyboardType: TextInputType.number,
        isSetValidator: true,
        validator: (String val) {
          String mainprice = mapitem[priceKey]!.text;
          if (variations[key]['isDeleted']) {
            return null;
          }
          if (mainprice.isEmpty) {
            return context
                .read<SettingsAndLanguagesCubit>()
                .getTranslatedValue(labelKey: enterPriceKey);
          } else if (val.trim().isEmpty) {
            return context
                .read<SettingsAndLanguagesCubit>()
                .getTranslatedValue(labelKey: enterSpecialPriceKey);
          } else {
            double price = double.parse(mapitem[priceKey]!.text);
            double spprice = double.parse(mapitem[specialPriceKey]!.text);
            if (spprice >= price) {
              return context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: specialPriceErrMsgKey);
            } else {
              return null;
            }
          }
        },
      )
    ]);
  }
}
