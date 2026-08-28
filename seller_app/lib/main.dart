import 'package:curl_logger_dio_interceptor/curl_logger_dio_interceptor.dart';
import 'package:dio/dio.dart';
import 'package:eshopplus_seller/commons/blocs/allStoreCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/userDetailsCubit.dart';
import 'package:eshopplus_seller/commons/models/store.dart';
import 'package:eshopplus_seller/commons/repositories/settingsRepository.dart';
import 'package:eshopplus_seller/commons/repositories/storeRepository.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/hiveConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/core/theme/appTheme.dart';
import 'package:eshopplus_seller/features/auth/blocs/authCubit.dart';
import 'package:eshopplus_seller/features/auth/repositories/authRepository.dart';
import 'package:eshopplus_seller/features/orders/blocs/deliveryBoyCubit.dart';
import 'package:eshopplus_seller/features/orders/blocs/orderCubit.dart';
import 'package:eshopplus_seller/features/orders/repositories/orderRepository.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getContactsCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getMessageCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/models/chatMessage.dart';
import 'package:eshopplus_seller/features/profile/chat/widgets/chatController.dart';
import 'package:eshopplus_seller/firebase_options.dart';

import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:hive_flutter/adapters.dart';
import 'package:package_info_plus/package_info_plus.dart';
////////
//Version 1.0.6
///////

void main() async {
  await initializeApp();
}

String currentChatUserId = '';
late PackageInfo packageInfo;
Map<int, List<ChatMessage>> unreadMessages = {};
final RouteController routeController = Get.put(RouteController());
final ChatController chatController = Get.put(ChatController());
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();
late final Dio _dio;
Future<void> initializeApp() async {
  WidgetsFlutterBinding.ensureInitialized();
  packageInfo = await PackageInfo.fromPlatform();
  //Register the licence of font
  //If using google-fonts

  LicenseRegistry.addLicense(() async* {
    final license = await rootBundle.loadString('google_fonts/OFL.txt');
    yield LicenseEntryWithLineBreaks(['google_fonts'], license);
  });

  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarBrightness: Brightness.light,
      statusBarIconBrightness: Brightness.dark));

  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);

  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  await Hive.initFlutter();
  await Hive.openBox(authBoxKey);
  await Hive.openBox(settingsBoxKey);
  await Hive.openBox(chatBoxKey);

  runApp(MyApp());
}

class MyApp extends StatefulWidget {
  MyApp({Key? key}) : super(key: key) {
    _dio = Dio();

    // avoid using it in production or do it at your own risks!
    if (!kReleaseMode) {
      _dio.interceptors.add(CurlLoggerDioInterceptor(printOnSuccess: true));
    }
  }

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  Store defaultStore = Store.fromJson(Map.from({}));
  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider(create: (_) => StoresCubit(StoreRepository())),
        BlocProvider<AuthCubit>(create: (_) => AuthCubit(AuthRepository())),
        BlocProvider<DeliveryBoyCubit>(create: (_) => DeliveryBoyCubit()),
        BlocProvider<UserDetailsCubit>(create: (_) => UserDetailsCubit()),

        BlocProvider<GetMessageCubit>(
            create: (_) =>
                GetMessageCubit()), // we have taken these two cubits globally
        BlocProvider<GetContactsCubit>(
            create: (_) =>
                GetContactsCubit()), // so  that we can update its values from notification
        BlocProvider<SettingsAndLanguagesCubit>(
            create: (_) => SettingsAndLanguagesCubit(SettingsRepository())),
        BlocProvider<OrdersCubit>(
          create: (context) => OrdersCubit(OrderRepository()),
        ),
        BlocProvider(
          create: (context) => AllStoresCubit(StoreRepository()),
        ),
      ],
      child: Builder(builder: (context) {
        return BlocBuilder<SettingsAndLanguagesCubit,
            SettingsAndLanguagesState>(
          builder: (context, state) {
            final currentLanguage = context
                .watch<SettingsAndLanguagesCubit>()
                .getCurrentAppLanguage();
            return BlocBuilder<StoresCubit, StoresState>(
              builder: (context, state) {
                if (state is! StoresFetchSuccess) {
                  defaultStore = context
                      .read<AllStoresCubit>()
                      .getAllAllStores()
                      .firstWhere((element) => element.isDefaultStore == 1,
                          orElse: () => Store.fromJson(Map.from({})));
                } else {
                  defaultStore = context.read<StoresCubit>().getDefaultStore();
                }
                return GetMaterialApp(
                  navigatorKey: navigatorKey,
                  textDirection: currentLanguage.isThisRTL()
                      ? TextDirection.rtl
                      : TextDirection.ltr,
                  theme: AppTheme.getThemeData(context, defaultStore),
                  debugShowCheckedModeBanner: false,
                  getPages: Routes.getPages,
                  initialRoute: Routes.splashScreen,
                  routingCallback: (routing) {
                    if (routing != null) {
                      routeController.updateCurrentRoute(
                          routing.current, routing.previous);
                    }
                  },
                );
              },
            );
          },
        );
      }),
    );
  }
}

class RouteController extends GetxController {
  var currentRoute = ''.obs;
  var previousRoute = ''.obs;

  @override
  void onInit() {
    ever(currentRoute, (route) {});
    super.onInit();
  }

  void updateCurrentRoute(String route, String previousRoute) {
    currentRoute.value = currentRoute.value;
    currentRoute.value = route;
  }
}
