import 'package:eshoppro_deliveryboy/core/constants/hiveConstants.dart';
import 'package:eshoppro_deliveryboy/core/routes/routes.dart';
import 'package:eshoppro_deliveryboy/core/theme/appTheme.dart';
import 'package:eshoppro_deliveryboy/features/auth/blocs/authCubit.dart';
import 'package:eshoppro_deliveryboy/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshoppro_deliveryboy/features/auth/repositories/authRepository.dart';
import 'package:eshoppro_deliveryboy/commons/repositories/settingsRepository.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

import 'package:hive_flutter/adapters.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'commons/blocs/userDetailsCubit.dart';
import '../firebase_options.dart';
import '../utils/session.dart';

late PackageInfo packageInfo;
late SharedPreferences pref;
late Session session;
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

////////
//Version 1.0.6
///////
void main() async {
  await initializeApp();
}

Future<void> initializeApp() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  pref = await SharedPreferences.getInstance();
  session = Session(pref);
  packageInfo = await PackageInfo.fromPlatform();

  LicenseRegistry.addLicense(() async* {
    final license = await rootBundle.loadString('google_fonts/OFL.txt');
    yield LicenseEntryWithLineBreaks(['google_fonts'], license);
  });

  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarBrightness: Brightness.light,
      statusBarIconBrightness: Brightness.dark,
    ),
  );

  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);

  await Hive.initFlutter();
  await Hive.openBox(authBoxKey);
  await Hive.openBox(settingsBoxKey);

  runApp(MyApp());
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => SettingsAndLanguagesCubit(SettingsRepository()),
        ),
        BlocProvider(create: (context) => AuthCubit(AuthRepository())),
        BlocProvider<UserDetailsCubit>(create: (_) => UserDetailsCubit()),
      ],
      child: Builder(
        builder: (context) {
          return BlocBuilder<
            SettingsAndLanguagesCubit,
            SettingsAndLanguagesState
          >(
            builder: (context, state) {
              final currentLanguage = context
                  .watch<SettingsAndLanguagesCubit>()
                  .getCurrentAppLanguage();
              return GetMaterialApp(
                navigatorKey: navigatorKey,
                textDirection: currentLanguage.isThisRTL()
                    ? TextDirection.rtl
                    : TextDirection.ltr,
                theme: AppTheme.getThemeData(context),
                debugShowCheckedModeBanner: false,
                getPages: Routes.getPages,
                initialRoute: Routes.splashScreen,
                routingCallback: (routing) {},
                builder: (context, child) {
                  return Center(
                    child: Directionality(
                      textDirection: currentLanguage.isThisRTL()
                          ? TextDirection.rtl
                          : TextDirection.ltr,
                      child: child!,
                    ),
                  );
                },
              );
            },
          );
        },
      ),
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
