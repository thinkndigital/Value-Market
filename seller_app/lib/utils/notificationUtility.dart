import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:isolate';
import 'dart:ui';
import 'package:dio/dio.dart';
import 'package:value_market_seller/features/notification/repositories/notificationRepository.dart';
import 'package:value_market_seller/features/auth/repositories/authRepository.dart';
import 'package:value_market_seller/features/mainScreen.dart';
import 'package:value_market_seller/features/orders/blocs/orderCubit.dart';
import 'package:value_market_seller/features/orders/repositories/orderRepository.dart';
import 'package:value_market_seller/features/profile/chat/blocs/getContactsCubit.dart';
import 'package:value_market_seller/features/profile/chat/blocs/getMessageCubit.dart';
import 'package:value_market_seller/features/profile/chat/models/chatMessage.dart';
import 'package:value_market_seller/features/profile/wallet/blocs/transactionCubit.dart';
import 'package:value_market_seller/main.dart';
import 'package:value_market_seller/core/routes/routes.dart';
import 'package:value_market_seller/commons/blocs/storesCubit.dart';
import 'package:value_market_seller/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_seller/features/orders/models/order.dart';
import 'package:value_market_seller/commons/models/product.dart';

import 'package:value_market_seller/commons/repositories/productRepository.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/api/apiService.dart';
import 'package:value_market_seller/core/configs/appConfig.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:value_market_seller/core/constants/hiveConstants.dart';

import 'package:value_market_seller/utils/utils.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:get/get.dart';
import 'package:hive/hive.dart';
import 'package:path_provider/path_provider.dart';

final ReceivePort backgroundMessageport = ReceivePort()
  ..listen(backgroundMessagePortHandler);

const String backgroundMessageIsolateName = 'fcm_background_msg_isolate';

void backgroundMessagePortHandler(message) {}

void stopBackgroundRingtone() {
  final port = IsolateNameServer.lookupPortByName(backgroundMessageIsolateName);
  if (port != null) {
    port.send("stopRingtone");
  } else {}
}

