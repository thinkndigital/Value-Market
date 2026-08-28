import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/ui/profile/transaction/models/paymentMethod.dart';
import 'package:eshop_plus/ui/profile/transaction/repositories/transactionRepository.dart';
import 'package:eshop_plus/ui/profile/transaction/widgets/stripeService.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

abstract class PaymentMethodState {}

class PaymentMethodInitial extends PaymentMethodState {}

class PaymentMethodFetchInProgress extends PaymentMethodState {}

class PaymentMethodFetchSuccess extends PaymentMethodState {
  final List<PaymentModel> paymentMethods;

  PaymentMethodFetchSuccess(this.paymentMethods);
}

class PaymentMethodFetchFailure extends PaymentMethodState {
  final String errorMessage;

  PaymentMethodFetchFailure(this.errorMessage);
}

class PaymentMethodCubit extends Cubit<PaymentMethodState> {
  final TransactionRepository _transactionRepository = TransactionRepository();

  PaymentMethodCubit() : super(PaymentMethodInitial());

  void fetchPaymentMethods() {
    emit(PaymentMethodFetchInProgress());

    _transactionRepository.fetchPaymentMethods().then((value) {
      List<PaymentModel> paymentMethodList = [];

      if (value.paypalMethod == 1) {
        paymentMethodList.addAll([
          PaymentModel(
            isSelected: false,
            name: paypalKey,
            image: 'paypal',
            paypalMode: value.paypalMode,
            paypalBusinessEmail: value.paypalBusinessEmail,
            paypalClientId: value.paypalClientId,
          )
        ]);
      }
      if (value.phonepeMethod == 1) {
        paymentMethodList.addAll([
          PaymentModel(
            isSelected: false,
            name: phonepeKey,
            image: 'phonepe',
            phonepeMode: value.phonepeMode,
            phonepeMarchantId: value.phonepeMarchantId,
            phonepeSaltIndex: value.phonepeSaltIndex,
            phonepeSaltKey: value.phonepeSaltKey,
          )
        ]);
      }
      if (value.razorpayMethod == 1) {
        paymentMethodList.addAll([
          PaymentModel(
            isSelected: false,
            name: razorpayKey,
            image: 'razorpay',
            razorpayMode: value.razorpayMode,
            razorpayKeyId: value.razorpayKeyId,
            razorpaySecretKey: value.razorpaySecretKey,
            razorpayWebhookSecretKey: value.razorpayWebhookSecretKey,
          )
        ]);
      }
      if (value.paystackMethod == 1) {
        paymentMethodList.addAll([
          PaymentModel(
            isSelected: false,
            name: paystackKey,
            image: 'paystack',
            paystackKeyId: value.paystackKeyId,
            paystackSecretKey: value.paystackSecretKey,
          )
        ]);
      }
      if (value.stripeMethod == 1) {
        paymentMethodList.addAll([
          PaymentModel(
            isSelected: false,
            name: stripeKey,
            image: 'stripe',
            stripePaymentMode: value.stripePaymentMode ?? 'test',
            stripePublishableKey: value.stripePublishableKey,
            stripeSecretKey: value.stripeSecretKey,
            stripeWebhookSecretKey: value.stripeWebhookSecretKey,
            stripeCurrencyCode: value.stripeCurrencyCode,
          )
        ]);
        StripeService.secret = value.stripeSecretKey;
        StripeService.init(
          value.stripePublishableKey,
          value.stripePaymentMode ?? 'test',
        );
      }
      if (value.directBankTransferMethod == 1) {
        paymentMethodList.addAll([
          PaymentModel(
              isSelected: false,
              name: bankTransferKey,
              image: 'bank_transfer',
              accountName: value.accountName,
              accountNumber: value.accountNumber,
              bankName: value.bankName,
              bankCode: value.bankCode,
              notes: value.notes)
        ]);
      }
      if (value.codMethod == 1) {
        paymentMethodList.addAll([
          PaymentModel(
            isSelected: false,
            name: cashOnDeliveryKey,
            image: 'cod_payment',
          )
        ]);
      }
      if (paymentMethodList.isNotEmpty)
        paymentMethodList[0].isSelected = true;
      else {
        emit(PaymentMethodFetchFailure(dataNotAvailableKey));
      }
      emit(PaymentMethodFetchSuccess(paymentMethodList));
    }).catchError((e) {
      emit(PaymentMethodFetchFailure(e.toString()));
    });
  }

  setPaymentMethod(PaymentModel paymentMethod) {
    List<PaymentModel> paymentMethodList =
        List.from((state as PaymentMethodFetchSuccess).paymentMethods);

    ///[Set defaultStore to 0 for all stores]
    for (var i = 0; i < paymentMethodList.length; i++) {
      paymentMethodList[i] = paymentMethodList[i].copyWith(isSelected: false);
    }

    //[Find the new default store index]
    final newDefaultStoreIdIndex = paymentMethodList
        .indexWhere((element) => element.name == paymentMethod.name);

    if (newDefaultStoreIdIndex != -1) {
      paymentMethodList[newDefaultStoreIdIndex] =
          paymentMethodList[newDefaultStoreIdIndex].copyWith(isSelected: true);
      emit(PaymentMethodFetchSuccess(paymentMethodList));
    }
  }

  List getPaymentMethodList() {
    if (state is PaymentMethodFetchSuccess) {
      return (state as PaymentMethodFetchSuccess).paymentMethods;
    }
    return [];
  }
}
