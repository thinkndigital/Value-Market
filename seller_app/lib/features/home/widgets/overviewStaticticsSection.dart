import 'package:value_market_seller/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/features/home/blocs/overviewDataCubit.dart';
import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/features/home/widgets/barChart.dart';
import 'package:value_market_seller/features/home/widgets/pieChart.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';

import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class OverviewStaticticsSection extends StatefulWidget {
  const OverviewStaticticsSection({Key? key}) : super(key: key);

  @override
  _OverviewStaticticsSectionState createState() =>
      _OverviewStaticticsSectionState();
}

class _OverviewStaticticsSectionState extends State<OverviewStaticticsSection>
    with SingleTickerProviderStateMixin, AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    Future.delayed(Duration.zero, () {
      context.read<OverviewDataCubit>().getOverviewData(params: {
        ApiURL.storeIdApiKey: context.read<StoresCubit>().getDefaultStore().id
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return BlocBuilder<OverviewDataCubit, OverviewDataState>(
        builder: (context, state) {
      if (state is OverviewDataSuccess) {
        return CustomDefaultContainer(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: <Widget>[
                  CustomTextContainer(
                    textKey: overviewStatisticKey,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  SizedBox(
                    height: 30,
                    width: MediaQuery.of(context).size.width / 2,
                    child: TabBar(
                        controller: _tabController,
                        dividerColor: Colors.transparent,
                        indicator: BoxDecoration(
                          borderRadius: BorderRadius.circular(
                              borderRadius), // Creates border
                          color: Theme.of(context).colorScheme.primary,
                        ),
                        indicatorSize: TabBarIndicatorSize.tab,
                        unselectedLabelColor: Theme.of(context)
                            .colorScheme
                            .secondary,
                        labelColor: Theme.of(context).colorScheme.onPrimary,
                        padding: EdgeInsets.zero,
                        labelPadding: EdgeInsets.zero,
                        labelStyle: Theme.of(context)
                            .textTheme
                            .bodySmall!
                            .copyWith(
                                color: Theme.of(context)
                                    .colorScheme
                                    .primaryContainer),
                        tabs: const [
                          Tab(child: CustomTextContainer(textKey: todayKey)),
                          Tab(child: CustomTextContainer(textKey: weeklyKey)),
                          Tab(child: CustomTextContainer(textKey: monthlyKey)),
                        ]),
                  )
                ],
              ),
              SizedBox(
                height: 350,
                child: TabBarView(
                  controller: _tabController,
                  children: [
                    BarChartSample(
                      totalSales: state.dailyStatistics.sales,
                      totalOrders: state.dailyStatistics.orders,
                      totalRevenue: state.dailyStatistics.revenue,
                      monthNames: state.dailyStatistics.names,
                    ),
                    BarChartSample(
                      totalSales: state.weeklyStatistics.sales,
                      totalOrders: state.weeklyStatistics.orders,
                      totalRevenue: state.weeklyStatistics.revenue,
                      monthNames: state.weeklyStatistics.names,
                    ),
                    BarChartSample(
                      totalSales: state.monthlyStatistics.sales,
                      totalOrders: state.monthlyStatistics.orders,
                      totalRevenue: state.monthlyStatistics.revenue,
                      monthNames: state.monthlyStatistics.names,
                    ),
                  ],
                ),
              ),
              Wrap(
                  alignment: WrapAlignment.center,
                  spacing: 10, // Space between label items
                  children: [
                    LegendItem(
                        color: Colors.blue,
                        text: context
                            .read<SettingsAndLanguagesCubit>()
                            .getTranslatedValue(labelKey: salesKey)),
                    LegendItem(
                        color: Colors.green,
                        text: context
                            .read<SettingsAndLanguagesCubit>()
                            .getTranslatedValue(labelKey: ordersKey)),
                    LegendItem(
                        color: Colors.orange,
                        text: context
                            .read<SettingsAndLanguagesCubit>()
                            .getTranslatedValue(labelKey: revenueKey)),
                  ]),
            ],
          ),
        );
      }
      return SizedBox();
    });
  }
}
