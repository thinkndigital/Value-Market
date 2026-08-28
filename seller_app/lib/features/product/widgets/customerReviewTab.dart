import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';

import '../../../commons/widgets/customBottomButtonContainer.dart';
import '../../../commons/widgets/customRoundedButton.dart';

class CustomerReviewTab extends StatefulWidget {
  const CustomerReviewTab({Key? key}) : super(key: key);

  @override
  _CustomerReviewTabState createState() => _CustomerReviewTabState();
}

class _CustomerReviewTabState extends State<CustomerReviewTab> {
  int _selectedIndex = 0;
  List<Widget> ratingList = [];

  @override
  Widget build(BuildContext context) {
    return CustomDefaultContainer(
      child: Column(
        children: <Widget>[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              CustomTextContainer(
                textKey: customerReviewsKey,
                style: Theme.of(context).textTheme.titleMedium,
              ),
              GestureDetector(
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primaryContainer,
                      borderRadius: BorderRadius.circular(borderRadius),
                      border: Border.all(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.8))),
                  child: Row(
                    children: <Widget>[
                      CustomTextContainer(
                        textKey: allReviewsKey,
                        style: Theme.of(context).textTheme.bodySmall!.copyWith(
                            color: Theme.of(context)
                                .colorScheme
                                .secondary
                                .withValues(alpha: 0.8)),
                      ),
                      Icon(
                        Icons.keyboard_arrow_down,
                        color: Theme.of(context)
                            .colorScheme
                            .secondary
                            .withValues(alpha: 0.8),
                      )
                    ],
                  ),
                ),
              ),
            ],
          )
        ],
      ),
    );
  }

  Widget buildSortingList() {
    return StatefulBuilder(
        builder: (BuildContext buildcontext, StateSetter setState) {
      return SingleChildScrollView(
        child: Column(
          children: [
            Padding(
              padding: EdgeInsetsDirectional.symmetric(
                  horizontal: appContentVerticalSpace,
                  vertical: appContentVerticalSpace),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  CustomTextContainer(
                    textKey: sortByKey,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  ...List.generate(
                    ratingList.length,
                    (index) =>
                        buildRadioListTile(index, ratingList[index], setState),
                  )
                ],
              ),
            ),
            buildFilterButtons(),
          ],
        ),
      );
    });
  }

  buildRadioListTile(int index, Widget child, StateSetter setState) {
    return RadioListTile(
      contentPadding: EdgeInsets.zero,
      visualDensity: const VisualDensity(horizontal: -4, vertical: -2),
      title: child,

      value: index, // Assign a value of 1 to this option
      groupValue:
          _selectedIndex, // Use _selectedValue to track the selected option
      onChanged: (value) {
        setState(() {
          _selectedIndex =
              value as int; // Update _selectedValue when option 1 is selected
        });
      },
    );
  }

  CustomBottomButtonContainer buildFilterButtons() {
    return CustomBottomButtonContainer(
      child: Row(
        children: [
          Expanded(
            child: CustomRoundedButton(
                widthPercentage: 0.4,
                buttonTitle: clearFiltersKey,
                showBorder: true,
                backgroundColor: Theme.of(context).colorScheme.onPrimary,
                borderColor: Theme.of(context).hintColor,
                onTap: () => Navigator.of(context).pop()),
          ),
          const SizedBox(
            width: 16,
          ),
          Expanded(
            child: CustomRoundedButton(
              widthPercentage: 0.4,
              buttonTitle: applyKey,
              showBorder: false,
              onTap: () {},
            ),
          )
        ],
      ),
    );
  }
}
