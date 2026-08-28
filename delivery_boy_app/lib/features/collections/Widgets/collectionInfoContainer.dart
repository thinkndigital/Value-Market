import 'package:eshoppro_deliveryboy/core/constants/appConstants.dart';
import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/features/orders/models/cashCollection.dart';
import 'package:eshoppro_deliveryboy/commons/models/systemSettings.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customStatusContainer.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../utils/designConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import '../../../core/theme/colors.dart';
import '../../../commons/widgets/customTextContainer.dart';

class CollectionInfoContainer extends StatelessWidget {
  final CashCollection transaction;
  const CollectionInfoContainer({Key? key, required this.transaction})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    final CurrencySetting currencySetting = context
            .read<SettingsAndLanguagesCubit>()
            .getSettings()
            .systemSettings
            ?.currencySetting ??
        CurrencySetting();
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(
          vertical: appContentHorizontalPadding),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                CustomTextContainer(
                  textKey: 'ID: #${transaction.id}',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium!
                      .copyWith(color: Theme.of(context).colorScheme.primary),
                ),
                CustomStatusContainer(
                    getValueList: Utils.getCollectionStatusTextAndColor,
                    status: transaction.type ?? "")
              ],
            ),
          ),
          const Divider(
            thickness: 0.5,
          ),
          buildLabelAndValue(context, dateKey,
              transaction.transactionDate.toString().split(' ')[0]),
/*   buildLabelAndValue(context, typeKey,
Utils.formatStringToTitleCase(transaction.type ?? '')), */
          if ((transaction.message ?? '').trim().isNotEmpty)
            buildLabelAndValue(context, messageKey, transaction.message ?? '',
                isSetBottomPadding: false),
          const Divider(thickness: 0.5),
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text.rich(TextSpan(children: [
                  TextSpan(
                      text: currencySetting.symbol ?? "\$",
                      style: Theme.of(context).textTheme.titleMedium),
                  const TextSpan(text: " "),
                  TextSpan(
                      text: context
                          .read<SettingsAndLanguagesCubit>()
                          .getTranslatedValue(labelKey: amountKey),
                      style: Theme.of(context).textTheme.titleMedium),
                ])),
                CustomTextContainer(
                    textKey:
                        '${getAmountSignAndColor(context, transaction.type!)[0]} ${transaction.amount ?? "0"}',
                    style: Theme.of(context).textTheme.titleMedium!.copyWith(
                        color: getAmountSignAndColor(
                            context, transaction.type!)[1]))
              ],
            ),
          )
        ],
      ),
    );
  }

  getAmountSignAndColor(BuildContext context, String type) {
    if (type.trim().toLowerCase() == receivedStatusType) {
      return ['+', successStatusColor];
    }
    return ['-', Theme.of(context).colorScheme.error];
  }

  buildLabelAndValue(BuildContext context, String title, String value,
      {bool isSetBottomPadding = true}) {
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(
          horizontal: appContentHorizontalPadding),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomTextContainer(
            textKey: title,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          CustomTextContainer(
            textKey: value,
            style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.8)),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          if (isSetBottomPadding) DesignConfig.smallHeightSizedBox
        ],
      ),
    );
  }
}
