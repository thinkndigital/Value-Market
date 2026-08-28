import 'package:eshop_plus/ui/profile/transaction/models/transaction.dart';
import 'package:eshop_plus/core/theme/colors.dart';
import 'package:eshop_plus/commons/widgets/customStatusContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';

import '../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../../commons/models/systemSettings.dart';

class TransactionInfoContainer extends StatelessWidget {
  final Transaction transaction;
  const TransactionInfoContainer({Key? key, required this.transaction})
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
                  textKey: transaction.transactionType == walletTransactionType
                      ? 'ID: #${transaction.id}'
                      : 'Order ID: #${transaction.orderId}',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium!
                      .copyWith(color: Theme.of(context).colorScheme.primary),
                ),
                if (transaction.status != '')
                  CustomStatusContainer(
                      getValueList: Utils.getTransactionStatusTextAndColor,
                      status: transaction.status!)
              ],
            ),
          ),
          const Divider(
            thickness: 0.5,
          ),
          buildLabelAndValue(context, dateKey,
              transaction.transactionDate.toString().split(' ')[0]),
          buildLabelAndValue(
              context,
              transaction.transactionType == walletTransactionType
                  ? typeKey
                  : paymentMethodKey,
              Utils.formatStringToTitleCase(transaction.type ?? '')),
          if (transaction.transactionType == walletTransactionType)
            buildLabelAndValue(context, messageKey, transaction.message ?? '')
          else
            buildLabelAndValue(context, 'Txn ID', transaction.txnId ?? ''),
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
                        '${getAmountSignAndColor(context)[0]} ${Utils.priceWithCurrencySymbol(price: transaction.amount ?? 0.0, context: context)}',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium!
                        .copyWith(color: getAmountSignAndColor(context)[1]))
              ],
            ),
          )
        ],
      ),
    );
  }

  getAmountSignAndColor(BuildContext context) {
    if (transaction.transactionType == defaultTransactionType ||
        (transaction.transactionType == walletTransactionType &&
            (transaction.type == debitType ||
                transaction.type == withdrawKey))) {
      return ['-', Theme.of(context).colorScheme.error];
    }
    return ['+', successStatusColor];
  }

  buildLabelAndValue(BuildContext context, String title, String value) {
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
          DesignConfig.smallHeightSizedBox
        ],
      ),
    );
  }
}
