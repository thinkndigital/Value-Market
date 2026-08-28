// ignore_for_file: use_build_context_synchronously

import 'package:dio/dio.dart' as dio;
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/profile/address/blocs/addNewAddressCubit.dart';
import 'package:value_market_customer/ui/profile/address/blocs/deleteAddressCubit.dart';
import 'package:value_market_customer/ui/profile/address/blocs/getAddressCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/checkCartProductDelCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/clearCartCubit.dart';
import 'package:value_market_customer/ui/cart/cubits/getUserCart.dart';
import 'package:value_market_customer/ui/cart/cubits/placeOrderCubit.dart';
import 'package:value_market_customer/ui/profile/orders/blocs/deleteOrderCubit.dart';
import 'package:value_market_customer/ui/profile/orders/blocs/updateOrderCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/ui/profile/transaction/blocs/addTransactionCubit.dart';
import 'package:value_market_customer/ui/profile/transaction/blocs/getPaymentMethodCubit.dart';
import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_customer/ui/profile/address/models/address.dart';
import 'package:value_market_customer/ui/cart/models/cart.dart';
import 'package:value_market_customer/ui/profile/transaction/models/paymentMethod.dart';
import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/ui/cart/repositories/cartRepository.dart';
import 'package:value_market_customer/ui/profile/transaction/repositories/transactionRepository.dart';
import 'package:value_market_customer/ui/cart/screens/selectPaymentScreen.dart';
import 'package:value_market_customer/ui/cart/widgets/customStepper.dart';
import 'package:value_market_customer/ui/cart/widgets/finalCartScreen.dart';
import 'package:value_market_customer/ui/mainScreen.dart';
import 'package:value_market_customer/ui/profile/address/screens/myAddressScreen.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customBottomButtonContainer.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_customer/commons/widgets/customRoundedButton.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_customer/commons/widgets/error_screen.dart';
import 'package:value_market_customer/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:value_market_customer/ui/profile/transaction/widgets/stripeService.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:value_market_customer/utils/validator.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

class PlaceOrderScreen extends StatefulWidget {
  final bool showAddressSelection;
  final int storeId;
  const PlaceOrderScreen(
      {Key? key, required this.showAddressSelection, required this.storeId})
      : super(key: key);
  static Widget getRouteInstance() => MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => CheckCartProductDeliverabilityCubit(),
          ),
          BlocProvider(
            create: (context) => PaymentMethodCubit(),
          ),
          // we have taken provider of userdetailcubit separatrely here, so that calling user API wont recall all APIs of app
          BlocProvider(
            create: (context) => UserDetailsCubit(),
          ),
          BlocProvider(
            create: (context) => PlaceOrderCubit(),
          ),
          BlocProvider(
            create: (context) => ClearCartCubit(),
          ),
          BlocProvider(
            create: (context) => UpdateOrderCubit(),
          ),
          BlocProvider(
            create: (context) => AddTransactionCubit(),
          ),
          BlocProvider(
            create: (context) => DeleteOrderCubit(),
          ),
        ],
        child: PlaceOrderScreen(
          showAddressSelection: Get.arguments['showAddressSelection'],
          storeId: Get.arguments['storeId'],
        ),
      );
  @override
  _PlaceOrderScreenState createState() => _PlaceOrderScreenState();
}

class _PlaceOrderScreenState extends State<PlaceOrderScreen> {
  int _currentStep = 1;
  int _stepLength = 3;
  bool _isLoading = false;
  String currentOrderID = '';
  PaymentModel? _selectedPaymentMethod;
  Address? _selectedAddress;
  Razorpay? _razorpay;
  bool _canPop = false;
  @override
  initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      context
          .read<UserDetailsCubit>()
          .fetchUserDetails(params: Utils.getParamsForVerifyUser(context));
      context.read<PaymentMethodCubit>().fetchPaymentMethods();

