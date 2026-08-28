import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
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
    return Padding(
      padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
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
          _buildSortByTile(topRatedProductKey),
          _buildSortByTile(newestFirstKey),
          _buildSortByTile(oldestFirstKey),
          _buildSortByTile(priceLowToHighKey),
          _buildSortByTile(priceHighToLowKey),
          const SizedBox(
            height: appContentHorizontalPadding,
          ),
        ],
      ),
    );
  }
}
