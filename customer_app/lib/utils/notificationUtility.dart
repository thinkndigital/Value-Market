import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/main.dart';
import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/core/constants/appConstants.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/categoty/cubits/categoryCubit.dart';
import 'package:value_market_customer/ui/profile/chat/screens/chatScreen.dart';
import 'package:value_market_customer/ui/profile/customerSupport/screens/customerSupportScreen.dart';
import 'package:value_market_customer/ui/profile/orders/blocs/orderCubit.dart';
import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/ui/profile/transaction/blocs/transactionCubit.dart';
import 'package:value_market_customer/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_customer/ui/profile/orders/models/order.dart';
import 'package:value_market_customer/commons/product/models/product.dart';
import 'package:value_market_customer/ui/notification/repositories/notificationRepository.dart';
import 'package:value_market_customer/ui/explore/productDetails/productDetailsScreen.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:get/get.dart';
import 'package:path_provider/path_provider.dart';

import '../ui/auth/repositories/authRepository.dart';

class NotificationUtility {
  static void initFirebaseState(BuildContext context) async {
    if (!context.read<UserDetailsCubit>().isGuestUser()) {
      String fcmToken = await AuthRepository.getFcmToken();
      if (context.read<UserDetailsCubit>().getuserDetails().fcmId != null &&
          context.read<UserDetailsCubit>().getuserDetails().fcmId!.isNotEmpty &&
          !context
              .read<UserDetailsCubit>()
              .getuserDetails()
              .fcmId!
              .contains(fcmToken) &&
          fcmToken.isNotEmpty) {
        AuthRepository().updateFcmId({
          ApiURL.userIdApiKey: AuthRepository.getUserDetails().id,
          ApiURL.fcmIdApiKey: fcmToken
        });
      }
    }
  }

  @pragma('vm:entry-point')
  static Future<void> onBackgroundMessage(RemoteMessage message) async {
    if (message.data.isNotEmpty) {
      NotificationRepository().addNotification(message.data);
    }
  }

  static void _onTapNotificationScreenNavigateCallback(
      {required Map<String, dynamic>? notificationData,
      required BuildContext context}) {
    if (notificationData == null) {
      return;
    }
    Map<String, dynamic> data = notificationData;
    String? type = data['type'];
    if (type != null) {
      if (type == messageNotificationType &&
          chatController.showNotification(data[ApiURL.userIdApiKey])) {
        currentChatUserId = data[ApiURL.userIdApiKey];

        Utils.navigateToScreen(
          context,
          Routes.chatScreen,
          preventDuplicates: false,
          replacePrevious: Get.currentRoute == Routes.chatScreen,
          arguments: {
            'id': int.parse(data[ApiURL.userIdApiKey]),
          },
        );
      }
      if (type == walletNotificationType) {
        Utils.navigateToScreen(context, Routes.walletScreen);
      }
      if (type == cartNotificationType) {
        Utils.navigateToScreen(context, Routes.cartScreen,
            arguments: {
              'storeId': int.tryParse(data[ApiURL.storeIdApiKey]) ??
                  context.read<StoresCubit>().getDefaultStore().id,
            },
            preventDuplicates: false,
            replacePrevious: Get.currentRoute == Routes.cartScreen);
      }
      if (type == orderNotificationType) {
        Utils.navigateToScreen(
          context,
          Routes.orderDetailsScreen,
          arguments: {
            'storeId': int.tryParse(data[ApiURL.storeIdApiKey]),
            'order': Order(id: int.parse(data[ApiURL.orderIdApiKey])),
            'orderId': int.parse(data[ApiURL.orderIdApiKey]),
          },
          preventDuplicates: false,
          replacePrevious: Get.currentRoute == Routes.orderDetailsScreen,
        );
      }
      if (type == defaultNotificationType) {}
      if (type == notificationUrlNotificationType && data['link'].isNotEmpty) {
        Utils.launchURL(data['link'].toString());
      }
      if (type == productsNotificationType) {
        Utils.navigateToScreen(
          context,
          Routes.productDetailsScreen,
          arguments: ProductDetailsScreen.buildArguments(
              storeId: int.tryParse(data[ApiURL.storeIdApiKey]),
              product: Product(id: int.parse(data['type_id'])),
              productIds: [int.parse(data['type_id'])]),
        );
      }

      if (type == categoriesNotificationType) {
        if (data['type_id'] != null) {
          Utils.navigateToScreen(context, Routes.categoryScreen,
                  preventDuplicates: false,
                  replacePrevious: Get.currentRoute == Routes.categoryScreen,
                  arguments: {
                'storeId': int.tryParse(data[ApiURL.storeIdApiKey]),
                'categoryId': int.parse(data['type_id']),
                'shouldPop': true
              })!
              .then((value) => context.read<CategoryCubit>().fetchCategories(
                  storeId: context.read<StoresCubit>().getDefaultStore().id!,
                  search: ''));
        }
      }
      if (type == ticketStatusNotificationType) {
        Utils.navigateToScreen(context, Routes.customerSupportScreen,
            replacePrevious: Get.currentRoute == Routes.customerSupportScreen,
            preventDuplicates: false);
      }
      if (type == ticketMessageNotificationType) {
        Utils.navigateToScreen(context, Routes.chatScreen,
            preventDuplicates: false,
            replacePrevious: Get.currentRoute == Routes.chatScreen,
            arguments: {
              'id': int.parse(data['ticket_id'].toString()),
              'isTicketChatScreen': true,
            });
      }
    }
  }