      //if the product type is digital or the user has one default address selected then we will skip this step
      if (context
                  .read<GetUserCartCubit>()
                  .getCartDetail()
                  .cartProducts![0]
                  .type ==
              digitalProductType ||
          (context.read<GetAddressCubit>().state is GetAddressFetchSuccess &&
              (context.read<GetAddressCubit>().state as GetAddressFetchSuccess)
                      .addresses
                      .indexWhere((e) => e.isDefault == true) !=
                  -1) ||
          !widget.showAddressSelection) {
        List<Address> addresses =
            (context.read<GetAddressCubit>().state as GetAddressFetchSuccess)
                .addresses;
        int index = addresses.indexWhere((element) => element.isDefault == 1);
        if (index != -1) {
          _selectedAddress = addresses[index];
        } else {
          _selectedAddress = addresses.first;
        }

        setState(() {
          _currentStep = 2;
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return MultiBlocListener(
      listeners: [
        BlocListener<DeleteOrderCubit, DeleteOrderState>(
          listener: (context, state) async {
            if (state is DeleteOrderFailure) {
              Utils.showSnackBar(message: state.errorMessage, context: context);
            }
            if (state is DeleteOrderSuccess) {}
          },
        ),
        BlocListener<AddTransactionCubit, AddTransactionState>(
          listener: (context, state) {
            if (state is AddTransactionSuccess) {
              context.read<ClearCartCubit>().clearCart(context);

              Utils.navigateToScreen(context, Routes.orderConfirmedScreen,
                  arguments: {
                    'orderId': state.orderId,
                    'storeId': widget.storeId
                  },
                  replacePrevious: true);
            }
            if (state is AddTransactionFailure) {
              Utils.showSnackBar(message: state.errorMessage, context: context);
            }
          },
        ),
        BlocListener<PaymentMethodCubit, PaymentMethodState>(
          listener: (context, state) {
            if (state is PaymentMethodFetchSuccess) {
              if (context
                      .read<GetUserCartCubit>()
                      .getCartDetail()
                      .cartProducts![0]
                      .type ==
                  digitalProductType) {
                state.paymentMethods.removeWhere(
                    (element) => element.name == cashOnDeliveryKey);
              }

              //we will remove COD option if at least one product is not COD allowed
              if (context
                      .read<GetUserCartCubit>()
                      .getCartDetail()
                      .cartProducts !=
                  null) {
                if (context
                        .read<GetUserCartCubit>()
                        .getCartDetail()
                        .cartProducts!
                        .indexWhere((element) =>
                            element.productDetails!.first.codAllowed == 0) !=
                    -1) {
                  state.paymentMethods.removeWhere(
                      (element) => element.name == cashOnDeliveryKey);
                }
              }
              _selectedPaymentMethod = state.paymentMethods
                  .firstWhereOrNull((element) => element.isSelected == true);
              context
                  .read<GetUserCartCubit>()
                  .changePaymantMethod(_selectedPaymentMethod);
              if (state.paymentMethods.isEmpty) {
                _selectedPaymentMethod = null;
              }
            }
          },
        ),
      ],
      child: BlocConsumer<PlaceOrderCubit, PlaceOrderState>(
        listener: (context, state) {
          // if payable amount is 0 then not necessary to redirect to payment screen
          if (state is PlaceOrderSuccess) {
            setState(() {
              _isLoading = false;
            });
            if (context
                    .read<GetUserCartCubit>()
                    .getCartDetail()
                    .useWalletBalance ==
                true) {}
            mainScreenKey?.currentState!.refreshProducts();
            currentOrderID = state.orderId.toString();
            if (context
                    .read<GetUserCartCubit>()
                    .getCartDetail()
                    .overallAmount ==
                0) {
              doOnSuccess();
            } else {
              initiatePaymentMethod(state.orderId, state.finalTotal);
            }
          }
          if (state is PlaceOrderFailure) {
            Utils.showSnackBar(
                context: context,
                message: state.errorMessage,
                duration: const Duration(seconds: 5),
                backgroundColor: Theme.of(context).colorScheme.error);
          }
        },
        builder: (context, state) {
          return Stack(
            clipBehavior: Clip.hardEdge,
            children: [
              PopScope(
                canPop: _canPop,
                onPopInvokedWithResult: (didPop, result) async {
                  if (didPop) return;

                  callFunctionOnBackPress();
                },
                child: BlocBuilder<GetUserCartCubit, GetUserCartState>(
                  builder: (context, state) {
                    return Scaffold(
                      appBar: CustomAppbar(
                          titleKey: _currentStep == 1
                              ? selectAddressKey
                              : _currentStep == 2
                                  ? selectPaymentKey
                                  : cartKey,
                          onBackButtonTap: callFunctionOnBackPress),
                      body: buildBody(),
                      bottomNavigationBar: buildNavigationButtons(),
                    );
                  },
                ),
              ),
              if (state is PlaceOrderInProgress)
                Container(
                  color: Colors.black.withValues(alpha: 0.4),
                  child: CustomCircularProgressIndicator(
                    strokeWidth: 3,
                    indicatorColor: Theme.of(context).colorScheme.primary,
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  callFunctionOnBackPress() {
    if (_currentStep > 1) {
      if (context.read<GetUserCartCubit>().getCartDetail().cartProducts !=
              null &&
          context
                  .read<GetUserCartCubit>()
                  .getCartDetail()
                  .cartProducts![0]
                  .type ==
              digitalProductType &&
          _currentStep == 2) {
        _canPop = true;
        Navigator.of(context).pop();
        return;
      }
      if (_currentStep == 2 &&
          context.read<GetUserCartCubit>().getCartDetail().selectedAddress !=
              null) {
        _canPop = true;
        Navigator.of(context).pop();
        return;
      } else {
        _canPop = false;
        setState(() {
          _currentStep--;
        });
      }
    } else {
      _canPop = true;
      Navigator.of(context).pop();
      return;
    }
  }

  Widget buildNavigationButtons() {
    return BlocBuilder<GetAddressCubit, GetAddressState>(
        builder: (context, state) {
      return BlocListener<CheckCartProductDeliverabilityCubit,
          CheckCartProductDeliverabilityState>(
        listener: (context, state) {
          if (state is CheckCartProductDeliverabilitySuccess) {
            context.read<GetUserCartCubit>().resetErrorMessages();
            if (_currentStep == 1)
              setState(() {
                _currentStep = 2;
              });
          }
          if (state is CheckCartProductDeliverabilityFailure) {
            if (state.errorData != null) {
              for (var item in state.errorData!) {
                if (item['is_deliverable'] == false) {
                  int productId = item['product_id'];
                  String errorMessage =
                      '${item['name']} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: notDelierableErrorMessageKey)}';

                  // Find the corresponding CartProduct and assign the error message

                  context
                      .read<GetUserCartCubit>()
                      .setErrorMessage(productId, errorMessage);
                }
              }
            }

            Utils.showSnackBar(
                context: context,
                duration: const Duration(seconds: 7),
                backgroundColor: Theme.of(context).colorScheme.error,
                message: state.errorMessage);
            if (Get.currentRoute == Routes.placeOrderScreen)
              Navigator.of(context).pop(true);
          }
        },
        child: BlocBuilder<PlaceOrderCubit, PlaceOrderState>(
            builder: (context, state) {
          if (_currentStep == 1 &&
              context.read<GetAddressCubit>().getAddressList().isEmpty) {
            return const SizedBox.shrink();
          }
          return CustomBottomButtonContainer(
            child: CustomRoundedButton(
              widthPercentage: 0.5,
              buttonTitle: _currentStep == 1
                  ? deliverToThisAddressKey
                  : _currentStep == 2
                      ? continueKey
                      : placeOrderKey,
              showBorder: false,
              onTap: () async {
                if (_currentStep == 1) {
                  if (_selectedAddress != null ||
                      context
                              .read<GetUserCartCubit>()
                              .getCartDetail()
                              .selectedAddress !=
                          null) {
                    if (_selectedAddress != null &&
                        _selectedAddress !=
                            context
                                .read<GetUserCartCubit>()
                                .getCartDetail()
                                .selectedAddress) {
                      // Step 1: Update the Cart with the New Address

                      // Step 2: Change Selected Address in Cart Locally
                      context
                          .read<GetUserCartCubit>()
                          .changeSelectedAddress(_selectedAddress);

                      // Step 3: Get All User Addresses
                      List<Address> addresses =
                          context.read<GetAddressCubit>().getAddressList();

                      // Step 4: Check if Selected Address is Already the Default
                      Address? currentDefault =
                          addresses.firstWhereOrNull((e) => e.isDefault == 1);

                      if (currentDefault == null ||
                          currentDefault.id != _selectedAddress!.id) {
                        // If selected address is NOT the default, update it
                        final updatedAddresses = addresses.map((address) {
                          if (address.id == _selectedAddress!.id) {
                            return address.copyWith(isDefault: 1);
                          } else {
                            return address.copyWith(isDefault: 0);
                          }
                        }).toList();

                        // Step 5: Emit Updated Address List
                        context
                            .read<GetAddressCubit>()
                            .emitSuccessState(updatedAddresses);

                        // Step 6: Call API to Make the Selected Address Default
                        await context
                            .read<AddNewAddressCubit>()
                            .addAddress(params: {
                          ApiURL.idApiKey: _selectedAddress!.id!,
                          ApiURL.isDefaultAddressApiKey: 1,
                        });
                        context.read<GetUserCartCubit>().updateCart(
                          context: context,
                          oldCart:
                              context.read<GetUserCartCubit>().getCartDetail(),
                          params: {
                            ApiURL.storeIdApiKey: widget.storeId,
                            ApiURL.onlyDeliveryChargeApiKey: 0,
                            ApiURL.userIdApiKey:
                                context.read<UserDetailsCubit>().getUserId(),
                            ApiURL.addressIdApiKey: _selectedAddress!.id!,
                          },
                        );
                      }
                    } else {
                      context
                          .read<CheckCartProductDeliverabilityCubit>()
                          .checkDeliverability(
                              storeId: widget.storeId,
                              addressId: context
                                  .read<GetUserCartCubit>()
                                  .getCartDetail()
                                  .selectedAddress!
                                  .id!);
                    }
                    // // Close the dialog or move to the next step
                    // Navigator.of(context).pop();
                  } else {
                    // Show error message if no address is selected
                    Utils.showSnackBar(
                        context: context, message: selectAddressWarningKey);
                    return;
                  }
                } else if (_currentStep == 2) {
                  if (context
                              .read<GetUserCartCubit>()
                              .getCartDetail()
                              .selectedPaymentMethod !=
                          null ||
                      (context
                                  .read<GetUserCartCubit>()
                                  .getCartDetail()
                                  .selectedPaymentMethod ==
                              null &&
                          context
                                  .read<GetUserCartCubit>()
                                  .getCartDetail()
                                  .useWalletBalance ==
                              true)) {
                    setState(() {
                      _currentStep = 3;
                    });
                  } else {
                    Utils.showSnackBar(
                        message: selectPaymentMethodOrWalletBalanceKey,
                        context: context);
                  }
                } else {
                  Cart cart = context.read<GetUserCartCubit>().getCartDetail();
                  Map<String, dynamic> orderPayload = {};
                  if (!checkForAttachments(cart)) {
                    Utils.showSnackBar(
                        message: productAttachmentWarningKey, context: context);
                    return;
                  } else {
                    orderPayload = await prepareOrderPayload(cart);
                  }

                  if (cart.cartProducts![0].type == digitalProductType &&
                      cart.emailAddress == null) {
                    openBottomShhetForDigitalProduct();

                    return;
                  }
                  // if (cart.cartProducts![0].type == digitalProductType &&
                  //     cart.emailAddress == null) {
                  //   Utils.showSnackBar(
                  //       message: enterEmailForCheckoutKey, context: context);
                  //   return;
                  // }
                  Map<String, dynamic> params = {
                    ApiURL.storeIdApiKey: widget.storeId,
                    ApiURL.deliveryChargeApiKey: cart.deliveryCharge,
                    if (cart.promoCode != null)
                      ApiURL.promoCodeIdApiKey: cart.promoCode!.id,
                    ApiURL.paymentMethodApiKey: cart.selectedPaymentMethod ==
                            null
                        ? ''
                        : cart.selectedPaymentMethod!.name == cashOnDeliveryKey
                            ? 'cod'
                            : cart.selectedPaymentMethod!.name!.toLowerCase(),
                    ApiURL.isWalletUsedApiKey:
                        cart.useWalletBalance == true ? 1 : 0,
                    ApiURL.walletBalanceUsedApiKey:
                        cart.useWalletBalance == true ? cart.walletAmount : 0,
                    ApiURL.orderNoteApiKey: cart.deliveryInstruction ?? '',
                    ApiURL.orderPaymentCurrencyCodeApiKey: context
                        .read<SettingsAndLanguagesCubit>()
                        .getSettings()
                        .systemSettings!
                        .currencySetting!
                        .code,
                    ApiURL.discountApiKey: cart.discount,
                  };
                  //below code will add attachments to the order payload
                  if (orderPayload.isNotEmpty) {
                    params.addEntries(orderPayload.entries);
                  }
                  if (cart.cartProducts![0].type == digitalProductType) {
                    params.addAll({ApiURL.emailApiKey: cart.emailAddress});
                  } else {
                    params.addAll(
                        {ApiURL.addressIdApiKey: cart.selectedAddress!.id});
                  }
                  if (cart.selectedPaymentMethod != null) {
                    if (cart.selectedPaymentMethod!.name == cashOnDeliveryKey) {
                      params.addAll({ApiURL.statusApiKey: receivedStatusType});
                    } else {
                      params.addAll({ApiURL.statusApiKey: awaitingStatusType});
                    }
                  } else if (cart.useWalletBalance == true) {
                    params.addAll({ApiURL.statusApiKey: receivedStatusType});
                  }
                  context.read<PlaceOrderCubit>().placeOrder(params: params);
                }
              },
            ),
          );
        }),
      );
    });
  }

  bool checkForAttachments(Cart cart) {
    for (var product in cart.cartProducts!) {
      if (product.productDetails![0].isAttachmentRequired == 1) {
        int id = product.cartProductType == comboType
            ? product.id!
            : product.productVariantId!;
        // Ensure the product_variant_id is present in attachments before adding
        if (cart.attachments == null || !cart.attachments!.containsKey(id)) {
          return false;
        }
      }
    }
    return true;
  }

  Future<Map<String, dynamic>> prepareOrderPayload(Cart cart) async {
    Map<String, dynamic> orderPayload = {};

    for (var product in cart.cartProducts!) {
      if (product.productDetails![0].isAttachmentRequired == 1) {
        int id = product.cartProductType == comboType
            ? product.id!
            : product.productVariantId!;
        // Ensure the product_variant_id is present in attachments before adding
        if (cart.attachments != null && cart.attachments!.containsKey(id)) {
          orderPayload["order_attachment[$id]"] =
              await dio.MultipartFile.fromFile(
            cart.attachments![id]!,
          );

          cart.attachments![id];
        } else {
          Utils.showSnackBar(
              message: productAttachmentWarningKey, context: context);
          break;
        }
      }
    }

    return orderPayload;
  }

  buildBody() {
    if (context.read<GetUserCartCubit>().getCartDetail().cartProducts != null &&
        context
            .read<GetUserCartCubit>()
            .getCartDetail()
            .cartProducts!
            .isNotEmpty) {
      return Column(
        children: [
          CustomDefaultContainer(
            child: CustomStepper(
              totalSteps: _stepLength,
              width: MediaQuery.of(context).size.width,
              curStep: _currentStep,
              startIndex: context
                          .read<GetUserCartCubit>()
                          .getCartDetail()
                          .cartProducts![0]
                          .type ==
                      digitalProductType
                  ? 2
                  : 1,
              stepCompleteColor: Theme.of(context).colorScheme.primary,
              currentStepColor: Theme.of(context).colorScheme.primary,
              inactiveColor: Theme.of(context).colorScheme.secondary,
              lineWidth: 2.0,
            ),
          ),
          const SizedBox(
            height: 12,
          ),
          Expanded(
              child: _currentStep == 1
                  ? BlocProvider(
                      create: (context) => DeleteAddressCubit(),
                      child: MyAddressScreen(
                        isFromCartScreen: true,
                        onInstAdded: onInstructionAdded,
                        onAddressSelection: (address) {
                          _selectedAddress = address;
                        },
                      ),
                    )
                  : _currentStep == 2
                      ? BlocBuilder<PaymentMethodCubit, PaymentMethodState>(
                          builder: (context, state) {
                            if (state is PaymentMethodFetchSuccess) {
                              return SelectPaymentScreen(
                                selectedPaymentMethod: _selectedPaymentMethod,
                                paymentMethodCubit:
                                    context.read<PaymentMethodCubit>(),
                                userDetailsCubit:
                                    context.read<UserDetailsCubit>(),
                              );
                            }
                            if (state is PaymentMethodFetchFailure) {
                              return ErrorScreen(
                                  text: state.errorMessage,
                                  onPressed: () {
                                    context
                                        .read<PaymentMethodCubit>()
                                        .fetchPaymentMethods();
                                  });
                            }
                            if (state is PaymentMethodFetchInProgress) {
                              return CustomCircularProgressIndicator(
                                  indicatorColor:
                                      Theme.of(context).colorScheme.primary);
                            }
                            return const SizedBox.shrink();
                          },
                        )
                      : FinalCartScreen(
                          onInstAdded: onInstructionAdded,
                          placeOrderState:
                              context.read<PlaceOrderCubit>().state,
                          storeId: widget.storeId,
                        ))
        ],
      );
    }
    return const SizedBox.shrink();
  }

  onInstructionAdded(int index) {
    setState(() {
      _currentStep = index;
    });
  }

  initiatePaymentMethod(int orderID, double finalTotal) async {
    var response;

    currentOrderID = orderID.toString();
    if (_selectedPaymentMethod != null) {
      if (_isLoading) return;
      setState(() {
        _isLoading = true;
      });
      if ([cashOnDeliveryKey, bankTransferKey]
          .contains(_selectedPaymentMethod!.name)) {
        doOnSuccess();
      } else {
        if (_selectedPaymentMethod!.name == razorpayKey) {
          doPaymentWithRazorpay(orderID, finalTotal);
          return;
        }
        if (_selectedPaymentMethod!.name == stripeKey) {
          doPaymentWithStripe(orderID, finalTotal);
          return;
        }
        if (_selectedPaymentMethod!.name == paystackKey) {
          response = await TransactionRepository().doPaymentWithPayStack(
              price: finalTotal,
              orderID: orderID.toString(),
              context: context,
              paystackId: _selectedPaymentMethod!.paystackKeyId!);
        }
        if (_selectedPaymentMethod!.name == paypalKey) {
          response = await TransactionRepository().doPaymentWithPaypal(
              price: finalTotal,
              orderID: orderID.toString(),
              type: 'order',
              context: context);
        }
        if (_selectedPaymentMethod!.name == phonepeKey) {
          response = await TransactionRepository().doPaymentWithPhonePe(
              context: context,
              price: finalTotal,
              environment: _selectedPaymentMethod!.phonepeMode!.toUpperCase(),
              appId: _selectedPaymentMethod!.phonepeSaltKey,
              merchantId: _selectedPaymentMethod!.phonepeMarchantId!,
              transactionType: defaultTransactionType,
              orderID: orderID.toString(),
              type: 'cart');
        }
        Utils.showSnackBar(message: response['message'], context: context);
        if (response['error'] == false) {
          doOnSuccess();
        } else if (response['error'] == true) {
          deleteOrder();
        }
        setState(() {
          _isLoading = false;
        });
      }
    } else {
      Utils.showSnackBar(message: selectPaymentMethodKey, context: context);
    }
  }

  doPaymentWithRazorpay(int orderId, double finalTotal) async {
    String userContactNumber = context.read<UserDetailsCubit>().getUserMobile();
    String userEmail = context.read<UserDetailsCubit>().getUserEmail();

    try {
      var response = await TransactionRepository()
          .createRazorpayOrder(orderID: orderId.toString(), amount: finalTotal);
      if (response['error'] == false) {
        var razorpayOptions = {
          'key': _selectedPaymentMethod!.razorpayKeyId!,
          'amount': finalTotal.toString(),
          'name': context.read<UserDetailsCubit>().getUserName(),
          'order_id': response[ApiURL.dataKey]['id'],
          'notes': {'order_id': orderId},
          'prefill': {
            'contact': userContactNumber,
            'email': userEmail,
          },
        };
        _razorpay = Razorpay();
        _razorpay!.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handlePaymentSuccess);
        _razorpay!.on(Razorpay.EVENT_PAYMENT_ERROR, _handlePaymentError);
        _razorpay!.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleExternalWallet);
        _razorpay!.open(razorpayOptions);
      }
    } catch (e) {
      Utils.showSnackBar(message: e.toString(), context: context);
    }
  }

  doPaymentWithStripe(int orderID, double finalTotal) async {
    var response = await StripeService.payWithPaymentSheet(
        amount: (finalTotal.round() * 100).toString(),
        currency: _selectedPaymentMethod!.stripeCurrencyCode!,
        from: 'order',
        awaitedOrderId: orderID.toString(),
        context: context);

    if (response.message == successTxnStatus) {
      doOnSuccess();
    } else if (response.status == pendingStatus ||
        response.status == capturedStatus) {
      doOnSuccess();
    } else {
      deleteOrder();
    }
  }

  updateOrder(String orderID, String status) async {}

  void _handlePaymentSuccess(PaymentSuccessResponse response) async {
    doOnSuccess();
  }

  void _handlePaymentError(PaymentFailureResponse response) {
    setState(() {
      _isLoading = false;
    });
    Utils.showSnackBar(message: response.error.toString(), context: context);
    deleteOrder();
  }

  void _handleExternalWallet(ExternalWalletResponse response) {}

  void addTransaction(
      {String? transactionId,
      required int orderID,
      required double price,
      required String status}) {
    context.read<AddTransactionCubit>().addTransaction(
      params: {
        ApiURL.userIdApiKey: context.read<UserDetailsCubit>().getUserId(),
        ApiURL.orderIdApiKey: orderID,
        ApiURL.transactionTypeApiKey: defaultTransactionType,
        ApiURL.typeApiKey: _selectedPaymentMethod!.name == cashOnDeliveryKey
            ? 'cod'
            : _selectedPaymentMethod!.name!.toLowerCase(),
        ApiURL.txnIdApiKey: transactionId,
        ApiURL.amountApiKey: price.toString(),
        ApiURL.statusApiKey: status,
        ApiURL.messageApiKey: 'waiting for payment',
      },
    );
  }

  doOnSuccess() async {
    await CartRepository()
        .clearCart()
        .then((value) => context.read<GetUserCartCubit>().resetCart(context));

    Utils.navigateToScreen(context, Routes.orderConfirmedScreen,
        arguments: {'orderId': currentOrderID, 'storeId': widget.storeId},
        replacePrevious: true);
  }

  void deleteOrder() {
    context
        .read<DeleteOrderCubit>()
        .deleteOrder(orderId: currentOrderID.toString(), context: context);
    currentOrderID = '';
  }

  void openBottomShhetForDigitalProduct() {
    final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
    TextEditingController _emailAddressController = TextEditingController();
    Utils.openModalBottomSheet(
        context,
        FilterContainerForBottomSheet(
            title: enterEmailKey,
            borderedButtonTitle: '',
            primaryButtonTitle: submitKey,
            borderedButtonOnTap: () {},
            primaryButtonOnTap: () {
              if (_formKey.currentState!.validate()) {
                context
                    .read<GetUserCartCubit>()
                    .addEmailAddress(_emailAddressController.text.trim());
                Future.delayed(const Duration(milliseconds: 500), () {
                  Navigator.of(context).pop();
                });
              }
            },
            content: Padding(
              padding: EdgeInsets.only(
                  bottom: MediaQuery.of(context).viewInsets.bottom),
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    CustomTextContainer(
                      textKey: enterEmailDescKey,
                      style: Theme.of(context).textTheme.bodySmall!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.67)),
                    ),
                    DesignConfig.defaultHeightSizedBox,
                    Form(
                      key: _formKey,
                      child: CustomTextFieldContainer(
                        hintTextKey: enterEmailKey,
                        autofocus: true,
                        keyboardType: TextInputType.emailAddress,
                        textEditingController: _emailAddressController,
                        validator: (value) =>
                            Validator.validateEmail(context, value),
                      ),
                    )
                  ],
                ),
              ),
            )),
        isScrollControlled: true,
        staticContent: true);
  }
}
