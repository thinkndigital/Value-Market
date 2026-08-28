import 'package:eshoppro_deliveryboy/core/constants/appConstants.dart';
import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/features/orders/models/parcel.dart';
import 'package:eshoppro_deliveryboy/utils/datetimeUtils.dart';
import 'package:eshoppro_deliveryboy/utils/extensions/currencyExtension.dart';
import 'package:flutter/material.dart';

import '../../../core/localization/labelKeys.dart';
import '../../../commons/widgets/customTextContainer.dart';

class OrderDetailsContainer extends StatelessWidget {
  final Parcel parcel;
  const OrderDetailsContainer({Key? key, required this.parcel})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: MediaQuery.of(context).size.width,
      padding: EdgeInsets.symmetric(vertical: appContentVerticalSpace),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.symmetric(
                horizontal: appContentHorizontalPadding),
            child: CustomTextContainer(
              textKey: orderDetailsKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          const Divider(
            thickness: 0.5,
          ),
          buildLabelAndText(
            context,
            orderDateTimeKey,
            DateTimeUtils.getFormattedDateTime(
              parcel.createdDate!,
            ),
          ),
          const SizedBox(height: 8),
          buildLabelAndText(
              context, orderIDKey, parcel.orderId.toString().withOrderSymbol()),
          const SizedBox(height: 8),
          buildLabelAndText(context, paymentMethodKey,
              paymentGatewayDisplayNames[parcel.paymentMethod!] ?? ''),
        ],
      ),
    );
  }

  buildLabelAndText(BuildContext context, String title, String value) {
    return Padding(
      padding:
          const EdgeInsets.symmetric(horizontal: appContentHorizontalPadding),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          CustomTextContainer(
            textKey: title,
            style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.8)),
          ),
          Text(value,
              style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.8)))
        ],
      ),
    );
  }
}