class NotificationUtility {
  static void initFirebaseState(BuildContext context) async {
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
        ApiURL.userIdApiKey: AuthRepository.getUserId(),
        ApiURL.fcmIdApiKey: fcmToken
      });
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
      required BuildContext context}) async {
    if (notificationData == null || notificationData.isEmpty) {
      return;
    }
    Map<String, dynamic> data = notificationData;
    String? type = data['type'];
    //if user get notification from other store theb it should load data from that store
    int storeId = int.tryParse(data['store_id']) ??
        context.read<StoresCubit>().getDefaultStore().id!;

    if (type != null) {
      if (type == ApiURL.messageKey &&
          chatController.showNotification(data['user_id'])) {
        Utils.navigateToScreen(context, Routes.chatScreen,
            replacePrevious: Get.currentRoute == Routes.chatScreen,
            arguments: {
              'id': int.parse(data['user_id']),
            });
      }
      if (type == 'wallet') {
        Utils.navigateToScreen(context, Routes.walletScreen);
      }
      if (type == 'order') {
        Map result = await OrderRepository().getOrders({
          ApiURL.storeIdApiKey: storeId,
          ApiURL.idApiKey: data['order_id'],
        });
        Order order =
            result[ApiURL.dataKey].map((e) => Order.fromJson(e)).toList().first;
        Utils.navigateToScreen(context, Routes.orderDetailsScreen,
                arguments: {'order': order})!
            .then((value) {
          if (storeId == context.read<StoresCubit>().getDefaultStore().id) {
            BlocProvider.of<OrdersCubit>(context).loadPosts({
              ApiURL.storeIdApiKey:
                  context.read<StoresCubit>().getDefaultStore().id
            }, isSetInitial: true);
          }
        });
      }
      if (type == 'regular_product') {
        ({
          List<Product> products,
          int total,
        }) result = await ProductRepository().getProducts(
          storeId: storeId,
          productId: int.parse(data['product_id']),
        );
        Utils.navigateToScreen(
          context,
          Routes.productDetailsScreen,
          arguments: {
            'product': result.products.first,
            'productsCubit': regularProductsCubit!
          },
          replacePrevious: Get.currentRoute == Routes.productDetailsScreen,
        );
      }
      if (type == 'combo_product') {
        ({
          List<Product> products,
          int total,
        }) result = await ProductRepository().getProducts(
          storeId: storeId,
          isComboProduct: true,
          productId: int.parse(data['product_id']),
        );

        Utils.navigateToScreen(
          context,
          Routes.productDetailsScreen,
          arguments: {
            'product': result.products.first,
            'productsCubit': comboProductsCubit!
          },
          replacePrevious: Get.currentRoute == Routes.productDetailsScreen,
        );
      }
      if (type == 'default') {}
      if (type == 'notification_url' && data['link'].isNotEmpty) {
        Utils.launchURL(data['link'].toString());
      }
      if (type == 'status_change') {
        Utils.navigateToScreen(context, Routes.mainScreen, replaceAll: true);
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

  static Future<void> setUpNotificationService(BuildContext context) async {
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
      Map<String, dynamic> data, BuildContext context) async {
    var type = data['type'];
    int storeId = int.tryParse(data['store_id']) ?? 0;
    if (type != null) {
      if (type == 'wallet' && Get.currentRoute == Routes.walletScreen) {
        context.read<TransactionCubit>().getTransaction(
            userId: context.read<UserDetailsCubit>().getUserId(),
            type: creditType);
      }

      //we will update  details only if the notification is from current store of seller app
      if (storeId == (context.read<StoresCubit>().getDefaultStore().id ?? 0)) {
        if (type == 'order') {
          context
              .read<OrdersCubit>()
              .addOrder(storeId, data[ApiURL.orderIdApiKey], context);
        }
        if (type == regularProductType) {
          Future.delayed(Duration.zero, () async {
            ({
              List<Product> products,
              int total,
            }) result = await ProductRepository().getProducts(
              storeId: storeId,
              productId: int.parse(data['product_id']),
            );

            regularProductsCubit!.updateProductDetails(result.products.first);
          });
        }
        if (type == 'combo_product') {
          Future.delayed(Duration.zero, () async {
            ({
              List<Product> products,
              int total,
            }) result = await ProductRepository().getProducts(
              storeId: storeId,
              isComboProduct: true,
              productId: int.parse(data['product_id']),
            );

            comboProductsCubit!.updateProductDetails(result.products.first);
          });
        }
      }
      if (type == 'status_change') {
        context.read<StoresCubit>().fetchStores();
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
    if (additionalData['type'] == ApiURL.messageKey) {
      if (chatController.showNotification(additionalData['user_id'])) {
        updateMessageCount(context, int.parse(additionalData['user_id']), [
          ChatMessage.fromJson({
            'from_id': int.parse(additionalData['user_id']),
            'to_id': AuthRepository.getUserId(),
            'body': body,
            'created_at': DateTime.now().toString(),
            'seen': 0
          })
        ]);
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
            androidPackageName, androidPackageName,
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
            androidPackageName, androidPackageName,
            importance: Importance.max,
            priority: Priority.high,
            largeIcon: FilePathAndroidBitmap(downloadedImagePath),
            styleInformation: bigPictureStyleInformation,
            ongoing: !dismissable,
            ticker: 'ticker');
      }
    } else {
      androidPlatformChannelSpecifics = AndroidNotificationDetails(
          androidPackageName, androidPackageName,
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
    final String filePath = directory.path;

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

  static void updateMessageCount(
    BuildContext context,
    int userId,
    List<ChatMessage> messages,
  ) {
    context
        .read<GetMessageCubit>()
        .updateUnreadCount(context.read<GetContactsCubit>(), userId, messages);
  }
}
