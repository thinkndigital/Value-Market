import 'package:eshoppro_deliveryboy/core/constants/appConstants.dart';
import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/features/collections/Widgets/cashTransactionPage.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/primaryContainerWithBackground.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../../utils/utils.dart';
import '../blocs/cashCollectionCubit.dart';
import '../../../commons/blocs/userDetailsCubit.dart';

import '../../../core/localization/labelKeys.dart';
import '../../../commons/widgets/customAppbar.dart';

import '../../../commons/widgets/customTextContainer.dart';

class CollectionScreen extends StatefulWidget {
  const CollectionScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => const CollectionScreen();
  @override
  CollectionScreenState createState() => CollectionScreenState();
}

class CollectionScreenState extends State<CollectionScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(
        titleKey: cashCollectionKey,
        showBackButton: false,
      ),
      body: BlocProvider(
        create: (context) => CashCollectionCubit(),
        child: Column(
          children: <Widget>[
            const SizedBox(
              height: 12,
            ),
            buildCollectionContainer(),
            buildTabBar(),
          ],
        ),
      ),
    );
  }

  buildCollectionContainer() {
    return PrimaryContainerWithBackground(
      child: Padding(
        padding: const EdgeInsetsDirectional.symmetric(
            horizontal: appContentHorizontalPadding),
        child: Column(
          children: <Widget>[
            CustomTextContainer(
                textKey: currentBalanceKey,
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium!
                    .copyWith(color: Theme.of(context).colorScheme.onPrimary)),
            const SizedBox(
              height: 4,
            ),
            BlocBuilder<UserDetailsCubit, UserDetailsState>(
              builder: (context, state) {
                return Column(
                  children: [
                    CustomTextContainer(
                        textKey: Utils.priceWithCurrencySymbol(
                            price: double.parse(context
                                    .read<UserDetailsCubit>()
                                    .getuserDetails()
                                    .cashReceived ??
                                "0"),
                            context: context),
                        style: Theme.of(context).textTheme.titleLarge!.copyWith(
                            color: Theme.of(context).colorScheme.onPrimary)),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  buildTabBar() {
    return Expanded(
      child: DefaultTabController(
        length: 2,
        child: Column(
          children: <Widget>[
            Container(
              height: 40,
              padding: const EdgeInsetsDirectional.only(
                  top: 5,
                  start: appContentHorizontalPadding,
                  end: appContentHorizontalPadding),
              color: Theme.of(context).colorScheme.primaryContainer,
              child: TabBar(
                dividerColor: Colors.transparent,
                indicatorColor: Theme.of(context).colorScheme.primary,
                labelColor: Theme.of(context).colorScheme.primary,
                unselectedLabelColor: Theme.of(context).colorScheme.secondary,
                indicatorSize: TabBarIndicatorSize.tab,
                labelStyle: Theme.of(context).textTheme.bodyLarge,
                tabs: [
                  buildTabLabel(riderCashKey),
                  buildTabLabel(cashCollectionKey)
                ],
              ),
            ),
            Expanded(
              child: TabBarView(children: [
                CashTransactionScreen(
                  type: riderCashType,
                ),
                BlocProvider(
                    create: (context) => CashCollectionCubit(),
                    child: CashTransactionScreen(
                      type: cashCollectionType,
                    )),
              ]),
            )
          ],
        ),
      ),
    );
  }

  Tab buildTabLabel(String title) {
    return Tab(
      text: context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: title),
    );
  }
}
