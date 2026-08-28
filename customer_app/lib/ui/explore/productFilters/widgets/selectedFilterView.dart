import 'package:value_market_customer/ui/explore/productFilters/models/selectedFilterAttribute.dart';
import 'package:value_market_customer/ui/explore/productFilters/widgets/filterAttributesTile.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';

class SelectedFilterView extends StatelessWidget {
  final Function(String, int, [bool]) onTapFilterAttribute;
  final bool Function(String, int) isFilterAttributeSelected;
  final SelectedFilterAttribute selectedFilterAttribute;

  const SelectedFilterView({
    super.key,
    required this.onTapFilterAttribute,
    required this.isFilterAttributeSelected,
    required this.selectedFilterAttribute,
  });

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
        child: selectedFilterAttribute.attributeName == ratingsKey
            ? Column(
                children: List.generate(
                    Utils.getFilterRatings(context: context).length,
                    (index) => FilterAttributeTile(
                          id: index,
                          isSelected: isFilterAttributeSelected(
                              selectedFilterAttribute.attributeName, index),
                          title:
                              Utils.getFilterRatings(context: context)[index],
                          onTap: (id) => onTapFilterAttribute(
                              selectedFilterAttribute.attributeName, id, true),
                        )),
              )
            : (selectedFilterAttribute.attributeName == discountKey)
                ? Column(
                    children: List.generate(
                        Utils.getFilterDiscounts(context: context).length,
                        (index) => FilterAttributeTile(
                              id: index,
                              isSelected: isFilterAttributeSelected(
                                  selectedFilterAttribute.attributeName, index),
                              title: Utils.getFilterDiscounts(
                                  context: context)[index],
                              onTap: (id) => onTapFilterAttribute(
                                  selectedFilterAttribute.attributeName,
                                  id,
                                  true),
                            )),
                  )
                : Column(
                    children: List.generate(
                      (selectedFilterAttribute.attributeValues ?? []).length,
                      (index) {
                        final id = int.parse(
                            selectedFilterAttribute.attributeValuesId![index]);
                        return FilterAttributeTile(
                          id: id,
                          isSelected: isFilterAttributeSelected(
                              selectedFilterAttribute.attributeName, id),
                          title:
                              selectedFilterAttribute.attributeValues![index],
                          onTap: (id) => onTapFilterAttribute(
                              selectedFilterAttribute.attributeName,
                              id,
                              selectedFilterAttribute.isSingleSelection ??
                                  false),
                        );
                      },
                    ),
                  ));
  }
}
