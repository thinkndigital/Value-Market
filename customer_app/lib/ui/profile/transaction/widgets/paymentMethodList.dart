import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/theme/colors.dart';
import 'package:eshop_plus/ui/cart/cubits/getUserCart.dart';
import 'package:eshop_plus/ui/profile/transaction/blocs/getPaymentMethodCubit.dart';
import 'package:eshop_plus/ui/profile/transaction/models/paymentMethod.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class PaymentMethodList extends StatefulWidget {
  final List<PaymentModel> paymentMethods;
  final PaymentMethodCubit paymentMethodCubit;
  const PaymentMethodList(
      {super.key,
      required this.paymentMethods,
      required this.paymentMethodCubit});

  @override
  _PaymentMethodListState createState() => _PaymentMethodListState();
}

class _PaymentMethodListState extends State<PaymentMethodList> {
  PaymentModel? _selectedPaymentMethod;
  @override
  void initState() {
    super.initState();
    _selectedPaymentMethod = widget.paymentMethods
        .firstWhereOrNull((element) => element.isSelected == true);
  }

  @override
  Widget build(BuildContext context) {
    _selectedPaymentMethod = widget.paymentMethods
        .firstWhereOrNull((element) => element.isSelected == true);

    return CustomDefaultContainer(
        borderRadius: 8,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            CustomTextContainer(
              textKey: paymentMethodsKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            DesignConfig.defaultHeightSizedBox,
            ListView.separated(
              separatorBuilder: (context, index) =>
                  DesignConfig.smallHeightSizedBox,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: widget.paymentMethods.length,
              itemBuilder: (context, index) {
                final paymentMethod = widget.paymentMethods[index];
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    buildPaymentMethodItem(
                      paymentMethod: paymentMethod,
                    ),
                  ],
                );
              },
            ),
            if (_selectedPaymentMethod != null &&
                _selectedPaymentMethod!.name == bankTransferKey &&
                _selectedPaymentMethod!.isSelected == true)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: warningColor,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: CustomTextContainer(
                        textKey: bankTransferWarningKey,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: infoColor,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          CustomTextContainer(
                              textKey: accountDetailsKey,
                              style: Theme.of(context)
                                  .textTheme
                                  .titleSmall
                                  ?.copyWith(fontWeight: FontWeight.bold)),
                          const SizedBox(height: 4),
                          buildListTile(accountNameKey,
                              '${_selectedPaymentMethod!.accountName ?? ''}'),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              Expanded(
                                child: buildListTile(accountNumberKey,
                                    '${_selectedPaymentMethod!.accountNumber ?? ''}'),
                              ),
                              buildClipboard(
                                  _selectedPaymentMethod!.accountNumber ?? ''),
                            ],
                          ),
                          buildListTile(bankNameKey,
                              '${_selectedPaymentMethod!.bankName ?? ''}'),
                          Row(
                            children: <Widget>[
                              Flexible(
                                child: buildListTile(bankCodeKey,
                                    '${_selectedPaymentMethod!.bankCode ?? ''}'),
                              ),
                              buildClipboard(
                                  _selectedPaymentMethod!.bankCode ?? ''),
                            ],
                          )
                        ],
                      ),
                    ),
                    if (_selectedPaymentMethod!.notes != null) ...[
                      const SizedBox(height: 8),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.lightBlue[100],
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            CustomTextContainer(
                                textKey: extraDetailsKey,
                                style: Theme.of(context)
                                    .textTheme
                                    .titleSmall
                                    ?.copyWith(fontWeight: FontWeight.bold)),
                            const SizedBox(height: 4),
                            ..._parseHtmlString(_selectedPaymentMethod!.notes!),
                          ],
                        ),
                      ),
                    ]
                  ],
                ),
              ),
          ],
        ));
  }

  IconButton buildClipboard(String value) {
    return IconButton(
        visualDensity: const VisualDensity(horizontal: -4, vertical: -4),
        iconSize: 16,
        onPressed: () {
          Clipboard.setData(ClipboardData(text: value));
        },
        icon: Icon(Icons.copy));
  }

  buildListTile(String title, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        CustomTextContainer(
          textKey: title,
          style: Theme.of(context)
              .textTheme
              .bodyMedium!
              .copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: CustomTextContainer(
            textKey: value,
            style: Theme.of(context).textTheme.bodyMedium,
            maxLines: null,
            overflow: TextOverflow.visible,
          ),
        ),
      ],
    );
  }

  Widget buildPaymentMethodItem({required PaymentModel paymentMethod}) {
    return Material(
      child: ListTile(
        visualDensity: const VisualDensity(horizontal: -4, vertical: -2),
        tileColor: Theme.of(context).colorScheme.primaryContainer,
        contentPadding: const EdgeInsetsDirectional.symmetric(horizontal: 8),
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(borderRadius),
            side: BorderSide(
                color: Theme.of(context).inputDecorationTheme.iconColor!)),
        title: CustomTextContainer(
            textKey: paymentGatewayDisplayNames[paymentMethod.name!] ?? ''),
        leading: Radio(
          groupValue: _selectedPaymentMethod != null
              ? _selectedPaymentMethod!.name
              : null,
          value: paymentMethod.name,
          onChanged: (value) {
            changeSelection(paymentMethod);
          },
        ),
        trailing: Utils.setSvgImage(
          paymentMethod.image!,
        ),
        onTap: () {
          changeSelection(paymentMethod);
        },
      ),
    );
  }

  changeSelection(PaymentModel paymentMethod) {
    if (context.read<GetUserCartCubit>().getCartDetail().useWalletBalance ==
        true) {
      context.read<GetUserCartCubit>().useWalletBalance(false, 0);
    }
    setState(() {
      _selectedPaymentMethod = paymentMethod;
    });
    widget.paymentMethodCubit.setPaymentMethod(paymentMethod);
  }

  // Helper to parse simple HTML tags from notes (very basic)
  List<Widget> _parseHtmlString(String html) {
    final lines = html
        .replaceAll('<br>', '\n')
        .split(RegExp(r'<\/p>|<li>|<ul>|<\/ul>|<\/li>|<p>|<a [^>]+>|<\/a>'));
    return lines
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .map((line) => Padding(
              padding: const EdgeInsets.only(bottom: 2.0),
              child: Text(line, style: const TextStyle(fontSize: 14)),
            ))
        .toList();
  }
}