  static final FlutterLocalNotificationsPlugin
      _flutterLocalNotificationsPlugin = FlutterLocalNotificationsPlugin();

  //Ask notification permission here
  static Future<NotificationSettings> _getNotificationPermission() async {
    return await FirebaseMessaging.instance.requestPermission(
      alert: false,
      announcement: false,
      badge: false,
      carPlay: false,
      criticalAlert: false,
      provisional: false,
    );
  }

  Future<void> setUpNotificationService(BuildContext context) async {
    NotificationSettings notificationSettings =
        await FirebaseMessaging.instance.getNotificationSettings();
    //ask for permission
    if (notificationSettings.authorizationStatus ==
        AuthorizationStatus.notDetermined) {
      notificationSettings = await _getNotificationPermission();

      //if permission is provisionnal or authorised
      if (notificationSettings.authorizationStatus ==
              AuthorizationStatus.authorized ||
          notificationSettings.authorizationStatus ==
              AuthorizationStatus.provisional) {
        _initNotificationListener(context);
      }

      //if permission denied
    } else if (notificationSettings.authorizationStatus ==
        AuthorizationStatus.denied) {
      //If user denied then ask again
      notificationSettings = await _getNotificationPermission();
      if (notificationSettings.authorizationStatus ==
          AuthorizationStatus.denied) {
        return;
      }
    }
    _initNotificationListener(context);
  }

  static void _initNotificationListener(BuildContext context) {
    FirebaseMessaging.instance.setForegroundNotificationPresentationOptions(
      alert: true, // Required to display a heads up notification
      badge: true,
      sound: true,
    );
    FirebaseMessaging.onMessage.listen((remoteMessage) {
      foregroundMessageListener(remoteMessage, context);
    });
    FirebaseMessaging.onBackgroundMessage(onBackgroundMessage);
    FirebaseMessaging.onMessageOpenedApp.listen((remoteMessage) {
      onMessageOpenedAppListener(remoteMessage, context);
    });

    FirebaseMessaging.instance.getInitialMessage().then((value) {
      _onTapNotificationScreenNavigateCallback(
          notificationData: value?.data ?? {}, context: context);
    });

    if (!kIsWeb) {
      _initLocalNotification(context);
    }
  }

  static displayNotification(
      String title, String body, String image, Map additionalData) async {
    createLocalNotification(
        dismissable: true,
        imageUrl: image,
        title: title,
        body: body,
        payload: jsonEncode(additionalData));
  }

  static onReceiveNotification(
      Map<String, dynamic> data, BuildContext context) {
    var type = data['type'];
    if (type != null) {
      if (type == walletNotificationType &&
          Get.currentRoute == Routes.walletScreen) {
        context.read<TransactionCubit>().getTransaction(
            userId: context.read<UserDetailsCubit>().getUserId(),
            transactionType: walletTransactionType,
            type: creditType);
      }
    }
    if (type == orderNotificationType &&
        (Get.currentRoute == Routes.myOrderScreen ||
            Get.currentRoute == Routes.orderDetailsScreen)) {
      context
          .read<OrdersCubit>()
          .addOrder(int.parse(data[ApiURL.orderIdApiKey]), context);
    }
    if (type == ticketStatusNotificationType) {
      if (Get.currentRoute == Routes.customerSupportScreen)
        customerSupportScreenKey.currentState?.getTickets();
    }
    if (type == ticketMessageNotificationType) {
      if (Get.currentRoute == Routes.chatScreen &&
          currentChatUserId == data['ticket_id'].toString()) {
        chatScreenKey.currentState?.listenToMessages();
      }
    }
  }

