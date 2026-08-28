import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/features/home/blocs/mostSellingCategory.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/features/home/widgets/pieChart.dart';
import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customDropDownContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class MostSellingCategorySection extends StatefulWidget {
  const MostSellingCategorySection({Key? key}) : super(key: key);

  @override
  _MostSellingCategorySectionState createState() =>
      _MostSellingCategorySectionState();
}

class _MostSellingCategorySectionState extends State<MostSellingCategorySection>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  String _selectedDuration = sellerOverviewStatusTypes.entries.first.key;
  List<int> totalSold = [];
  List<String> categoryNames = [];
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      context.read<MostSellingCategoryCubit>().getMostSellingCategory(params: {
        ApiURL.storeIdApiKey: context.read<StoresCubit>().getDefaultStore().id
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return BlocConsumer<MostSellingCategoryCubit, MostSellingCategoryState>(
        listener: (context, state) {
      if (state is MostSellingCategoryFetchSuccess) {
        totalSold = state.monthly.totalSold;
        categoryNames = state.monthly.categoryNames;
      }
    }, builder: (context, state) {
      if (state is MostSellingCategoryFetchSuccess) {
        return Column(
          children: [
            DesignConfig.smallHeightSizedBox,
            CustomDefaultContainer(
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      CustomTextContainer(
                        textKey: mostSellingCategoryKey,
                        style: Theme.of(context).textTheme.bodyLarge,
                      ),
                      SizedBox(
                        width: 120,
                        child: CustomDropDownContainer(
                            labelKey: '',
                            isDenceDropdown: true,
                            dropDownDisplayLabels:
                                sellerOverviewStatusTypes.values.toList(),
                            selectedValue: _selectedDuration,
                            onChanged: (value) {
                              setState(() {
                                _selectedDuration = value!;
                                if (_selectedDuration == monthlyKey) {
                                  totalSold = state.monthly.totalSold;
                                  categoryNames = state.monthly.categoryNames;
                                }
                                if (_selectedDuration == yearlyKey) {
                                  totalSold = state.yearly.totalSold;
                                  categoryNames = state.yearly.categoryNames;
                                }
                                if (_selectedDuration == weeklyKey) {
                                  totalSold = state.weekly.totalSold;
                                  categoryNames = state.weekly.categoryNames;
                                }
                                setState(() {});
                              });
                            },
                            values: sellerOverviewStatusTypes.keys.toList()),
                      ),
                    ],
                  ),
                  DesignConfig.defaultHeightSizedBox,
                  if (totalSold.isNotEmpty && categoryNames.isNotEmpty) ...[
                    SizedBox(
                      height: 200,
                      child: DonutChart(
                        totalSold: totalSold,
                        categoryNames: categoryNames,
                      ),
                    ),
                    const SizedBox(
                        height: 24), // Space between chart and labels
                    // Category labels below the chart
                    Wrap(
                      alignment: WrapAlignment.center,
                      spacing: 10, // Space between label items
                      children: List.generate(categoryNames.length, (i) {
                        return LegendItem(
                          color: DonutChart.getColorForIndex(i),
                          text: categoryNames[i],
                        );
                      }),
                    ),
                  ] else
                    const Center(
                      child: CustomTextContainer(textKey: dataNotAvailableKey),
                    )
                ],
              ),
            ),
          ],
        );
      }
      return const SizedBox();
    });
  }
}
