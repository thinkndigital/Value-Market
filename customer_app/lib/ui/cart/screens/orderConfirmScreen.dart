import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/ui/profile/orders/models/order.dart';
import 'package:eshop_plus/ui/mainScreen.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:lottie/lottie.dart';

class OrderConfirmScreen extends StatelessWidget {
  final String orderId;
  final int storeId;
  const OrderConfirmScreen(
      {Key? key, required this.orderId, required this.storeId})
      : super(key: key);
  static Widget getRouteInstance() => OrderConfirmScreen(
        orderId: Get.arguments['orderId'].toString(),
        storeId: Get.arguments['storeId']!,
      );

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsetsDirectional.all(appContentHorizontalPadding),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              Lottie.asset(Utils.getLottieAnimationPath('order_confirmed.json'),
                  width: 200, height: 200, repeat: false),
              const SizedBox(
                height: 24,
              ),
              CustomTextContainer(
                  textKey: orderConfirmedKey,
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge!
                      .copyWith(color: Theme.of(context).colorScheme.primary)),
              DesignConfig.smallHeightSizedBox,
              CustomTextContainer(
                  textKey: yourOrderWillDeliveredSoonKey,
                  style: Theme.of(context).textTheme.bodyLarge),
              CustomTextContainer(
                  textKey:
                      '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: orderIDKey)} : $orderId',
                  style: Theme.of(context).textTheme.bodyLarge),
              const SizedBox(
                height: 24,
              ),
              CustomRoundedButton(
                  widthPercentage: 1.0,
                  buttonTitle: trackYourOrderKey,
                  showBorder: false,
                  onTap: () =>
                      Utils.navigateToScreen(context, Routes.orderDetailsScreen,
                          arguments: {
                            'order': Order(id: int.parse(orderId)),
                            'orderId': int.parse(orderId),
                            'storeId': storeId
                          },
                          replacePrevious: true)),
                          DesignConfig.defaultHeightSizedBox,
              CustomTextButton(
                  buttonTextKey: backToHomeKey,
                  textStyle: Theme.of(context).textTheme.bodyLarge,
                  onTapButton: () {
                    mainScreenKey?.currentState?.changeCurrentIndex(0);
                    Get.until(
                      (route) => route.settings.name == Routes.mainScreen,
                    );
                  }),
            ],
          ),
        ),
      ),
    );
  }
}
