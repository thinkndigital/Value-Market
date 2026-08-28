import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';

class SortProductBottomSheet extends StatefulWidget {
  final String selectedSortBy;
  final Function(String) onSortBySelected;
  const SortProductBottomSheet(
      {super.key,
      required this.selectedSortBy,
      required this.onSortBySelected});

  @override
  State<SortProductBottomSheet> createState() => _SortProductBottomSheetState();
}

class _SortProductBottomSheetState extends State<SortProductBottomSheet> {
  late String _selectedSortBy = widget.selectedSortBy;

  Widget _buildSortByTile(String textKey) {
    final isSelected = _selectedSortBy == textKey;
    return ListTile(
      dense: true,
      contentPadding: const EdgeInsetsDirectional.all(0),
      leading: Container(
        width: 20.0,
        height: 20.0,
        decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
                color: isSelected
                    ? Theme.of(context).colorScheme.primary
                    : Theme.of(context).colorScheme.secondary,
                width: 1.5)),
        padding: const EdgeInsetsDirectional.all(3.0),
        child: isSelected
            ? Container(
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.primary,
                  shape: BoxShape.circle,
                ),
              )
            : const SizedBox(),
      ),
      title: CustomTextContainer(
        textKey: textKey,
        style: Theme.of(context).textTheme.bodyLarge,
      ),
      onTap: () {
        if (isSelected) {
          return;
        }
        _selectedSortBy = textKey;
        setState(() {});
        widget.onSortBySelected.call(textKey);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
      width: MediaQuery.of(context).size.width,
      constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * (0.75)),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomTextContainer(
              textKey: sortByKey,
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(
            height: 10,
          ),
          _buildSortByTile(popularityKey),
          _buildSortByTile(priceLowToHighKey),
          _buildSortByTile(priceHighToLowKey),
          _buildSortByTile(discountKey),
          _buildSortByTile(newArrivalsKey),
          _buildSortByTile(topRatedProductKey),
        ],
      ),
    );
  }
}
