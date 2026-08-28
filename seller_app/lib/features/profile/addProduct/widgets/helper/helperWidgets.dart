import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';


import '../../../../../commons/widgets/customCuperinoSwitch.dart';
import '../../../../../commons/widgets/customTextContainer.dart';

class HelperWidgets {
  static taxWithSwitchWidget(
      BuildContext context, String lblKey, bool mainvalue,
      {Function? changeCallback,
      bool isHorizontal = true,
      TextStyle? textstyle}) {
    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      direction: isHorizontal ? Axis.horizontal : Axis.vertical,
      children: <Widget>[
        CustomTextContainer(
          textKey: lblKey,
          style: textstyle ?? Theme.of(context).textTheme.bodyMedium!,
        ),
        const SizedBox(width: 10),
        CustomCuperinoSwitch(
          value: mainvalue,
          onChanged: (value) {
            if (changeCallback != null) {
              changeCallback(value);
            }
          },
        )
      ],
    );
  }

  static Map<String, dynamic> generateVariationCombinations(
      Map<String, Map<String, dynamic>> selectedattribute,
      Map<String, dynamic> currvariations) {
    Map<String, dynamic> newResult = {};
    List<Map<String, String>> dynamicMaps = [];

    selectedattribute.forEach((key, value) {
   
      if (value["forVariation"]) {
        Map attributevalues = value["values"];
        if (attributevalues.isNotEmpty) {
         
          dynamicMaps.add(value["values"]);
        }
      }
    });
    if (dynamicMaps.isEmpty) {
      return newResult;
    }
    List<Map<String, String>> result = [{}];

    for (var map in dynamicMaps) {
      List<Map<String, String>> tempList = [];
      for (var combination in result) {
        map.forEach((key, value) {
          tempList.add({...combination, key: value});
        });
      }

      result = tempList;
    }

    for (var element in result) {
      List<int> targetParts = element.keys.toList().map(int.parse).toList()
        ..sort();
      String mkey = targetParts.join(" ");
  

      if (currvariations.containsKey(mkey)) {
        newResult[mkey] = currvariations[mkey];
      } else {
        newResult[mkey] = {
          "main": element,
          "isDeleted": false,
          priceKey: TextEditingController(),
          specialPriceKey: TextEditingController(),
          weightKey: TextEditingController(),
          heightKey: TextEditingController(),
          breadthKey: TextEditingController(),
          lengthKey: TextEditingController(),
          SKUKey: TextEditingController(),
          totalStockKey: TextEditingController(),
          stockStatusKey:
              TextEditingController(text: stockStatusTypes.keys.first),
          otherImagesKey: {},
        };
      }
    }
  
    return newResult;
  }
}
