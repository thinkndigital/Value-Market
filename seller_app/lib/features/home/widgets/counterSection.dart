import 'package:eshopplus_seller/commons/widgets/circleButton.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import 'package:eshopplus_seller/features/home/blocs/getTotalDataCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';

import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class CounterSection extends StatefulWidget {
  const CounterSection({Key? key}) : super(key: key);

  @override
  _CounterSectionState createState() => _CounterSectionState();
}

class _CounterSectionState extends State<CounterSection>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      context.read<GetTotalDataCubit>().getTotalData(params: {
        ApiURL.storeIdApiKey: context.read<StoresCubit>().getDefaultStore().id
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return BlocBuilder<GetTotalDataCubit, GetTotalDataState>(
      builder: (context, state) {
        if (state is GetTotalDataFetchSuccess) {
          return SizedBox(
            height: 120,
            child: ListView(
              shrinkWrap: true,
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.only(
                  left: appContentHorizontalPadding,
                  top: appContentHorizontalPadding,
                  bottom: appContentHorizontalPadding),
              children: [
                buildOrderStatusContainer(
                    totalSalesKey,
                    Utils.priceWithCurrencySymbol(
                        price: double.parse(state.totalSales.toString()),
                        context: context),
                    Icons.point_of_sale,
                    receivedStatusColor),
                buildOrderStatusContainer(
                    totalCommissionKey,
                    Utils.priceWithCurrencySymbol(
                        price: double.parse(
                            state.totalCommissionAmount.toString()),
                        context: context),
                    Icons.currency_exchange_outlined,
                    deliveredStatusColor),
                buildOrderStatusContainer(totalOrdersKey, state.totalOrders,
                    Icons.shopping_bag_outlined, deliveredStatusColor),
                buildOrderStatusContainer(totalProductsKey, state.totalProducts,
                    Icons.inventory_2_outlined, shippedStatusColor),
                buildOrderStatusContainer(lowStockKey, state.lowStockProducts,
                    Icons.trending_down, returnedStatusColor),
                buildOrderStatusContainer(
                    totalBalanceKey,
                    Utils.priceWithCurrencySymbol(
                        price: double.parse(state.totalBalance.toString()),
                        context: context),
                    Icons.account_balance_wallet_outlined,
                    processedStatusColor),
              ],
            ),
          );
        }
        return const SizedBox.shrink();
      },
    );
  }

  Container buildOrderStatusContainer(
      String status, dynamic value, IconData icon, Color color) {
    return Container(
      height: 90,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
      margin: const EdgeInsets.only(right: appContentHorizontalPadding),
      decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8),
          color: Theme.of(context).colorScheme.primaryContainer),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          CircleButton(
            onTap: () {},
            heightAndWidth: 34,
            backgroundColor: color.withValues(alpha: 0.12),
            child: Icon(
              icon,
              color: color,
              size: 24,
            ),
          ),
          const SizedBox(
            width: 12,
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              CustomTextContainer(
                textKey: status,
                style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                    color: Theme.of(context)
                        .colorScheme
                        .secondary
                        .withValues(alpha: 0.8)),
              ),
              const SizedBox(
                height: 8,
              ),
              Text(
                value,
                style: Theme.of(context)
                    .textTheme
                    .titleLarge!
                    .copyWith(color: Theme.of(context).colorScheme.secondary),
              )
            ],
          )
        ],
      ),
    );
  }
}
