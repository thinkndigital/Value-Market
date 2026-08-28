import 'dart:async';

import 'package:value_market_customer/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/ui/profile/orders/blocs/deleteOrderCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/ui/profile/transaction/blocs/addTransactionCubit.dart';
import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';

import 'package:value_market_customer/commons/widgets/checkInterconnectiviy.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'package:webview_flutter_android/webview_flutter_android.dart';

// Import for iOS features.
import 'package:webview_flutter_wkwebview/webview_flutter_wkwebview.dart';

class PaypalWebviewScreen extends StatefulWidget {
  final String? url, from, msg, amt, orderId;
  final double? price;

  const PaypalWebviewScreen({
    Key? key,
    this.url,
    this.from,
    this.msg,
    this.amt,
    this.orderId,
    this.price,
  }) : super(key: key);
  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return BlocProvider(
      create: (context) => DeleteOrderCubit(),
      child: PaypalWebviewScreen(
        url: arguments['url'],
        from: arguments['from'],
        msg: arguments['msg'],
        amt: arguments['amt'],
        orderId: arguments['orderId'],
      ),
    );
  }

  @override
  State<StatefulWidget> createState() {
    return StatePaypalWebviewScreen();
  }
}

class StatePaypalWebviewScreen extends State<PaypalWebviewScreen> {
  String message = '';
  bool isloading = true;
  GlobalKey<ScaffoldState> scaffoldKey = GlobalKey<ScaffoldState>();
  DateTime? currentBackPressTime;
  bool isNetworkAvail = false;
  late final WebViewController _controller;

  @override
  void initState() {
    webViewInitiliased();
    checkNetwork();
    super.initState();
  }

  checkNetwork() async {
    isNetworkAvail = !await InternetConnectivity.isUserOffline();
    if (isNetworkAvail) {
      setState(() {
        isloading = false;
      });
    }
  }

