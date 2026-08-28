import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/commons/product/models/attribute.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/product/models/productVariant.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class VariantContainer extends StatefulWidget {
  final Product product;
  final Function onVariantSelected;
  const VariantContainer(
      {Key? key, required this.product, required this.onVariantSelected})
      : super(key: key);

  @override
  _VariantContainerState createState() => _VariantContainerState();
}

class _VariantContainerState extends State<VariantContainer> {
  Map<String, String> selectedVariants = {};
  Set<String> outOfStockCombinations = {}; // Track disabled options
  @override
  void initState() {
    super.initState();
    if (widget.product.selectedVariant != null) {
      if (widget.product.attributes!.isNotEmpty &&
          widget.product.selectedVariant!.attributeValueIds!.isNotEmpty) {
        List<String> idsList =
            widget.product.selectedVariant!.attributeValueIds!.split(',');

        for (int i = 0; i < idsList.length; i++) {
          Attribute attribute = widget.product.attributes!.firstWhere(
              (e) => e.ids!.split(',').toList().contains(idsList[i]));
          selectedVariants[attribute.attrName!] = idsList[i];
        }
      }
    }

    if (widget.product.stockType == '2' || widget.product.stockType == '1') {
      for (var variant in widget.product.variants!) {
        if (variant.availability == "1" &&
            variant.stock != null &&
            variant.stock!.isNotEmpty &&
            int.parse(variant.stock!) > 0) {
          continue;
        } else {
          outOfStockCombinations.add(variant.attributeValueIds!);
        }
      }
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    if (selectedVariants.isEmpty) {
      return Container(
        padding: EdgeInsets.all(appContentHorizontalPadding),
        color: Theme.of(context).colorScheme.primaryContainer,
        margin: EdgeInsetsDirectional.only(bottom: 8),
        child: Center(
          child: Text('No Variants Found'),
        ),
      );
    }
    return ListView(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: widget.product.attributes!.map((attribute) {
        if (widget.product.variants![0].swatcheType == '1') {
          return ColorPalette(
            colors: widget.product.variants!
                .map((variant) => variant.swatcheValue!)
                .toList(),
            values: widget.product.variants!
                .map((variant) => variant.attributeValueIds!)
                .toList(),
            onColorSelected: (id) {
              setState(() {
                selectedVariants[attribute.attrName!] = id;
                widget.product.selectedVariant = widget.product.variants!
                    .firstWhere((element) =>
                        element.attributeValueIds ==
                        selectedVariants.values.join(','));
                widget.onVariantSelected();
              });
            },
            selectedColor: selectedVariants[attribute.attrName] ?? '',
          );
        }
        return AttributeSelection(
          attributeName: attribute.attrName!,
          options: widget.product.variants![0].swatcheType == '2'
              ? widget.product.variants!
                  .map((variant) => variant.swatcheValue!)
                  .toList()
              : attribute.value!.split(','),
          values: attribute.ids!.split(','),
          selectedOption: selectedVariants[attribute.attrName!],
          outOfStockCombinations: outOfStockCombinations,
          selectedVariants: selectedVariants,
          onSelection: (selectedOption) {
            setState(() {
              selectedVariants[attribute.attrName!] = selectedOption;
            });
            ProductVariant? variant =
                widget.product.variants!.firstWhereOrNull((element) {
              final variantSet = element.attributeValueIds!.split(',').toSet();
              final selectedSet = selectedVariants.values.toSet();
              return variantSet.containsAll(selectedSet) &&
                  selectedSet.containsAll(variantSet);
            });
            if (variant != null) {
              widget.product.selectedVariant = variant;
              widget.onVariantSelected();
            } else {
              Utils.showSnackBar(
                  message: 'No variant found for this attributes',
                  context: context);
              selectedVariants.remove(attribute.attrName!);
            }
          },
        );
      }).toList(),
    );
  }
}

class AttributeSelection extends StatelessWidget {
  final String attributeName;

  final List<String> options;
  final List<String> values;
  final String? selectedOption;
  final Set<String> outOfStockCombinations;
  final Function(String) onSelection;
  final Map<String, String> selectedVariants;

  AttributeSelection({
    required this.attributeName,
    required this.options,
    required this.values,
    required this.selectedOption,
    required this.outOfStockCombinations,
    required this.onSelection,
    required this.selectedVariants,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CustomDefaultContainer(
            child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            CustomTextContainer(
                textKey: 'Select $attributeName',
                style: Theme.of(context).textTheme.titleMedium),
            DesignConfig.defaultHeightSizedBox,
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: List.generate(options.length, (index) {
                  bool isDisabled = _isOptionDisabled(values[index]);

                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: attributeName == 'image'
                          ? CustomImageWidget(
                              url: options[index],
                              height: 50,
                              width: 40,
                            )
                          : Text(options[index]),
                      selected: selectedVariants.containsValue(values[index]),
                      selectedColor: Theme.of(context).colorScheme.primary,
                      showCheckmark: false,
                      labelStyle: selectedVariants.containsValue(values[index])
                          ? Theme.of(context).textTheme.bodyMedium!.copyWith(
                              color: Theme.of(context).colorScheme.onPrimary)
                          : Theme.of(context).textTheme.bodyMedium,
                      onSelected: (selected) {
                        onSelection(values[index]);
                      },
                      backgroundColor: isDisabled ? Colors.grey : null,
                    ),
                  );
                }),
              ),
            ),
          ],
        )),
        DesignConfig.smallHeightSizedBox
      ],
    );
  }

  bool _isOptionDisabled(String optionId) {
    // Generate the attribute combination for the current selection + the new option
    Map<String, String> tempSelection = Map.from(selectedVariants);
    tempSelection[attributeName] = optionId;

    // Check if this combination is out of stock
    return outOfStockCombinations.contains(tempSelection.values.join(','));
  }
}

class ColorPalette extends StatelessWidget {
  final List<String> colors;
  final List<String> values;
  final Function(String) onColorSelected;
  final String? selectedColor;

  ColorPalette({
    required this.colors,
    required this.values,
    required this.onColorSelected,
    required this.selectedColor,
  });

  @override
  Widget build(BuildContext context) {
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomTextContainer(
              textKey: 'Select Color',
              style: Theme.of(context).textTheme.titleMedium),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: List.generate(colors.length, (index) {
                return GestureDetector(
                  onTap: () => onColorSelected(values[index]),
                  child: Container(
                    margin: EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Utils.hexToColor(colors[index]),
                      border: Border.all(
                        color: selectedColor != null &&
                                selectedColor == values[index]
                            ? Theme.of(context).inputDecorationTheme.iconColor!
                            : Colors.transparent,
                        width: 3,
                      ),
                    ),
                    width: 30,
                    height: 30,
                  ),
                );
              }).toList(),
            ),
          ),
        ],
      ),
    );
  }
}
