import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/ui/cart/cubits/getUserCart.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/ui/profile/transaction/blocs/getPaymentMethodCubit.dart';
import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/ui/profile/transaction/models/paymentMethod.dart';
import 'package:eshop_plus/ui/profile/transaction/widgets/paymentMethodList.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

class SelectPaymentScreen extends StatefulWidget {
  final PaymentModel? selectedPaymentMethod;
  final PaymentMethodCubit paymentMethodCubit;
  final UserDetailsCubit userDetailsCubit;
  const SelectPaymentScreen(
      {Key? key,
      required this.selectedPaymentMethod,
      required this.paymentMethodCubit,
      required this.userDetailsCubit})
      : super(key: key);

  @override
  _SelectPaymentScreenState createState() => _SelectPaymentScreenState();
}

class _SelectPaymentScreenState extends State<SelectPaymentScreen> {
  late Razorpay _razorpay;
  bool _isWalletPayment = false;

  @override
  void initState() {
    super.initState();

    Future.delayed(Duration.zero, () {
      if (mounted) {
        _isWalletPayment =
            context.read<GetUserCartCubit>().getCartDetail().useWalletBalance ??
                true;

        setState(() {});
      }
    });
    _razorpay = Razorpay();
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handlePaymentSuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handlePaymentError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleExternalWallet);
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: BlocConsumer<UserDetailsCubit, UserDetailsState>(
          bloc: widget.userDetailsCubit,
          listener: (context, state) {},
          builder: (context, state) {
            if (state is UserDetailsFetchInProgress) {
              return Center(
                  child: CustomCircularProgressIndicator(
                      indicatorColor: Theme.of(context).colorScheme.primary));
            }
            return Column(children: [
              Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(8),
                  color: Theme.of(context).colorScheme.primaryContainer,
                ),
                margin: const EdgeInsetsDirectional.symmetric(
                    vertical: 12, horizontal: appContentHorizontalPadding),
                padding: const EdgeInsetsDirectional.symmetric(
                    vertical: appContentHorizontalPadding, horizontal: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    CustomTextContainer(
                      textKey: walletBalanceKey,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    DesignConfig.defaultHeightSizedBox,
                    BlocListener<GetUserCartCubit, GetUserCartState>(
                      listener: (context, state) {
                        if (state is GetUserCartFetchSuccess) {
                          _isWalletPayment = context
                                  .read<GetUserCartCubit>()
                                  .getCartDetail()
                                  .useWalletBalance ??
                              true;
                        }
                      },
                      child: Material(
                        child: ListTile(
                            visualDensity: const VisualDensity(
                                horizontal: -4, vertical: -2),
                            tileColor:
                                Theme.of(context).colorScheme.primaryContainer,
                            contentPadding:
                                const EdgeInsetsDirectional.symmetric(
                                    horizontal: 16),
                            shape: RoundedRectangleBorder(
                                borderRadius:
                                    BorderRadius.circular(borderRadius),
                                side: BorderSide(
                                    color: Theme.of(context)
                                        .inputDecorationTheme
                                        .iconColor!)),
                            title: CustomTextContainer(
                                textKey:
                                    '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: availableBalanceKey)} : ${Utils.priceWithCurrencySymbol(price: context.read<UserDetailsCubit>().getuserDetails().balance ?? 0, context: context)}'),
                            leading: Container(
                              width: 18.0,
                              height: 18.0,
                              decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  border: Border.all(
                                      color: _isWalletPayment
                                          ? Theme.of(context)
                                              .colorScheme
                                              .primary
                                          : Theme.of(context)
                                              .colorScheme
                                              .secondary,
                                      width: 2)),
                              padding: const EdgeInsetsDirectional.all(2.0),
                              child: _isWalletPayment
                                  ? Container(
                                      decoration: BoxDecoration(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .primary,
                                        shape: BoxShape.circle,
                                      ),
                                    )
                                  : const SizedBox(),
                            ),
                            onTap: changeSelection),
                      ),
                    )
                  ],
                ),
              ),
              BlocBuilder<PaymentMethodCubit, PaymentMethodState>(
                bloc: widget.paymentMethodCubit,
                builder: (context, state) {
                  if (state is PaymentMethodFetchSuccess) {
                    if (state.paymentMethods.isEmpty ||
                        widget.selectedPaymentMethod == null) {
                      return Center(
                          child: CustomTextContainer(
                              textKey: paymentMethodsAreNotAvailableKey));
                    }
                    return IgnorePointer(
                      ignoring: false
                      /* (
                         _isWalletPayment &&
                          context
                                  .read<UserDetailsCubit>()
                                  .getuserDetails()
                                  .balance !=
                              null &&
                          context
                                  .read<UserDetailsCubit>()
                                  .getuserDetails()
                                  .balance! >
                              (context
                                      .read<GetUserCartCubit>()
                                      .getCartDetail()
                                      .originalOverallAmount ??
                                  0.0) 
                          )*/
                      ,
                      child: Padding(
                        padding: const EdgeInsetsDirectional.symmetric(
                            vertical: 8, horizontal: 8),
                        child: PaymentMethodList(
                          paymentMethods: state.paymentMethods,
                          paymentMethodCubit: widget.paymentMethodCubit,
                        ),
                      ),
                    );
                  }
                  if (state is PaymentMethodFetchFailure) {
                    return ErrorScreen(
                        text: state.errorMessage,
                        onPressed: () {
                          widget.paymentMethodCubit.fetchPaymentMethods();
                        });
                  }
                  if (state is PaymentMethodFetchInProgress) {
                    return CustomCircularProgressIndicator(
                        indicatorColor: Theme.of(context).colorScheme.primary);
                  }
                  return const SizedBox.shrink();
                },
              )
            ]);
          }),
    );
  }

  changeSelection() {
    if (context.read<UserDetailsCubit>().getuserDetails().balance! >=
        context.read<GetUserCartCubit>().getCartDetail().overallAmount!) {
      setState(() {
        _isWalletPayment = true;

        context.read<GetUserCartCubit>().useWalletBalance(_isWalletPayment,
            context.read<UserDetailsCubit>().getuserDetails().balance ?? 0);
        if (_isWalletPayment) {
          if (context.read<GetUserCartCubit>().state
                  is GetUserCartFetchSuccess &&
              (context.read<GetUserCartCubit>().state
                          as GetUserCartFetchSuccess)
                      .cart
                      .selectedPaymentMethod ==
                  null) {
            if (widget.paymentMethodCubit.state is PaymentMethodFetchSuccess) {
              (widget.paymentMethodCubit.state as PaymentMethodFetchSuccess)
                  .paymentMethods
                  .forEach((element) => element.isSelected = false);
            }
          }
        } else {
          // if user deselected wallet payment method then select first payment method
          if (widget.paymentMethodCubit.state is PaymentMethodFetchSuccess) {
            context.read<GetUserCartCubit>().changePaymantMethod(
                (widget.paymentMethodCubit.state as PaymentMethodFetchSuccess)
                    .paymentMethods
                    .first);
            (widget.paymentMethodCubit.state as PaymentMethodFetchSuccess)
                .paymentMethods
                .first
                .isSelected = true;
          }
        }
        setState(() {});
      });
    } else {
      Utils.showSnackBar(
          message: insufficientWalletBalanceKey, context: context);
    }
  }

  void _handlePaymentSuccess(PaymentSuccessResponse response) async {
    Utils.showSnackBar(message: transactionSuccessfulKey, context: context);
    Navigator.pop(context, response);
  }

  void _handlePaymentError(PaymentFailureResponse response) {
    Utils.showSnackBar(message: defaultErrorMessageKey, context: context);
    Navigator.pop(context, response);
  }

  void _handleExternalWallet(ExternalWalletResponse response) {}
}
