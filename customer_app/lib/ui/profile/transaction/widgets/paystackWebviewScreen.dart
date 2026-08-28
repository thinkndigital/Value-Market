import 'dart:async';

import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/ui/auth/repositories/authRepository.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/deleteOrderCubit.dart';
import 'package:eshop_plus/ui/profile/transaction/repositories/transactionRepository.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:webview_flutter/webview_flutter.dart';

class PaystackWebViewScreen extends StatefulWidget {
  final String authorizationUrl;
  final String callbackUrl;
  const PaystackWebViewScreen(
      {super.key, required this.authorizationUrl, required this.callbackUrl});
  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return BlocProvider(
      create: (context) => DeleteOrderCubit(),
      child: PaystackWebViewScreen(
        authorizationUrl: arguments['authorizationUrl'] as String,
        callbackUrl: arguments['callbackUrl'] as String,
      ),
    );
  }

  @override
  State<PaystackWebViewScreen> createState() => _PaystackWebViewScreenState();
}

class _PaystackWebViewScreenState extends State<PaystackWebViewScreen> {
  late final WebViewController _controller;
  DateTime? currentBackPressTime;
  @override
  void initState() {
    super.initState();
    String token = AuthRepository.getToken();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            final url = request.url;
            if (url.contains(widget.callbackUrl)) {
              Uri uri = Uri.parse(url);
              if (uri.queryParameters.containsKey(ApiURL.referenceApiKey)) {
                TransactionRepository()
                    .verifyTransaction(
                        refId: uri.queryParameters[ApiURL.referenceApiKey]!)
                    .then((value) {
                  if (value.status == 'success') {
                    Navigator.pop(context, 'success');
                    return NavigationDecision.prevent;
                  } else {
                    Navigator.of(context).pop(true);
                    return NavigationDecision.prevent;
                  }
                });
              }
              return NavigationDecision.prevent;
            }
            if (url.contains('success') || url.contains('completed')) {
              Navigator.pop(context, 'success'); // success case
              return NavigationDecision.prevent;
            } else if (url.contains('failure') ||
                url.contains('fail') ||
                url.contains('failed')) {
              Timer(const Duration(seconds: 1), () {
                Navigator.of(context).pop(true);
              });
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.authorizationUrl),
          headers: {'Authorization': "Bearer $token"});
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        DateTime now = DateTime.now();
        if (currentBackPressTime == null ||
            now.difference(currentBackPressTime!) >
                const Duration(seconds: 2)) {
          currentBackPressTime = now;
          Utils.showSnackBar(
              message:
                  "${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: paymentBackWarningKey)}\n ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: paymentExitInstructionKey)}",
              context: context);
        } else {
          deleteOrder();
          if (didPop) {
            return;
          }
        }
      },
      child: SafeAreaWithBottomPadding(
        child: Scaffold(
          appBar: AppBar(
              title: Text(context
                      .read<SettingsAndLanguagesCubit>()
                      .getSettings()
                      .systemSettings!
                      .appName ??
                  "")),
          body: WebViewWidget(controller: _controller),
        ),
      ),
    );
  }

  Future<void> deleteOrder() async {
    Navigator.pop(context, true);
  }
}