  webViewInitiliased() {
    late final PlatformWebViewControllerCreationParams params;
    if (WebViewPlatform.instance is WebKitWebViewPlatform) {
      params = WebKitWebViewControllerCreationParams(
        allowsInlineMediaPlayback: true,
        mediaTypesRequiringUserAction: const <PlaybackMediaTypes>{},
      );
    } else {
      params = const PlatformWebViewControllerCreationParams();
    }

    final WebViewController controller =
        WebViewController.fromPlatformCreationParams(params);
    // #enddocregion platform_features

    controller
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0x00000000))
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {},
          onPageStarted: (String url) {},
          onPageFinished: (String url) {
            setState(
              () {
                isloading = false;
              },
            );
          },
          onWebResourceError: (WebResourceError error) {},
          onNavigationRequest: (NavigationRequest request) async {
            if (request.url.startsWith(ApiURL.paypalResponseUrl)) {
              if (mounted) {
                setState(() {
                  isloading = true;
                });
              }
              String responseurl = request.url;
              if (responseurl.contains('Failed') ||
                  responseurl.contains('failed')) {
                if (mounted) {
                  setState(() {
                    isloading = false;
                    message = 'Transaction Failed';
                  });
                }
                Timer(const Duration(seconds: 1), () {
                  Navigator.of(context).pop(true);
                });
              } else if (responseurl.contains('Completed') ||
                  responseurl.contains('completed') ||
                  responseurl.toLowerCase().contains('success')) {
                if (mounted) {
                  setState(() {
                    message = 'Transaction Successfull';
                  });
                }
                List<String> testdata = responseurl.split('&');
                for (String data in testdata) {
                  if (data.split('=')[0].toLowerCase() == 'tx' ||
                      data.split('=')[0].toLowerCase() == 'transaction_id') {
                    // userProvider.setCartCount('0');
                    if (widget.from == 'order') {
                      if (request.url.startsWith(ApiURL.paypalResponseUrl)) {
                        Navigator.of(context).pop(false);
                      } else {
                        String txid = data.split('=')[1];
                        addTransaction(
                          transactionId: txid,
                          orderID: widget.orderId!,
                          status: successKey,
                          price: widget.price!,
                        );
                      }
                    } else if (widget.from == walletTransactionType) {
                      Navigator.of(context).pop(false);
                    }

                    break;
                  }
                }
              }

              if (request.url.startsWith(ApiURL.paypalResponseUrl) &&
                  widget.orderId != null &&
                  (responseurl.contains('Canceled-Reversal') ||
                      responseurl.contains('Denied') ||
                      responseurl.contains('Failed'))) deleteOrder();
              return NavigationDecision.prevent;
            }

            return NavigationDecision.navigate;
          },
        ),
      )
      ..addJavaScriptChannel(
        'Toaster',
        onMessageReceived: (JavaScriptMessage message) {
          Utils.showSnackBar(message: message.message, context: context);
        },
      )
      ..loadHtmlString(
        widget.url!.toString(),
      );

    // #docregion platform_features
    if (controller.platform is AndroidWebViewController) {
      AndroidWebViewController.enableDebugging(true);
      (controller.platform as AndroidWebViewController)
          .setMediaPlaybackRequiresUserGesture(false);
    }
    // #enddocregion platform_features

    _controller = controller;
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
      child: BlocListener<DeleteOrderCubit, DeleteOrderState>(
        listener: (context, state) async {
          if (state is DeleteOrderFailure) {
            Utils.showSnackBar(message: state.errorMessage, context: context);
          }
          if (state is DeleteOrderSuccess) {
            Navigator.of(context).pop();
          }
        },
        child: SafeAreaWithBottomPadding(
          child: Scaffold(
            key: scaffoldKey,
            appBar: AppBar(
              titleSpacing: 0,
              leading: Builder(builder: (BuildContext context) {
                return Container(
                  margin: const EdgeInsets.all(10),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(borderRadius),
                    onTap: () {
                      DateTime now = DateTime.now();
                      if (currentBackPressTime == null ||
                          now.difference(currentBackPressTime!) >
                              const Duration(seconds: 2)) {
                        currentBackPressTime = now;
                        Utils.showSnackBar(
                            message:
                                "${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: paymentBackWarningKey)}\n ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: paymentExitInstructionKey)}",
                            context: context);
                        return;
                      }

                      if (widget.from == 'order' && widget.orderId != null) {
                        deleteOrder();
                      } else {
                        Navigator.pop(context, true);
                      }
                    },
                    child: Center(
                      child: Icon(Icons.keyboard_arrow_left,
                          size: 40,
                          color: Theme.of(context).colorScheme.secondary),
                    ),
                  ),
                );
              }),
              title: CustomTextContainer(
                textKey: context
                        .read<SettingsAndLanguagesCubit>()
                        .getSettings()
                        .systemSettings!
                        .appName ??
                    "",
              ),
            ),
            body: Stack(
              children: <Widget>[
                isNetworkAvail
                    ? WebViewWidget(controller: _controller)
                    : const SizedBox(),
                isloading
                    ? const Center(
                        child: CircularProgressIndicator(),
                      )
                    : const SizedBox(),
                message.trim().isEmpty
                    ? const SizedBox()
                    : Center(
                        child: Container(
                          color: Theme.of(context).colorScheme.primary,
                          padding: const EdgeInsets.all(5),
                          margin: const EdgeInsets.all(5),
                          child: Text(
                            message,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.onPrimary,
                              fontFamily: 'ubuntu',
                            ),
                          ),
                        ),
                      ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void addTransaction(
      {String? transactionId,
      required String orderID,
      required double price,
      required String status}) {
    context.read<AddTransactionCubit>().addTransaction(
      params: {
        ApiURL.userIdApiKey: context.read<UserDetailsCubit>().getUserId(),
        ApiURL.orderIdApiKey: orderID,
        ApiURL.transactionTypeApiKey: defaultTransactionType,
        ApiURL.typeApiKey: paypalKey,
        ApiURL.txnIdApiKey: transactionId,
        ApiURL.amountApiKey: price.toString(),
        ApiURL.statusApiKey: status,
        ApiURL.messageApiKey: 'waiting for payment',
      },
    );
  }

  Future<void> deleteOrder() async {
    Navigator.pop(context, true);
  }
}
