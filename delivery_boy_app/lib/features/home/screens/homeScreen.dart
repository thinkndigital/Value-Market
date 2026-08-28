import 'package:eshoppro_deliveryboy/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshoppro_deliveryboy/features/orders/blocs/recentOrderCubit.dart';
import 'package:eshoppro_deliveryboy/features/orders/models/parcel.dart';
import 'package:eshoppro_deliveryboy/features/home/screens/homeAppBar.dart';
import 'package:eshoppro_deliveryboy/features/orders/widgets/orderListWidget.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customRoundedButton.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../commons/blocs/userDetailsCubit.dart';
import '../../../utils/designConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../utils/utils.dart';
import '../../../core/theme/colors.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({Key? key}) : super(key: key);

  @override
  HomeScreenState createState() => HomeScreenState();
}

class HomeScreenState extends State<HomeScreen> {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const HomeAppBar(),
      body: BlocBuilder<RecentOrdersCubit, RecentOrdersState>(
        builder: (context, state) {
          if (state is RecentOrdersFetchInProgress) {
            return Center(
                child: CustomCircularProgressIndicator(
              indicatorColor: Theme.of(context).colorScheme.primary,
            ));
          } else if (state is RecentOrdersFetchFailure) {
            return noOrdersWidget();
/* return Utils.msgWithTryAgain(context, state.errorMessage, () {
loadList();
}); */
          } else if (state is RecentOrdersFetchSuccess) {
            return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  DesignConfig.defaultHeightSizedBox,
                  statusInfoWidget(state),
                  DesignConfig.defaultHeightSizedBox,
                  Padding(
                    padding:
                        const EdgeInsetsDirectional.only(start: 15, top: 5),
                    child: CustomTextContainer(
                      textKey: recentOrdersKey,
                      style: Theme.of(context)
                          .textTheme
                          .bodyLarge!
                          .copyWith(fontWeight: FontWeight.w400),
                    ),
                  ),
                  DesignConfig.defaultHeightSizedBox,
                  Expanded(
                      child: RefreshIndicator(
                          onRefresh: refreshList,
                          child: OrderListWidget(
                            parcelList: state.recentOrderList,
                            isFromHomeTab: true,
                            callback: (Parcel order) {
                              updateOrderItem(order);
                            },
                          )))
                ]);
          }
          return const SizedBox.shrink();
        },
      ),
    );
  }

  updateOrderItem(Parcel parcel) {
    if (context.read<RecentOrdersCubit>().state is RecentOrdersFetchSuccess) {
      RecentOrdersFetchSuccess successstate =
          context.read<RecentOrdersCubit>().state as RecentOrdersFetchSuccess;
      List<Parcel> parcellist = successstate.recentOrderList;
      int index = parcellist.indexWhere((element) => element.id == parcel.id);
      if (index != -1) {
        parcellist[index] = parcel;
        BlocProvider.of<RecentOrdersCubit>(context).setOldList(
            parcellist,
            successstate.awaiting,
            successstate.received,
            successstate.shipped,
            successstate.delivered,
            successstate.cancelled,
            successstate.returned,
            successstate.processed,
            successstate.total);
      }
    }
  }

  loadList() {
    BlocProvider.of<RecentOrdersCubit>(context).loadRecentOrders();
  }

  Future<void> refreshList() async {
    await Future.delayed(const Duration(seconds: 2), () {
      loadList();
    });
  }

  statusInfoWidget(RecentOrdersFetchSuccess state) {
    List statusinfo = [
      {
        "img": "bonus.svg",
        "lbl": totalOrdersKey,
        "value": state.total,
        "bgcolor": const Color(0xFFE4E2FF),
        "isCurrency": true
      },
      {
        "img": "balance.svg",
        "lbl": totalBalanceKey,
        "value":
            (context.read<UserDetailsCubit>().state as UserDetailsFetchSuccess)
                .userDetails
                .balance
                .toString(),
        "bgcolor": const Color(0xFFE9F0FF),
        "isCurrency": true
      },
      {
        "img": "bonus.svg",
        "lbl": totalBonusKey,
        "value":
            (context.read<UserDetailsCubit>().state as UserDetailsFetchSuccess)
                .userDetails
                .bonus
                .toString(),
        "bgcolor": const Color(0xFFE4E2FF),
        "isCurrency": true
      },
      {
        "img": "processed.svg",
        "lbl": processedKey,
        "value": state.processed,
        "bgcolor": const Color.fromRGBO(115, 106, 255, 0.12)
      },
      {
        "img": "shipped.svg",
        "lbl": shippedKey,
        "value": state.shipped,
        "bgcolor": const Color.fromRGBO(196, 77, 107, 0.12)
      },
      {
        "img": "delivered.svg",
        "lbl": deliveredKey,
        "value": state.delivered,
        "bgcolor": const Color.fromRGBO(115, 106, 255, 0.12)
      },
      {
        "img": "cancelled.svg",
        "lbl": cancelledKey,
        "value": state.cancelled,
        "bgcolor": const Color.fromRGBO(238, 28, 47, 0.12)
      },
      {
        "img": "returned.svg",
        "lbl": returnedKey,
        "value": state.returned,
        "bgcolor": const Color.fromRGBO(244, 137, 0, 0.12)
      },
    ];
    return SizedBox(
      height: 55,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: EdgeInsets.zero,
        separatorBuilder: (context, index) => const SizedBox(width: 12),
        itemCount: statusinfo.length,
        itemBuilder: (context, index) {
          Map item = statusinfo[index];
          return Container(
              decoration: DesignConfig.boxDecoration(white, 8),
              padding: const EdgeInsetsDirectional.only(
                  start: 8, top: 8, bottom: 8, end: 15),
              margin: EdgeInsetsDirectional.only(
                  start: index == 0 ? 12 : 0,
                  end: index == (statusinfo.length - 1) ? 12 : 0),
              child: statusWidget(
                  item["img"], item["lbl"], item["value"], item["bgcolor"],
                  isCurrency: item['isCurrency'] ?? false));
        },
      ),
    );
  }

  statusWidget(String img, String lbl, String value, Color bgcolor,
      {bool isCurrency = false}) {
    return Row(children: [
      CircleAvatar(
          backgroundColor: bgcolor,
          child: SvgPicture.asset(
            Utils.getImagePath(img),
          )),
      const SizedBox(width: 10),
      RichText(
          text: TextSpan(
              text: isCurrency
                  ? Utils.priceWithCurrencySymbol(
                      price: double.tryParse(value) ?? 0, context: context)
                  : value,
              style: Theme.of(context).textTheme.titleMedium!,
              children: [
            TextSpan(
              text:
                  "\n${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: lbl)}",
              style: Theme.of(context).textTheme.bodySmall!,
            ),
          ])),
    ]);
  }

  Widget noOrdersWidget() {
    return Stack(
      children: [
        Container(
          alignment: Alignment.center,
          padding: const EdgeInsetsDirectional.only(start: 15, end: 15),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SvgPicture.asset(
                Utils.getImagePath("noOrders.svg"),
              ),
              CustomTextContainer(
                  textKey: noOrdersAssignedKey,
                  textAlign: TextAlign.center,
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge!
                      .copyWith(color: Theme.of(context).colorScheme.primary)),
              CustomTextContainer(
                textKey: noOrdersAssignedSubtextKey,
                textAlign: TextAlign.center,
                style: Theme.of(context)
                    .textTheme
                    .bodyMedium!
                    .copyWith(fontWeight: FontWeight.w400),
              ),
              DesignConfig.defaultHeightSizedBox,
              CustomRoundedButton(
                height: 30,
                widthPercentage: 0.35,
                buttonTitle: retryKey,
                showBorder: false,
                onTap: () {
                  loadList();
                },
              )
            ],
          ),
        ),
        PositionedDirectional(
          start: 15,
          top: 15,
          child: CustomTextContainer(
            textKey: recentOrdersKey,
            style: Theme.of(context)
                .textTheme
                .bodyLarge!
                .copyWith(fontWeight: FontWeight.w400),
          ),
        ),
      ],
    );
  }
}
