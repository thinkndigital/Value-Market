import 'dart:async';

import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';

import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/commons/widgets/primaryContainerWithBackground.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/profile/salesReport/blocs/salesListCubit.dart';
import 'package:eshopplus_seller/features/profile/salesReport/models/salesReport.dart';
import 'package:eshopplus_seller/features/profile/salesReport/widgets/salesInfoContainer.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../utils/utils.dart';

class SalesReportScreen extends StatefulWidget {
  const SalesReportScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => const SalesReportScreen();

  @override
  _SalesReportScreenState createState() => _SalesReportScreenState();
}

class _SalesReportScreenState extends State<SalesReportScreen> {
  final scrollController = ScrollController();
  List<SalesReport> orderlist = [];
  Map<String, String> apiParams = {};
  int currOffset = 0;
  String totalOrder = "0",
      grandTotal = "0",
      totalDeliveryCharge = "0",
      grandFinalTotal = "0";
  final fromDateController = TextEditingController();
  final toDateController = TextEditingController();
  DateTime? fromDate, toDate;
  String formattedDate = '';

  @override
  void initState() {
    super.initState();
    apiParams = {};
    setupScrollController(context);
    loadPage();
  }

  loadPage({bool isSetInitialPage = false}) {
    Map<String, String> parameter = {
      ApiURL.storeIdApiKey:
          context.read<StoresCubit>().getDefaultStore().id.toString(),
    };
    parameter.addAll(apiParams);

    BlocProvider.of<SalesListCubit>(context)
        .loadPosts(parameter, isSetInitial: isSetInitialPage);
  }

  setupScrollController(context) {
    scrollController.addListener(() {
      if (scrollController.position.atEdge) {
        if (scrollController.position.pixels != 0) {
          loadPage();
        }
      }
    });
  }

