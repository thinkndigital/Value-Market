import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:isolate';
import 'dart:ui';
import 'package:dio/dio.dart';
import 'package:value_market_delivery_boy/core/constants/hiveConstants.dart';
import 'package:value_market_delivery_boy/core/routes/routes.dart';

import 'package:value_market_delivery_boy/commons/blocs/userDetailsCubit.dart';

import 'package:value_market_delivery_boy/core/api/apiService.dart';
import 'package:value_market_delivery_boy/features/auth/repositories/authRepository.dart';
import 'package:value_market_delivery_boy/features/notification/repositories/notificationRepository.dart';
import 'package:value_market_delivery_boy/features/mainScreen.dart';
import 'package:value_market_delivery_boy/core/configs/appConfig.dart';
import 'package:value_market_delivery_boy/utils/utils.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:get/get.dart';
import 'package:hive/hive.dart';
import 'package:path_provider/path_provider.dart';
import 'package:value_market_delivery_boy/core/api/apiEndPoints.dart';

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
      required BuildContext context}) {
    if (notificationData == null) {
      return;
    }
    Map<String, dynamic> data = notificationData;
    String? type = data['type'];
    if (type != null) {
      if (type == 'wallet') {}
      if (type == 'order') {
        if (Get.currentRoute == Routes.mainScreen) {
          MainScreen.mainScreenKey.currentState!.changeCurrentIndex(1);
        } else {
          Navigator.of(context).popUntil((route) => route.isFirst);
          MainScreen.mainScreenKey.currentState!.changeCurrentIndex(1);
        }
      }
      if (type == 'default') {}
      if (type == 'notification_url' && data['link'].isNotEmpty) {
        Utils.launchURL(data['link'].toString());
      }
    }
  }

  static final FlutterLocalNotificationsPlugin
      _flutterLocalNotificationsPlugin = FlutterLocalNotificationsPlugin();

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
    if ((Hive.box(settingsBoxKey).get(pushNotificationHiveKey) ?? true) ==
        true) {
      createLocalNotification(
          dismissable: true,
          imageUrl: image,
          title: title,
          body: body,
          payload: jsonEncode(additionalData));
    }
  }

  static onReceiveNotification(
      Map<String, dynamic> data, BuildContext context) async {
    var type = data['type'];
    if (type != null) {}
  }

  static void foregroundMessageListener(
      RemoteMessage message, BuildContext context) async {
    final additionalData = message.data;
    RemoteNotification notification = message.notification!;

    var title = notification.title ?? '';
    var body = notification.body ?? '';
    var image = message.data['image'] ?? '';

    displayNotification(title, body, image, additionalData);
    onReceiveNotification(message.data, context);
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
