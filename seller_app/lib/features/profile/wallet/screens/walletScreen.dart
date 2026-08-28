import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:value_market_seller/commons/widgets/primaryContainerWithBackground.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/configs/appConfig.dart';
import 'package:value_market_seller/core/constants/appConstants.dart'
    hide walletKey;
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/features/auth/blocs/authCubit.dart';
import 'package:value_market_seller/commons/blocs/userDetailsCubit.dart';

import 'package:value_market_seller/commons/widgets/customAppbar.dart';
import 'package:value_market_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_seller/commons/widgets/customRoundedButton.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';

import 'package:value_market_seller/features/profile/wallet/blocs/sendWithdrawalReqCubit.dart';
import 'package:value_market_seller/features/profile/wallet/blocs/transactionCubit.dart';
import 'package:value_market_seller/features/profile/wallet/screens/transactionScreen.dart';

import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../utils/utils.dart';

class WalletScreen extends StatefulWidget {
  const WalletScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => UserDetailsCubit(),
        child: const WalletScreen(),
      );
  @override
  _WalletScreenState createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  @override
  initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      context.read<UserDetailsCubit>().fetchUserDetails(params: {
        ApiURL.mobileApiKey: context.read<AuthCubit>().getUserMobile()
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppbar(
        titleKey: walletKey,
      ),
      body: BlocProvider(
        create: (context) => TransactionCubit(),
        child: Column(
          children: <Widget>[
            const SizedBox(
              height: 12,
            ),
            buildWalletContainer(),
            buildTabBar(),
          ],
        ),
      ),
    );
  }

  buildWalletContainer() {
    return PrimaryContainerWithBackground(
      child: Padding(
        padding: const EdgeInsetsDirectional.symmetric(
            horizontal: appContentHorizontalPadding),
        child: Column(
          children: <Widget>[
            CustomTextContainer(
                textKey: currentBalanceKey,
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium!
                    .copyWith(color: Theme.of(context).colorScheme.onPrimary)),
            const SizedBox(
              height: 4,
            ),
            BlocBuilder<UserDetailsCubit, UserDetailsState>(
              builder: (context, state) {
                return Column(
                  children: [
                    CustomTextContainer(
                        textKey: Utils.priceWithCurrencySymbol(
                            price: context
                                    .read<UserDetailsCubit>()
                                    .getuserDetails()
                                    .balance ??
                                0,
                            context: context),
                        style: Theme.of(context).textTheme.titleLarge!.copyWith(
                            color: Theme.of(context).colorScheme.onPrimary)),
                    DesignConfig.defaultHeightSizedBox,
                    CustomRoundedButton(
                      widthPercentage: 1.0,
                      buttonTitle: withdrawMoneyKey,
                      showBorder: false,
                      backgroundColor: Theme.of(context).colorScheme.onPrimary,
                      style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                          color: Theme.of(context).colorScheme.primary),
                      onTap: state is UserDetailsFetchSuccess &&
                              (state.userDetails.balance ?? 0) > 0
                          ? () => openWithdrawBottomSheet(
                              context.read<UserDetailsCubit>())
                          : () => Utils.showSnackBar(
                                message: insufficientWalletBalanceKey,
                              ),
                    ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  buildTabBar() {
    return Expanded(
      child: DefaultTabController(
        length: 2,
        child: Column(
          children: <Widget>[
            Container(
              height: 40,
              padding: const EdgeInsetsDirectional.only(
                  top: 5,
                  start: appContentHorizontalPadding,
                  end: appContentHorizontalPadding),
              color: Theme.of(context).colorScheme.primaryContainer,
              child: TabBar(
                dividerColor: Colors.transparent,
                indicatorColor: Theme.of(context).colorScheme.primary,
                labelColor: Theme.of(context).colorScheme.primary,
                unselectedLabelColor: Theme.of(context).colorScheme.secondary,
                indicatorSize: TabBarIndicatorSize.tab,
                labelStyle: Theme.of(context).textTheme.bodyLarge,
                tabs: [
                  buildTabLabel(walletTransactionKey),
                  buildTabLabel(walletWithdrawKey)
                ],
              ),
            ),
            Expanded(
              child: TabBarView(
                  physics:
                      const NeverScrollableScrollPhysics(), // This approach forces both tabs to build at once, which may have performance implications if your TransactionScreen is resource-intensive.
                  children: [
                    TransactionScreen(
                      walletType: creditType,
                    ),
                    BlocProvider(
                        create: (context) => TransactionCubit(),
                        child: TransactionScreen(
                          walletType: debitType,
                          key: TransactionScreen.withdrawScreenKey,
                        )),
                  ]),
            )
          ],
        ),
      ),
    );
  }

  Tab buildTabLabel(String title) {
    return Tab(
      text: context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: title),
    );
  }

  openWithdrawBottomSheet(UserDetailsCubit userDetailsCubit) {
    Map<String, TextEditingController> controllers = {};
    Map<String, FocusNode> focusNodes = {};
    final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
    final List formFields = [
      withdrawalAmountKey,
      accountNumberKey,
      nameKey,
      ifscCodeKey,
    ];
    formFields.forEach((key) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    });
    if (isDemoApp) {
      Utils.showSnackBar(message: demoModeOnKey);
      return;
    }
    Utils.openModalBottomSheet(
            context,
            BlocProvider(
              create: (context) => SendWithdrawalRequestCubit(),
              child: BlocConsumer<SendWithdrawalRequestCubit,
                  SendWithdrawalRequestState>(
                listener: (context, state) {
                  if (state is SendWithdrawalRequestSuccess) {
                    if (TransactionScreen.withdrawScreenKey.currentState !=
                        null) {
                      TransactionScreen.withdrawScreenKey.currentState!
                          .addItemInList(state.transaction);
                    }
                    userDetailsCubit.fetchUserDetails(params: {
                      ApiURL.mobileApiKey:
                          context.read<AuthCubit>().getUserMobile()
                    });

                    Future.delayed(const Duration(milliseconds: 250), () {
                      Navigator.of(context).pop();
                    });
                    Utils.showSnackBar(message: state.successMessage);
                  }
                  if (state is SendWithdrawalRequestFailure) {
                    Navigator.of(context).pop();
                    Utils.showSnackBar(message: state.errorMessage);
                  }
                },
                builder: (context, state) {
                  return FilterContainerForBottomSheet(
                      title: '',
                      borderedButtonTitle: cancelKey,
                      primaryButtonTitle: sendKey,
                      primaryChild: state is SendWithdrawalRequestProgress
                          ? const CustomCircularProgressIndicator()
                          : null,
                      borderedButtonOnTap: () => Navigator.of(context).pop(),
                      primaryButtonOnTap: () {
                        if (_formKey.currentState!.validate()) {
                          if (state is! SendWithdrawalRequestProgress) {
                            context
                                .read<SendWithdrawalRequestCubit>()
                                .sendWithdrawalRequest(params: {
                              ApiURL.amountApiKey:
                                  controllers[withdrawalAmountKey]!.text.trim(),
                              ApiURL.paymentAddressApiKey:
                                  '${controllers[accountNumberKey]!.text.toString()}\n${controllers[ifscCodeKey]!.text.toString()}\n${controllers[nameKey]!.text.toString()}'
                            });
                          }
                        }
                      },
                      content: Form(
                        key: _formKey,
                        autovalidateMode: AutovalidateMode.onUserInteraction,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            CustomTextFieldContainer(
                              hintTextKey: withdrawalAmountKey,
                              textEditingController:
                                  controllers[withdrawalAmountKey]!,
                              focusNode: focusNodes[withdrawalAmountKey],
                              textInputAction: TextInputAction.next,
                              keyboardType: TextInputType.number,
                              inputFormatters: [
                                FilteringTextInputFormatter.allow(
                                    RegExp(r'^\d+\.?\d{0,2}')),
                              ],
                              validator: (val) {
                                if (val.trim().isEmpty) {
                                  return Validator.emptyValueValidation(
                                      val, context);
                                }
                                final amount = double.tryParse(val);
                                if (amount == null || amount <= 0) {
                                  return context
                                      .read<SettingsAndLanguagesCubit>()
                                      .getTranslatedValue(
                                          labelKey: enterValidAmountKey);
                                }
                              },
                              onFieldSubmitted: (v) {
                                FocusScope.of(context)
                                    .requestFocus(focusNodes[accountNumberKey]);
                              },
                            ),
                            DesignConfig.defaultHeightSizedBox,
                            CustomTextContainer(
                              textKey: bankDetailsKey,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            DesignConfig.smallHeightSizedBox,
                            CustomTextFieldContainer(
                              hintTextKey: accountNumberKey,
                              textEditingController:
                                  controllers[accountNumberKey]!,
                              focusNode: focusNodes[accountNumberKey],
                              keyboardType: TextInputType.number,
                              inputFormatters: [
                                FilteringTextInputFormatter.digitsOnly
                              ],
                              textInputAction: TextInputAction.next,
                              validator: (val) =>
                                  Validator.emptyValueValidation(val, context),
                              onFieldSubmitted: (v) {
                                FocusScope.of(context)
                                    .requestFocus(focusNodes[ifscCodeKey]);
                              },
                            ),
                            CustomTextFieldContainer(
                              hintTextKey: ifscCodeKey,
                              textEditingController: controllers[ifscCodeKey]!,
                              focusNode: focusNodes[ifscCodeKey],
                              textInputAction: TextInputAction.next,
                              validator: (val) =>
                                  Validator.emptyValueValidation(val, context),
                              onFieldSubmitted: (v) {
                                FocusScope.of(context)
                                    .requestFocus(focusNodes[nameKey]);
                              },
                            ),
                            CustomTextFieldContainer(
                              hintTextKey: nameKey,
                              textEditingController: controllers[nameKey]!,
                              focusNode: focusNodes[nameKey],
                              textInputAction: TextInputAction.done,
                              validator: (val) =>
                                  Validator.emptyValueValidation(val, context),
                              onFieldSubmitted: (v) {
                                focusNodes[nameKey]!.unfocus();
                              },
                            ),
                          ],
                        ),
                      ));
                },
              ),
            ),
            isScrollControlled: false,
            staticContent: true)
        .then((value) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        controllers.forEach((key, controller) {
          controller.dispose();
        });
        focusNodes.forEach((key, focusNode) {
          focusNode.dispose();
        });
      });
    });
  }
}
