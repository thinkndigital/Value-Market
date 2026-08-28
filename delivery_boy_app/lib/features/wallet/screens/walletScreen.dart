import 'package:eshoppro_deliveryboy/core/configs/appConfig.dart';
import 'package:eshoppro_deliveryboy/core/constants/appConstants.dart';
import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/features/wallet/blocs/sendWithdrawalReqCubit.dart';
import 'package:eshoppro_deliveryboy/features/wallet/blocs/transactionCubit.dart';
import 'package:eshoppro_deliveryboy/features/wallet/blocs/transactionScreen.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshoppro_deliveryboy/core/api/apiEndPoints.dart';

import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../../utils/utils.dart';
import '../../../commons/blocs/userDetailsCubit.dart';

import '../../../utils/designConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../utils/inputValidators.dart';
import '../../../commons/widgets/customAppbar.dart';
import '../../../commons/widgets/customCircularProgressIndicator.dart';
import '../../../commons/widgets/customRoundedButton.dart';
import '../../../commons/widgets/customTextContainer.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';
import '../../../commons/widgets/filterContainerForBottomSheet.dart';
import '../../../commons/widgets/primaryContainerWithBackground.dart';

class WalletScreen extends StatefulWidget {
  const WalletScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => const WalletScreen();
  @override
  _WalletScreenState createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(
        titleKey: walletKey,
        showBackButton: false,
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
                          ? openWithdrawBottomSheet
                          : () => Utils.showSnackBar(
                              message: 'Insufficient wallet balance',
                              context: context),
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
              child: TabBarView(children: [
                TransactionScreen(
                  walletType: creditType,
                ),
                BlocProvider(
                    create: (context) => TransactionCubit(),
                    child: TransactionScreen(
                      key: TransactionScreen.withdrawScreenKey,
                      walletType: debitType,
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

  openWithdrawBottomSheet() {
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
    Utils.openModalBottomSheet(
            context,
            BlocProvider(
              create: (context) => SendWithdrawalRequestCubit(),
              child: BlocConsumer<SendWithdrawalRequestCubit,
                  SendWithdrawalRequestState>(
                listener: (context, state) {
                  if (state is SendWithdrawalRequestSuccess) {
                    TransactionScreen.withdrawScreenKey.currentState!
                        .addItemInList(state.transaction);
                    Future.delayed(const Duration(milliseconds: 500), () {
                      Navigator.of(context).pop();
                    });
                    Utils.showSnackBar(
                        context: context, message: state.successMessage);
                  }
                  if (state is SendWithdrawalRequestFailure) {
                    Navigator.of(context).pop();
                    Utils.showSnackBar(
                        context: context, message: state.errorMessage);
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
                            if (isDemoApp) {
                              Navigator.of(context).pop();
                              Utils.showSnackBar(
                                  message: demoModeOnKey, context: context);
                              return;
                            }
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
                              validator: (val) =>
                                  Validator.emptyValueValidation(val, context),
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
