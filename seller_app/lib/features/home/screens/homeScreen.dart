import 'dart:io';

import 'package:eshopplus_seller/features/home/blocs/topSellingProductCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/categoryListCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getContactsCubit.dart';
import 'package:eshopplus_seller/features/home/blocs/getTotalDataCubit.dart';
import 'package:eshopplus_seller/features/home/blocs/mostSellingCategory.dart';
import 'package:eshopplus_seller/features/home/blocs/overviewDataCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/features/home/widgets/homeAppBar.dart';
import 'package:eshopplus_seller/features/home/widgets/counterSection.dart';
import 'package:eshopplus_seller/features/home/widgets/messagesSection.dart';
import 'package:eshopplus_seller/features/home/widgets/mostSellingCategorySection.dart';
import 'package:eshopplus_seller/features/home/widgets/overviewStaticticsSection.dart';
import 'package:eshopplus_seller/features/home/widgets/topSellingProductSection.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class HomeScreen extends StatefulWidget {
  static Widget getRouteInstance() => const HomeScreen();
  const HomeScreen({Key? key}) : super(key: key);

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  GlobalKey _counterkey = GlobalKey(),
      _chartKey = GlobalKey(),
      _categoryKey = GlobalKey(),
      _productKey = GlobalKey(),
      _messageKey = GlobalKey();
  @override
  void initState() {
    super.initState();
    checkForAppUpdate();
    Future.delayed(Duration.zero, () {
      context.read<GetContactsCubit>().getContactss();
    });
  }

  getApiData() {}
  checkForAppUpdate() {
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (context.read<SettingsAndLanguagesCubit>().state
          is SettingsAndLanguagesFetchSuccess) {
        if (context.read<SettingsAndLanguagesCubit>().isUpdateRequired()) {
          openUpdateDialog();
        }
      }
    });
  }

  openUpdateDialog() {
    Utils.openAlertDialog(context, barrierDismissible: false, onTapNo: () {
      exit(0); // Forcefully close the app
    }, onTapYes: () {
      Utils.rateApp(context);
    },
        message: forceUpdateTitleKey,
        content: forceUpdateDescKey,
        noLabel: exitKey,
        yesLabel: updateKey);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        appBar: const HomeAppBar(),
        body: RefreshIndicator(
          onRefresh: () async {
            _chartKey = GlobalKey();
            _counterkey = GlobalKey();
            _categoryKey = GlobalKey();
            _productKey = GlobalKey();
            _messageKey = GlobalKey();
          },
          child: ListView(
            children: <Widget>[
              BlocProvider(
                create: (context) => GetTotalDataCubit(),
                child: CounterSection(key: _counterkey),
              ),
              DesignConfig.smallHeightSizedBox,
              BlocProvider(
                create: (context) => OverviewDataCubit(),
                child: OverviewStaticticsSection(
                  key: _chartKey,
                ),
              ),
              BlocProvider(
                create: (context) => MostSellingCategoryCubit(),
                child: MostSellingCategorySection(
                  key: _categoryKey,
                ),
              ),
              DesignConfig.smallHeightSizedBox,
              MultiBlocProvider(
                providers: [
                  BlocProvider(
                    create: (context) => TopSellingProductCubit(),
                  ),
                  BlocProvider(
                    create: (context) => CategoryListCubit(),
                  ),
                ],
                child: TopSellingProductSection(
                  key: _productKey,
                ),
              ),
              MessagesSection(
                key: _messageKey,
              )
            ],
          ),
        ));
  }
}
