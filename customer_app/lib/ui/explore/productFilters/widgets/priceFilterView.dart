import 'package:eshop_plus/commons/product/models/productMinMaxPrice.dart';
import 'package:eshop_plus/ui/explore/productFilters/widgets/filterAttributesTile.dart';
import 'package:eshop_plus/core/theme/colors.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';

import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class PriceFilterView extends StatelessWidget {
  final Function(int) onTapPriceRange;
  final bool Function(int) isPriceRangeSelected;
  final TextEditingController minPriceController;
  final TextEditingController maxPriceController;
  final List<ProductMinMaxPrice> productMinMaxFilterPrices;
  final Function(double? minPrice, double? maxPrice) onChangedMinMaxPrice;
  const PriceFilterView({
    super.key,
    required this.minPriceController,
    required this.maxPriceController,
    required this.onTapPriceRange,
    required this.isPriceRangeSelected,
    required this.productMinMaxFilterPrices,
    required this.onChangedMinMaxPrice,
  });

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ...List.generate(productMinMaxFilterPrices.length, (index) {
            final ProductMinMaxPrice productMinMaxPrice =
                productMinMaxFilterPrices[index];
            String title = '';

            if (productMinMaxPrice.minPrice == -1) {
              title =
                  'Below - ${Utils.priceWithCurrencySymbol(price: productMinMaxPrice.maxPrice ?? 0.0, context: context)}';
            } else if (productMinMaxPrice.maxPrice == -1) {
              title =
                  '${Utils.priceWithCurrencySymbol(price: productMinMaxPrice.minPrice ?? 0.0, context: context)} - Above';
            } else {
              title =
                  '${Utils.priceWithCurrencySymbol(price: productMinMaxPrice.minPrice ?? 0.0, context: context)} - ${Utils.priceWithCurrencySymbol(price: productMinMaxPrice.maxPrice ?? 0.0, context: context)}';
            }
            return FilterAttributeTile(
              title: title,
              id: index,
              isSelected: isPriceRangeSelected.call(index),
              onTap: (id) {
                onTapPriceRange(id);
              },
            );
          }),
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(vertical: 10.0),
            child: CustomTextContainer(
              textKey: priceRangeKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(horizontal: 10.0),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CustomTextContainer(
                        textKey: minKey,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(
                        height: 5.0,
                      ),
                      Container(
                        height: 35,
                        decoration: BoxDecoration(
                          color: Theme.of(context).scaffoldBackgroundColor,
                          borderRadius: BorderRadius.circular(10.0),
                        ),
                        child: TextField(
                          controller: minPriceController,
                          keyboardType: TextInputType.number,
                          style: Theme.of(context).textTheme.bodyMedium,
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                                RegExp(r'^\d+\.?\d{0,2}')),
                          ],
                          onChanged: (value) => onChangedMinMaxPrice(
                              double.tryParse(value),
                              double.tryParse(maxPriceController.text.trim())),
                          decoration: const InputDecoration(
                              border: InputBorder.none,
                              contentPadding: EdgeInsetsDirectional.only(
                                  start: 10.0, end: 10.0, bottom: 5.0)),
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  width: 10.0,
                  margin: const EdgeInsetsDirectional.only(
                      top: 25, start: 5, end: 5, bottom: 2.5),
                  color: borderColor.withValues(alpha: 0.4),
                  height: 1.0,
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CustomTextContainer(
                        textKey: maxKey,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(
                        height: 5.0,
                      ),
                      Container(
                        height: 35,
                        decoration: BoxDecoration(
                          color: Theme.of(context).scaffoldBackgroundColor,
                          borderRadius: BorderRadius.circular(10.0),
                        ),
                        child: TextField(
                          controller: maxPriceController,
                          keyboardType: TextInputType.number,
                          style: Theme.of(context).textTheme.bodyMedium,
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(
                                RegExp(r'^\d+\.?\d{0,2}')),
                          ],
                          onChanged: (value) => onChangedMinMaxPrice(
                              double.tryParse(minPriceController.text.trim()),
                              double.tryParse(value)),
                          decoration: const InputDecoration(
                              border: InputBorder.none,
                              contentPadding: EdgeInsetsDirectional.only(
                                  start: 10.0, end: 10.0, bottom: 5.0)),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          )
        ],
      ),
    );
  }
}