  @override
  void dispose() {
    fromDateController.dispose();
    toDateController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(
        titleKey: salesReportKey,
      ),
      body: BlocBuilder<SalesListCubit, SalesListState>(
        builder: (context, state) {
          if (state is SalesListFetchProgress && state.isFirstFetch) {
            return Utils.loadingIndicator();
          } else if (state is SalesListFetchFailure) {
            return ErrorScreen(
                text: state.errorMessage,
                onPressed: () => loadPage(isSetInitialPage: true));
          }
          return contentWidget(state);
        },
      ),
    );
  }

  contentWidget(SalesListState state) {
    orderlist = [];
    if (state is SalesListFetchProgress) {
      orderlist = state.oldArchiveList;
    } else if (state is SalesListFetchSuccess) {
      orderlist = state.specialityList;
      currOffset = state.currOffset;
      grandFinalTotal = state.grandFinalTotal;
      grandTotal = state.grandTotal;
      totalDeliveryCharge = state.totalDeliveryCharge;
      totalOrder = state.totalOrder;
    }
    return Column(
      children: <Widget>[
        const SizedBox(
          height: 12,
        ),
        buildReportContainer(),
        buildDatePickConainer(),
        SizedBox(
          height: appContentVerticalSpace,
        ),
        buildSalesReportList()
      ],
    );
  }

  buildReportContainer() {
    TextStyle textStyle = Theme.of(context)
        .textTheme
        .titleSmall!
        .copyWith(color: Theme.of(context).colorScheme.onPrimary);
    return PrimaryContainerWithBackground(
      child: Column(
        children: <Widget>[
          buildReportRow(totalOrdersKey, totalOrder, textStyle),
          const SizedBox(
            height: 8,
          ),
          buildReportRow(
              grandTotalKey,
              Utils.priceWithCurrencySymbol(
                  price: double.tryParse(grandTotal) ?? 0,context: context),
              textStyle),
          const SizedBox(
            height: 8,
          ),
          buildReportRow(
              deliveryChargeKey,
              Utils.priceWithCurrencySymbol(
                  price: double.tryParse(totalDeliveryCharge) ?? 0,
                  context: context),
              textStyle),
          Divider(
            color: Theme.of(context).colorScheme.onPrimary,
          ),
          const SizedBox(
            height: 8,
          ),
          buildReportRow(
              totalKey,
              Utils.priceWithCurrencySymbol(
                  price: double.tryParse(grandFinalTotal) ?? 0,
                  context: context),
              Theme.of(context)
                  .textTheme
                  .titleMedium!
                  .copyWith(color: Theme.of(context).colorScheme.onPrimary))
        ],
      ),
    );
  }

  buildReportRow(String title, String value, TextStyle textStyle) {
    return Padding(
      padding: EdgeInsetsDirectional.symmetric(
          horizontal: appContentHorizontalPadding),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          CustomTextContainer(
            textKey: title,
            style: textStyle,
          ),
          Text(
            value,
            style: textStyle,
          )
        ],
      ),
    );
  }

  buildDatePickConainer() {
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(
          horizontal: appContentHorizontalPadding, vertical: 8),
      color: Theme.of(context).colorScheme.primaryContainer,
      height: 125,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: <Widget>[
          Expanded(
            child: CustomTextFieldContainer(
              hintTextKey: addKey,
              textEditingController: fromDateController,
              labelKey: fromDateKey,
              textStyle: Theme.of(context).textTheme.bodySmall,
              suffixWidget: const Icon(Icons.date_range_outlined),
              onTap: () async {
                fromDate = await Utils.openDatePicker(
                    context, fromDate ?? DateTime.now());

                if (fromDate != null) {
                  formattedDate = displayDateFormat.format(fromDate!);

                  setState(() {
                    fromDateController.text =
                        formattedDate; //set output date to TextField value.
                  });
                } else {}
              },
            ),
          ),
          DesignConfig.defaultWidthSizedBox,
          Expanded(
            child: CustomTextFieldContainer(
              hintTextKey: addKey,
              textEditingController: toDateController,
              textStyle: Theme.of(context).textTheme.bodySmall,
              labelKey: toDateKey,
              readOnly: true,
              suffixWidget: const Icon(Icons.date_range_outlined),
              onTap: () async {
                toDate = await Utils.openDatePicker(
                    context, toDate ?? DateTime.now());

                if (toDate != null) {
                  formattedDate = displayDateFormat.format(toDate!);

                  setState(() {
                    toDateController.text =
                        formattedDate; //set output date to TextField value.
                  });
                } else {}
              },
            ),
          ),
          DesignConfig.defaultWidthSizedBox,
          GestureDetector(
            onTap: () {
              if (fromDateController.text.trim().isEmpty &&
                  toDateController.text.trim().isEmpty) {
                Utils.showSnackBar(message: selectDateKey);
                return;
              } else if (fromDateController.text.trim().isNotEmpty &&
                  toDateController.text.trim().isNotEmpty &&
                  toDate!.isBefore(fromDate!)) {
                Utils.showSnackBar(
                    message: selectValidTodateKey);
                return;
              } else {
                if (fromDateController.text.trim().isNotEmpty) {
                  apiParams["start_date"] =
                      fromDateController.text.split("-").reversed.join("-");
                }
                if (toDateController.text.trim().isNotEmpty) {
                  apiParams["end_date"] =
                      toDateController.text.split("-").reversed.join("-");
                }
                loadPage(isSetInitialPage: true);
              }
            },
            child: Container(
              width: 52,
              height: 48,
              margin:
                  const EdgeInsets.only(bottom: appContentHorizontalPadding),
              decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(borderRadius),
                  color: Theme.of(context).colorScheme.primary),
              child: Icon(
                Icons.send_outlined,
                size: 24,
                color: Theme.of(context).colorScheme.onPrimary,
              ),
            ),
          )
        ],
      ),
    );
  }

  buildSalesReportList() {
    return Expanded(
      child: ListView.separated(
        shrinkWrap: true,
        controller: scrollController,
        itemCount: orderlist.length,
        separatorBuilder: (context, index) => const SizedBox(
          height: 8,
        ),
        itemBuilder: (context, index) {
          if (index < orderlist.length) {
            return SalesInfoContainer(salesReport: orderlist[index]);
          } else {
            Timer(const Duration(milliseconds: 30), () {
              scrollController
                  .jumpTo(scrollController.position.maxScrollExtent);
            });
            return Utils.loadingIndicator();
          }
        },
      ),
    );
  }

  buildDownloadButton() {
    return CustomBottomButtonContainer(
        child: CustomRoundedButton(
      widthPercentage: 1,
      buttonTitle: downloadReportKey,
      showBorder: false,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          CustomTextContainer(
            textKey: downloadReportKey,
            style: Theme.of(context)
                .textTheme
                .titleMedium!
                .copyWith(color: Theme.of(context).colorScheme.onPrimary),
          ),
          const SizedBox(
            width: 8,
          ),
          const Icon(Icons.file_download_outlined)
        ],
      ),
    ));
  }
}
