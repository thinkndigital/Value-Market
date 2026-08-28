import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/core/theme/colors.dart';
import 'package:eshopplus_seller/features/profile/salesReport/models/salesReport.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class SalesInfoContainer extends StatelessWidget {
  final SalesReport salesReport;
  const SalesInfoContainer({super.key, required this.salesReport});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsetsDirectional.symmetric(
          vertical: appContentHorizontalPadding),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: orderIDKey)}: #${salesReport.id}',
                  style: Theme.of(context).textTheme.titleMedium!,  
                ),
                if (salesReport.paymentMethod != null &&
                    salesReport.paymentMethod!.isNotEmpty)
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primary,
                        borderRadius: BorderRadius.circular(borderRadius)),
                    child: Text(
                      salesReport.paymentMethod ?? "",
                      style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                          color: Theme.of(context).colorScheme.onPrimary),
                    ),
                  )
              ],
            ),
          ),
          const Divider(),
          buildLabelAndValue(
              context, customerNameKey, salesReport.customerName ?? ""),
          buildLabelAndValue(
              context, orderDateKey, salesReport.orderDate ?? ""),
          buildLabelAndValue(
              context,
              totalKey,
              Utils.priceWithCurrencySymbol(
                  price: salesReport.total ?? 0, context: context)),
          buildLabelAndValue(
              context,
              discountKey,
              Utils.priceWithCurrencySymbol(
                  price: salesReport.discountedPrice ?? 0, context: context)),
          buildLabelAndValue(
              context,
              deliveryChargeKey,
              Utils.priceWithCurrencySymbol(
                  price: salesReport.deliveryCharge ?? 0, context: context)),
          const Divider(),
          Padding(
            padding: EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                CustomTextContainer(
                    textKey: totalAmountKey,
                    style: Theme.of(context).textTheme.titleMedium),
                Text(
                    Utils.priceWithCurrencySymbol(
                        price: salesReport.finalTotal ?? 0, context: context),
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium!
                        .copyWith(color: successStatusColor))
              ],
            ),
          )
        ],
      ),
    );
  }

  buildLabelAndValue(BuildContext context, String title, String value) {
    return Padding(
      padding: EdgeInsets.only(
          left: appContentHorizontalPadding,
          right: appContentHorizontalPadding,
          bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          CustomTextContainer(
            textKey: title,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          CustomTextContainer(
              textKey: value, style: Theme.of(context).textTheme.titleSmall),
        ],
      ),
    );
  }
}
