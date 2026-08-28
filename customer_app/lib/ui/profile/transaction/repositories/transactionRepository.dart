import 'dart:convert';
import 'dart:io';

import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_customer/ui/profile/transaction/models/paymentMethod.dart';
import 'package:value_market_customer/ui/profile/transaction/models/paystackModel.dart';
import 'package:value_market_customer/utils/extensions/encodingExtension.dart';
import 'package:value_market_customer/ui/profile/transaction/widgets/stripeService.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:phonepe_payment_sdk/phonepe_payment_sdk.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import '../models/transaction.dart';

class TransactionRepository {
  Future<({List<Transaction> transactions, int total, double balance})>
      getTransactions({
    required int userId,
    int? offset,
    String? transactionType,
    String? type,
  }) async {
    String url = ApiURL.getTransactions;
    try {
      Map<String, dynamic> queryParameters = {
        ApiURL.userIdApiKey: userId,
        ApiURL.limitApiKey: limit,
        ApiURL.transactionTypeApiKey: transactionType ?? defaultTransactionType,
        ApiURL.typeApiKey: type,
        ApiURL.offsetApiKey: offset ?? 0,
      };
      if (transactionType == walletTransactionType && type == debitType) {
        url = ApiURL.getWithdrawalRequest;
      }
      if (transactionType == defaultTransactionType) {
        queryParameters.remove(ApiURL.typeApiKey);
      }
      final result = await Api.get(
          url: url, useAuthToken: true, queryParameters: queryParameters);

      return (
        transactions: transactionType == walletTransactionType &&
                type == debitType
            ? ((result[ApiURL.dataKey] ?? []) as List)
                .map((transaction) =>
                    Transaction.fromWithdrawJson(Map.from(transaction ?? {})))
                .toList()
            : ((result[ApiURL.dataKey] ?? []) as List)
                .map((transaction) =>
                    Transaction.fromJson(Map.from(transaction ?? {})))
                .toList(),
        total: int.parse((result[ApiURL.totalKey] ?? 0).toString()),
        balance: double.parse((result['balance'] ?? 0).toString()),
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  createRazorpayOrder({required String orderID, required double amount}) async {
    try {
      final result = await Api.post(body: {
        ApiURL.orderIdApiKey: orderID,
        ApiURL.amountApiKey: amount,
      }, url: ApiURL.razorpayCreateOrder, useAuthToken: true);

      return result;
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<({Transaction transaction, String message})> sendWithdrawalRequest(
      {required Map<String, dynamic> params}) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.sendWithdrawalRequest, useAuthToken: true);

      return (
        transaction: Transaction.fromWithdrawJson(result[ApiURL.dataKey] ?? {}),
        message: result['message'].toString()
      );
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<bool> addTransaction({
    required Map<String, dynamic> params,
  }) async {
    try {
      final result = await Api.post(
          body: params, url: ApiURL.addTransaction, useAuthToken: true);

      return result['error'] == false;
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<PaymentMethod> fetchPaymentMethods() async {
    try {
      final result = await Api.get(
          url: ApiURL.getSettings,
          queryParameters: {ApiURL.typeApiKey: 'payment_method'},
          useAuthToken: true);
      return PaymentMethod.fromJson(
          Map.from(result[ApiURL.dataKey]['payment_method'] ?? {}));
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  doPaymentWithRazorpay(
      {required BuildContext context,
      required String orderID,
      required Razorpay razorpay,
      required String razorpayId,
      required double price}) async {
    try {
      String userContactNumber =
          context.read<UserDetailsCubit>().getUserMobile();
      String userEmail = context.read<UserDetailsCubit>().getUserEmail();

      var response = await TransactionRepository()
          .createRazorpayOrder(orderID: orderID, amount: price);
      if (response['error'] == false) {
        var razorpayOptions = {
          'key': razorpayId,
          'amount': price.toString(),
          'name': context.read<UserDetailsCubit>().getUserName(),
          'order_id': response[ApiURL.dataKey]['id'],
          'notes': {'order_id': orderID},
          'prefill': {
            'contact': userContactNumber,
            'email': userEmail,
          },
        };

        razorpay.open(razorpayOptions);
      } else {
        return {
          'error': true,
          'message': '',
          'status': false,
        };
      }
      return response;
    } catch (e) {
      Utils.showSnackBar(message: e.toString(), context: context);
    }
  }

  Future<Map<String, dynamic>> doPaymentWithStripe({
    required double price,
    required String currencyCode,
    required String paymentFor,
    required BuildContext context,
    String? orderId,
  }) async {
    try {
      StripeTransactionResponse stripeResponse = await payWithStripe(
          currencyCode: currencyCode,
          stripeTransactionAmount: price,
          paymentFor: paymentFor,
          orderId: orderId,
          context: context);
      Map<String, dynamic> response = {
        'error': true,
        'status': false,
        'message': defaultErrorMessageKey
      };

      if (stripeResponse.status == 'succeeded') {
        response['error'] = false;
        response['status'] = true;
        response['message'] = transactionSuccessfulKey;
      } else {
        response['error'] = true;
        response['status'] = false;
        response['message'] = stripeResponse.message;
      }

      return response;
    } catch (e) {
      return {
        'error': true,
        'message': e.toString(),
        'status': false,
      };
    }
  }

  //This method is used to pay with stripe
  static Future<StripeTransactionResponse> payWithStripe({
    required String currencyCode,
    required double stripeTransactionAmount,
    required String paymentFor,
    required BuildContext context,
    String? orderId,
  }) async {
    try {
      var response = await StripeService.payWithPaymentSheet(
          amount: (stripeTransactionAmount.round() * 100).toString(),
          currency: currencyCode,
          from: paymentFor,
          awaitedOrderId: orderId,
          context: context);

      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, dynamic>> doPaymentWithPayStack(
      {required double price,
      required String paystackId,
      required String orderID,
      required BuildContext context}) async {
    try {
      PaystackModel? paystackModel = await getPaystackAuthorizationUrl(price);

      if (paystackModel != null) {
        final result = await Utils.navigateToScreen(
            context, Routes.paystackWebviewScreen,
            arguments: {
              'authorizationUrl': paystackModel.authorizationUrl,
              'callbackUrl': paystackModel.callbackUrl,
            });
        if (result == 'success') {
          return {
            'error': false,
            'message': transactionSuccessfulKey,
            'status': true,
          };
        } else {
          return {
            'error': true,
            'message': transactionFailedKey,
            'status': true,
          };
        }
      } else {
        return {
          'error': true,
          'message': defaultErrorMessageKey,
          'status': false,
        };
      }
    } catch (e) {
      return {
        'error': true,
        'message': e.toString(),
        'status': false,
      };
    }
  }

  Future<Map<String, dynamic>> doPaymentWithPaypal(
      {required double price,
      required String orderID,
      required String type,
      required BuildContext context}) async {
    try {
      var paypalLink = await getPaypalPaymentGatewayLink(params: {
        ApiURL.amountApiKey: price,
        ApiURL.userIdApiKey: context.read<UserDetailsCubit>().getUserId(),
        ApiURL.orderIdApiKey: orderID
      });

      if (paypalLink != '') {
        var response = await Utils.navigateToScreen(
            context, Routes.paypalWebviewScreen,
            arguments: {
              'url': paypalLink,
              'from': type,
              'orderId': orderID,
              'price': price,
            });
        if (response == true) {
          return {
            'error': true,
            'message': transactionFailedKey,
            'status': false,
          };
        }
      }
      return {
        'error': false,
        'message': transactionSuccessfulKey,
        'status': true
      };
    } catch (e) {
      Utils.showSnackBar(message: e.toString(), context: context);
      return {
        'error': true,
        'message': e.toString(),
        'status': false,
      };
    }
  }

  //This method is used to get paypal payment gateway link
  static Future<String> getPaypalPaymentGatewayLink({
    required Map<String, dynamic> params,
  }) async {
    try {
      final result = await Api.getHtmlContent(
          url: ApiURL.getPaypalLink,
          queryParameters: params,
          useAuthToken: true);
      return result;
    } catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  Future<Map<String, dynamic>> doPaymentWithPhonePe({
    required double price,
    required String environment,
    required String? appId,
    required String merchantId,
    String? orderID,
    required BuildContext context,
    String transactionType = defaultTransactionType,
    required String type,
  }) async {
    try {
      //bool isPhonePeInitilized = await PhonePePaymentSdk.init(environment.toUpperCase(), appId ?? '', merchantId, true);
      String appPkgName =
          Platform.isAndroid ? androidPackageName : iosPackageName;

      Map<String, dynamic> params = {
        ApiURL.userIdApiKey: context.read<UserDetailsCubit>().getUserId(),
        ApiURL.amountApiKey: price.toString(),
        ApiURL.statusApiKey: awaitingStatusType,
        ApiURL.messageApiKey: 'waiting for payment',
        ApiURL.paymentMethodApiKey: phonepeKey,
        ApiURL.transactionTypeApiKey: transactionType,
        ApiURL.typeApiKey:
            transactionType == defaultTransactionType ? phonepeKey : creditType
      };
      if (orderID != null) {
        params.addAll({ApiURL.txnIdApiKey: orderID});
        params.addAll({ApiURL.orderIdApiKey: orderID});
      }
      final bool transactionAdded = await addTransaction(params: params);

      //first create transaction with awaiting state, then do payment
      //if (!transactionAdded || !isPhonePeInitilized) {
      if (!transactionAdded) {
        return {
          'error': true,
          'message': phonePePaymentFailedKey,
          'status': false,
        };
      }

      final phonePeDetails = await getPhonePeDetails(
        type: type,
        mobile: context.read<UserDetailsCubit>().getUserMobile().trim().isEmpty
            ? context.read<UserDetailsCubit>().getUserId().toString()
            : context.read<UserDetailsCubit>().getUserMobile(),
        userId: context.read<UserDetailsCubit>().getUserId().toString(),
        amount: price.toString(),
        orderId: orderID,
        transationId: orderID ?? '',
      );

      /* final response = await PhonePePaymentSdk.startTransaction(
          jsonEncode(phonePeDetails[ApiURL.dataKey]['payload'] ?? {}).toBase64,
          phonePeDetails[ApiURL.dataKey]['payload']['redirectUrl'] ?? '',
          phonePeDetails[ApiURL.dataKey]['checksum'] ?? '',
          Platform.isAndroid ? androidPackageName : iosPackageName); */

      bool isPhonePeInitilized = await PhonePePaymentSdk.init(
          phonePeDetails[ApiURL.dataKey]['environment'],
          merchantId,
          phonePeDetails[ApiURL.dataKey]['flowId'],
          phonePeDetails[ApiURL.dataKey]['enableLogging']);
      if (!isPhonePeInitilized) {
        return {
          'error': true,
          'message': phonePePaymentFailedKey,
          'status': false,
        };
      }
      Map<String, dynamic> payload = {
        'merchantOrderId': phonePeDetails[ApiURL.dataKey]['request']
            ['merchantOrderId'],
        'orderId': phonePeDetails[ApiURL.dataKey]['request']['merchantOrderId'],
        'merchantId': merchantId,
        'token': phonePeDetails[ApiURL.dataKey]['request']['token'],
        'paymentMode': phonePeDetails[ApiURL.dataKey]['request']['paymentMode'],
      };

      final response = await PhonePePaymentSdk.startTransaction(
          jsonEncode(payload), appPkgName);

      if (response != null) {
        String status = response['status'].toString();

        if (status == 'SUCCESS') {
          return {
            'error': false,
            'message': phonePePaymentSuccessKey,
            'status': true
          };
        }
      }

      return {
        'error': true,
        'message': phonePePaymentFailedKey,
        'status': false,
      };
    } catch (e) {
      return {
        'error': true,
        'message': e.toString(),
        'status': false,
      };
    }
  }

  static Future<Map> getPhonePeDetails({
    required String userId,
    required String type,
    required String mobile,
    String? amount,
    String? orderId,
    required String transationId,
  }) async {
    try {
      var responseData = await Api.post(
        url: ApiURL.phonepeApp,
        body: {
          ApiURL.typeApiKey: type,
          ApiURL.mobileApiKey: mobile,
          if (amount != null) ApiURL.amountApiKey: amount,
          ApiURL.orderIdApiKey: orderId ?? '',
          ApiURL.transactionIdApiKey: transationId,
          ApiURL.userIdApiKey: userId
        },
        useAuthToken: true,
      );
      return responseData;
    } on Exception catch (e) {
      if (e is ApiException) {
        throw ApiException(e.toString());
      } else {
        throw ApiException(defaultErrorMessageKey);
      }
    }
  }

  //create getPaystackWebviewModel with passing amount param
  Future<PaystackModel?> getPaystackAuthorizationUrl(
      final double amount) async {
    try {
      final result = await Api.get(
          url: ApiURL.paystackWebviewApi,
          queryParameters: {ApiURL.amountApiKey: amount},
          useAuthToken: true);

      return PaystackModel.fromJson(Map.from(result[ApiURL.dataKey] ?? {}));
    } catch (e) {
      return null;
    }
  }

  Future<({String status, String message})> verifyTransaction(
      {required String refId}) async {
    try {
      final result = await Api.get(
          url: ApiURL.handlePaystackCallbackApi,
          queryParameters: {ApiURL.referenceApiKey: refId},
          useAuthToken: true);
      return (
        status: result[ApiURL.dataKey]['status'] as String,
        message: result[ApiURL.dataKey]['gateway_response'].toString()
      );
    } catch (e) {
      return (status: 'fail', message: defaultErrorMessageKey);
    }
  }
}
