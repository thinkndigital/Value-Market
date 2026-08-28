import 'dart:math';

import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/ui/profile/transaction/blocs/getPaymentMethodCubit.dart';
import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_customer/ui/profile/transaction/models/paymentMethod.dart';
import 'package:value_market_customer/ui/profile/transaction/repositories/transactionRepository.dart';
import 'package:value_market_customer/ui/profile/transaction/widgets/paymentMethodList.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customBottomButtonContainer.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_customer/commons/widgets/customRoundedButton.dart';
import 'package:value_market_customer/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_customer/commons/widgets/error_screen.dart';
import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:value_market_customer/utils/validator.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

class AddMoneyScreen extends StatefulWidget {
  const AddMoneyScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => PaymentMethodCubit(),
        child: const AddMoneyScreen(),
      );
  @override
  _AddMoneyScreenState createState() => _AddMoneyScreenState();
}

class _AddMoneyScreenState extends State<AddMoneyScreen> {
  final TextEditingController _amountController = TextEditingController();
  final TextEditingController _messageController = TextEditingController();
  final FocusNode _messageNode = FocusNode();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late Razorpay _razorpay;
  late PaymentModel _selectedPaymentMethod;
  bool _isLoading = false;
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      context.read<PaymentMethodCubit>().fetchPaymentMethods();
    });

    _razorpay = Razorpay();
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handlePaymentSuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handlePaymentError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleExternalWallet);
  }

  @override
  void dispose() {
    _amountController.dispose();
    _messageController.dispose();
    _messageNode.dispose();

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<PaymentMethodCubit, PaymentMethodState>(
      listener: (context, state) {
        if (state is PaymentMethodFetchSuccess) {
          state.paymentMethods
              .removeWhere((element) => element.name == cashOnDeliveryKey);
          _selectedPaymentMethod = state.paymentMethods
              .firstWhere((element) => element.isSelected == true);
        }
      },
      builder: (context, state) {
        return GestureDetector(
          onTap: () => FocusScope.of(context).unfocus(),
          child: Scaffold(
              appBar: const CustomAppbar(titleKey: addMoneyKey),
              bottomNavigationBar: buildAddMoneyButton(),
              body: GestureDetector(
                onTap: () {
                  FocusScope.of(context).unfocus();
                },
                child: Container(
                    padding: const EdgeInsetsDirectional.symmetric(
                        vertical: 12, horizontal: appContentHorizontalPadding),
                    child: state is PaymentMethodFetchSuccess
                        ? Form(
                            key: _formKey,
                            autovalidateMode:
                                AutovalidateMode.onUserInteraction,
                            child: SingleChildScrollView(
                              child: Column(
                                children: <Widget>[
                                  CustomDefaultContainer(
                                      borderRadius: 8,
                                      child: Column(
                                        children: <Widget>[
                                          CustomTextFieldContainer(
                                              hintTextKey: amountKey,
                                              keyboardType:
                                                  TextInputType.number,
                                              textEditingController:
                                                  _amountController,
                                              textInputAction:
                                                  TextInputAction.next,
                                              onFieldSubmitted: (p0) =>
                                                  FocusScope.of(context)
                                                      .requestFocus(
                                                          _messageNode),
                                              inputFormatters: [
                                                FilteringTextInputFormatter
                                                    .allow(RegExp(
                                                        r'^\d+\.?\d{0,2}')),
                                              ],
                                              validator: (val) {
                                                if (val.trim().isEmpty) {
                                                  return Validator
                                                      .emptyValueValidation(
                                                          context, val);
                                                }
                                                final amount =
                                                    double.tryParse(val);
                                                if (amount == null ||
                                                    amount <= 0) {
                                                  return context
                                                      .read<
                                                          SettingsAndLanguagesCubit>()
                                                      .getTranslatedValue(
                                                          labelKey:
                                                              enterValidAmountKey);
                                                }
                                              }),
                                        ],
                                      )),
                                  DesignConfig.smallHeightSizedBox,
                                  PaymentMethodList(
                                    paymentMethods: state.paymentMethods,
                                    paymentMethodCubit:
                                        context.read<PaymentMethodCubit>(),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : state is PaymentMethodFetchFailure
                            ? ErrorScreen(
                                text: state.errorMessage,
                                onPressed: () => context
                                    .read<PaymentMethodCubit>()
                                    .fetchPaymentMethods())
                            : state is PaymentMethodFetchInProgress
                                ? CustomCircularProgressIndicator(
                                    indicatorColor:
                                        Theme.of(context).colorScheme.primary)
                                : Container()),
              )),
        );
      },
    );
  }

  buildAddMoneyButton() {
    return CustomBottomButtonContainer(
        child: CustomRoundedButton(
      widthPercentage: 1.0,
      buttonTitle: addMoneyKey,
      showBorder: false,
      child: _isLoading ? const CustomCircularProgressIndicator() : null,
      onTap: () async {
        _selectedPaymentMethod = context
            .read<PaymentMethodCubit>()
            .getPaymentMethodList()
            .firstWhere((element) => element.isSelected == true);
        if (_formKey.currentState!.validate() && !_isLoading) {
          var response;
          _formKey.currentState!.save();
          setState(() {
            _isLoading = true;
          });
          String orderID =
              'wallet-refill-user-${context.read<UserDetailsCubit>().getUserId()}-${DateTime.now().millisecondsSinceEpoch}-${Random().nextInt(900) + 100}';
          if (_selectedPaymentMethod.name == razorpayKey) {
            await TransactionRepository().doPaymentWithRazorpay(
                context: context,
                orderID: orderID,
                razorpay: _razorpay,
                razorpayId: _selectedPaymentMethod.razorpayKeyId!,
                price: double.parse(_amountController.text.trim()));
            return;
          }
          if (_selectedPaymentMethod.name == stripeKey) {
            response = await TransactionRepository().doPaymentWithStripe(
                price: double.parse(_amountController.text.trim()),
                currencyCode: _selectedPaymentMethod.stripeCurrencyCode!,
                paymentFor: walletTransactionType,
                context: context);
          }
          if (_selectedPaymentMethod.name == paystackKey) {
            response = await TransactionRepository().doPaymentWithPayStack(
                price: double.parse(_amountController.text.trim()),
                orderID: orderID,
                context: context,
                paystackId: _selectedPaymentMethod.paystackKeyId!);
          }
          if (_selectedPaymentMethod.name == paypalKey) {
            response = await TransactionRepository().doPaymentWithPaypal(
                price: double.parse(_amountController.text.trim()),
                orderID: orderID,
                type: walletTransactionType,
                context: context);
          }
          if (_selectedPaymentMethod.name == phonepeKey) {
            response = await TransactionRepository().doPaymentWithPhonePe(
                context: context,
                price: double.parse(_amountController.text.trim()),
                environment: _selectedPaymentMethod.phonepeMode!,
                appId: _selectedPaymentMethod.phonepeSaltKey,
                merchantId: _selectedPaymentMethod.phonepeMarchantId!,
                transactionType: walletTransactionType,
                orderID:
                    '${context.read<UserDetailsCubit>().getUserId()}${DateTime.now().millisecondsSinceEpoch}',
                type: walletTransactionType);
          }
          setState(() {
            _isLoading = false;
          });
          if (response['message'] != '')
            Utils.showSnackBar(message: response['message'], context: context);
          if (response['error'] == false) {
            Navigator.pop(context, 'update');
          }
        }
      },
    ));
  }

  void _handlePaymentSuccess(PaymentSuccessResponse response) async {
    Utils.showSnackBar(message: 'Transaction Successful', context: context);
    Navigator.pop(context, 'update');
    setState(() {
      _isLoading = false;
    });
  }

  void _handlePaymentError(PaymentFailureResponse response) {
    Utils.showSnackBar(message: defaultErrorMessageKey, context: context);
    Navigator.pop(context, 'false');
    setState(() {
      _isLoading = false;
    });
  }

  void _handleExternalWallet(ExternalWalletResponse response) {}
}