  static void foregroundMessageListener(
      RemoteMessage message, BuildContext context) async {
    final additionalData = message.data;
    RemoteNotification notification = message.notification!;
    var title = notification.title ?? '';
    var body = notification.body ?? '';
    var image = message.data['image'] ?? '';
    if (message.data['type'] == 'message') {
      if (chatController.showNotification(message.data[ApiURL.userIdApiKey])) {
        displayNotification(title, body, image, additionalData);
      }
    } else {
      displayNotification(title, body, image, additionalData);
      onReceiveNotification(message.data, context);
    }
  }

  static void onMessageOpenedAppListener(
      RemoteMessage remoteMessage, BuildContext context) {
    _onTapNotificationScreenNavigateCallback(
        notificationData: remoteMessage.data, context: context);
  }

  static void _initLocalNotification(BuildContext context) async {
    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    const DarwinInitializationSettings initializationSettingsIOS =
        DarwinInitializationSettings();

    InitializationSettings initializationSettings =
        const InitializationSettings(
      android: initializationSettingsAndroid,
      iOS: initializationSettingsIOS,
    );
    _requestPermissionsForIos();
    await _flutterLocalNotificationsPlugin.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: (details) {
        _onTapNotificationScreenNavigateCallback(
            notificationData:
                Map<String, dynamic>.from(jsonDecode(details.payload ?? "")),
            context: context);
      },
    );
  }

  static Future<void> _requestPermissionsForIos() async {
    if (Platform.isIOS) {
      _flutterLocalNotificationsPlugin
          .resolvePlatformSpecificImplementation<
              IOSFlutterLocalNotificationsPlugin>()
          ?.requestPermissions();
    }
  }

  static Future<void> createLocalNotification(
      {required String title,
      required bool dismissable, //User can clear it
      required String body,
      required String imageUrl,
      required String payload}) async {
    late AndroidNotificationDetails androidPlatformChannelSpecifics;
    if (imageUrl.isNotEmpty) {
      final downloadedImagePath = await _downloadAndSaveFile(imageUrl);
      if (downloadedImagePath.isEmpty) {
        //If somwhow failed to download image
        androidPlatformChannelSpecifics = AndroidNotificationDetails(
            androidPackageName, //channel id
            'Local notification', //channel name
            importance: Importance.max,
            priority: Priority.high,
            ongoing: !dismissable,
            ticker: 'ticker');
      } else {
        var bigPictureStyleInformation = BigPictureStyleInformation(
            FilePathAndroidBitmap(downloadedImagePath),
            hideExpandedLargeIcon: false,
            contentTitle: title,
            htmlFormatContentTitle: true,
            summaryText: title,
            htmlFormatSummaryText: true);

        androidPlatformChannelSpecifics = AndroidNotificationDetails(
            androidPackageName, //channel id
            'Local notification', //channel name
            importance: Importance.max,
            priority: Priority.high,
            largeIcon: FilePathAndroidBitmap(downloadedImagePath),
            styleInformation: bigPictureStyleInformation,
            ongoing: !dismissable,
            ticker: 'ticker');
      }
    } else {
      androidPlatformChannelSpecifics = AndroidNotificationDetails(
          androidPackageName, //channel id
          'Local notification', //channel name
          importance: Importance.max,
          priority: Priority.high,
          ongoing: !dismissable,
          ticker: 'ticker');
    }
    const DarwinNotificationDetails iosNotificationDetails =
        DarwinNotificationDetails();

    var platformChannelSpecifics = NotificationDetails(
        android: androidPlatformChannelSpecifics, iOS: iosNotificationDetails);
    await _flutterLocalNotificationsPlugin
        .show(0, title, body, platformChannelSpecifics, payload: payload);
  }

  static Future<String> _downloadAndSaveFile(String url) async {
    final Directory directory = await getApplicationDocumentsDirectory();
    final String filePath = '${directory.path}/temp.jpg';

    try {
      await Api.download(
          url: url,
          cancelToken: CancelToken(),
          savePath: filePath,
          updateDownloadedPercentage: (value) {});

      return filePath;
    } catch (e) {
      return "";
    }
  }
}
