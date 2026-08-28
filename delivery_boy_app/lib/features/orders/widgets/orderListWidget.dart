import 'dart:async';

import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/features/orders/models/parcel.dart';
import 'package:eshoppro_deliveryboy/features/mainScreen.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customLabelContainer.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customTextContainer.dart';
import 'package:eshoppro_deliveryboy/utils/datetimeUtils.dart';
import 'package:eshoppro_deliveryboy/utils/extensions/currencyExtension.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../core/routes/routes.dart';
import '../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../utils/utils.dart';

class OrderListWidget extends StatefulWidget {
  final List<Parcel> parcelList;
  final bool isLoading;
  final ScrollController? scrollController;
  final Function? callback;
  final bool isFromHomeTab;
  const OrderListWidget(
      {Key? key,
      required this.parcelList,
      this.scrollController,
      this.callback,
      this.isFromHomeTab = false,
      this.isLoading = false})
      : super(key: key);

  @override
  _OrderListWidgetState createState() => _OrderListWidgetState();
}

class _OrderListWidgetState extends State<OrderListWidget> {
  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      controller: widget.scrollController,
      padding: const EdgeInsetsDirectional.only(
          start: appContentHorizontalPadding,
          end: appContentHorizontalPadding,
          bottom: appContentHorizontalPadding),
      itemCount: widget.parcelList.length + (widget.isLoading ? 1 : 0),
      itemBuilder: (context, index) {
        if (index < widget.parcelList.length) {
          return orderInfoContainer(widget.parcelList[index]);
        } else {
          Timer(const Duration(milliseconds: 30), () {
            widget.scrollController!
                .jumpTo(widget.scrollController!.position.maxScrollExtent);
          });

          return Utils.loadingIndicator();
        }
      },
    );
  }

  orderInfoContainer(Parcel parcel) {
    TextStyle textStyle = Theme.of(context).textTheme.bodyMedium!.copyWith(
        color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8));
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.orderDetailsScreen,
          arguments: {
            'parcel': parcel,
            'callback': (Parcel parcel) {
              if (widget.callback != null) {
                widget.callback!(parcel);
              }
              MainScreen.mainScreenKey.currentState!
                  .updateScreen(widget.isFromHomeTab ? 1 : 0, parcel: parcel);
            }
          }),
      child: Container(
        margin: const EdgeInsetsDirectional.only(bottom: 8),
        width: MediaQuery.of(context).size.width,
        padding: EdgeInsets.symmetric(vertical: appContentVerticalSpace),
        decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primaryContainer,
            borderRadius: BorderRadius.circular(8)),
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: appContentHorizontalPadding),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.start,
                      children: [
                        CustomTextContainer(
                          textKey: orderIDKey,
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium!
                              .copyWith(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .secondary
                                      .withValues(alpha: 0.8)),
                        ),
                        Text(' : '),
                        Text(
                          parcel.orderId.toString().withOrderSymbol(),
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium!
                              .copyWith(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .secondary
                                      .withValues(alpha: 0.8)),
                        ),
                      ],
                    ),
                  ),
                  Container(
                      padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: 12, vertical: 2),
                      decoration: ShapeDecoration(
                        color: Utils.getOrderStatusTextAndColor(
                                parcel.activeStatus!)[1]
                            .withValues(alpha: 0.12),
                        shape: RoundedRectangleBorder(
                          side: BorderSide(
                              width: 0.50,
                              color: Utils.getOrderStatusTextAndColor(
                                  parcel.activeStatus!)[1]),
                          borderRadius: BorderRadius.circular(borderRadius),
                        ),
                      ),
                      child: CustomLabelContainer(
                        textKey: Utils.getOrderStatusTextAndColor(
                            parcel.activeStatus!)[0],
                        isFieldValueMandatory: false,
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                            color: Utils.getOrderStatusTextAndColor(
                                parcel.activeStatus!)[1]),
                      ))
                ],
              ),
            ),
            const Divider(
              thickness: 0.3,
            ),
            Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: appContentHorizontalPadding),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '#${parcel.id} : ${parcel.parcelName}',
                          style: textStyle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        if (parcel.username != null &&
                            parcel.username!.isNotEmpty)
                          Row(
                            children: <Widget>[
                              buildIcon(Icons.person_outline),
                              Text(
                                parcel.username ?? '',
                                style: textStyle,
                              )
                            ],
                          ),
                        Row(
                          children: <Widget>[
                            buildIcon(Icons.calendar_today_outlined),
                            Text(
                              DateTimeUtils.getFormattedDateTime(
                                  parcel.createdDate!,
                                  isReturnOnlyDate: true),
                              style: textStyle,
                            )
                          ],
                        ),
                        Row(
                          children: <Widget>[
                            buildIcon(Icons.payment_outlined),
                            Text.rich(
                              TextSpan(
                                children: [
                                  TextSpan(
                                      text:
                                          '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: paymentModeKey)} : ',
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium!),
                                  TextSpan(
                                      text: parcel.paymentMethod,
                                      style: textStyle),
                                ],
                              ),
                            )
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            )
          ],
        ),
      ),
    );
  }

  buildIcon(IconData iconData) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: Icon(iconData,
          size: 18, color: Theme.of(context).colorScheme.secondary),
    );
  }
}
