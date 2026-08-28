import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/downloadFileCubit.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/productFileDownloadLinkCubit.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/updateOrderCubit.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/ui/profile/orders/widgets/orderStatusItem.dart';
import 'package:eshop_plus/ui/profile/orders/widgets/setProductRatingContainer.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customImageWidget.dart';

import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';

import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/dateTimeUtils.dart';
import 'package:flutter/material.dart';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/utils.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';

import '../../../../commons/blocs/settingsAndLanguagesCubit.dart';
import '../models/order.dart';
import '../../../../utils/designConfig.dart';
import '../../../../utils/utils.dart';
import '../../../../commons/widgets/customTextContainer.dart';

class ShipmentDetailsContainer extends StatefulWidget {
  final Order order;
  final int selectedItemId;

  const ShipmentDetailsContainer({
    Key? key,
    required this.order,
    required this.selectedItemId,
  }) : super(key: key);

  @override
  State<ShipmentDetailsContainer> createState() =>
      _ShipmentDetailsContainerState();
}

enum StatusType { completed, inProgress, pending, cancelled }

class OrderStatus {
  final String status;
  final String subtitle;
  final String date;
  final StatusType statusType;
  final double size;
  OrderStatus(
      {required this.status,
      this.subtitle = '',
      required this.date,
      required this.statusType,
      required this.size});
}

class _ShipmentDetailsContainerState extends State<ShipmentDetailsContainer> {
  List attributeList = [], variantValues = [];
  late int _selectedItemId;
  late OrderItems _selectedOrder;
  List<OrderStatus> statuses = [];
  late int cancelableTillIndex, curStatusIndex;
  late Order order;
  List<Widget> orderStatusItems = [];
  GlobalKey _ratingKey = GlobalKey();

  @override
  void initState() {
    super.initState();
    order = widget.order;
    changeSelectedItem(widget.selectedItemId);
  }

  changeSelectedItem(int id) {
    _ratingKey = GlobalKey();
    orderStatusItems.clear();
    attributeList.clear();
    variantValues.clear();
    _selectedItemId = id;
    _selectedOrder = order.orderItems!
        .firstWhere((element) => element.id == _selectedItemId);

    cancelableTillIndex = orderStatusTypes.keys
        .toList()
        .indexWhere((element) => element == _selectedOrder.cancelableTill);

    curStatusIndex = orderStatusTypes.keys
        .toList()
        .indexWhere((element) => element == _selectedOrder.activeStatus);

    if (_selectedOrder.attrName!.isNotEmpty) {
      attributeList = _selectedOrder.attrName!.split(',');

      variantValues = _selectedOrder.variantValues!.split(',');
    }

    statuses = [
      OrderStatus(
          status: orderConfirmedKey,
          date: _selectedOrder.status!
              .firstWhere(
                (element) => element.status == receivedStatusType,
                orElse: () => StatusEntry(status: '', timestamp: ''),
              )
              .timestamp,
          statusType: StatusType.completed,
          size: 14),
      if (_selectedOrder.productType != digitalProductType) ...[
        //here we are checking if the order is cancelled or not , and if it is cancelled we are not showing the processed status only if it is  cancelled before processed status
        if (!_selectedOrder.statusNameList!.contains(cancelledStatusType) ||
            _selectedOrder.statusNameList!.contains(processedStatusType))
          OrderStatus(
              status: processedKey,
              date: '',
              statusType:
                  _selectedOrder.statusNameList!.contains(processedStatusType)
                      ? StatusType.completed
                      : StatusType.pending,
              size: _selectedOrder.activeStatus != processedKey ? 8 : 14),
        //here we are checking if the order is cancelled or not , and if it is cancelled we are not showing the shipped status only if it is  cancelled before shipped status
        if (!_selectedOrder.statusNameList!.contains(cancelledStatusType) ||
            _selectedOrder.statusNameList!.contains(shippedStatusType))
          OrderStatus(
              status: shippedKey,
              date: '',
              statusType:
                  _selectedOrder.statusNameList!.contains(shippedStatusType)
                      ? StatusType.completed
                      : StatusType.pending,
              size: _selectedOrder.activeStatus != shippedKey ? 8 : 14),

        //if usr is cancelled the order, show cancelled status else show delivered status
        if (_selectedOrder.statusNameList!.contains(cancelledStatusType))
          OrderStatus(
              status: cancelledKey,
              date: _selectedOrder.status!
                  .firstWhere(
                    (element) => element.status == cancelledStatusType,
                    orElse: () => StatusEntry(status: '', timestamp: ''),
                  )
                  .timestamp,
              statusType: StatusType.cancelled,
              size: 14),
        if (!_selectedOrder.statusNameList!.contains(cancelledStatusType) ||
            _selectedOrder.statusNameList!.contains(deliveredStatusType))
          OrderStatus(
              status: deliveredKey,
              date: _selectedOrder.status!
                  .firstWhere(
                    (element) => element.status == deliveredStatusType,
                    orElse: () => StatusEntry(status: '', timestamp: ''),
                  )
                  .timestamp,
              statusType:
                  _selectedOrder.statusNameList!.contains(deliveredStatusType)
                      ? StatusType.completed
                      : StatusType.pending,
              size: _selectedOrder.activeStatus != deliveredKey ? 8 : 14),
        if (_selectedOrder.statusNameList!
            .contains(returnRequestPendingStatusType))
          OrderStatus(
              status: returnRequestPendingKey,
              date: _selectedOrder.status!
                  .firstWhere(
                    (element) =>
                        element.status == returnRequestPendingStatusType,
                    orElse: () => StatusEntry(status: '', timestamp: ''),
                  )
                  .timestamp,
              statusType: StatusType.completed,
              size: _selectedOrder.activeStatus != returnRequestPendingKey
                  ? 8
                  : 14),
        if (_selectedOrder.statusNameList!
            .contains(returnRequestApprovedStatusType))
          OrderStatus(
              status: returnRequestApprovedKey,
              date: _selectedOrder.status!
                  .firstWhere(
                    (element) =>
                        element.status == returnRequestApprovedStatusType,
                    orElse: () => StatusEntry(status: '', timestamp: ''),
                  )
                  .timestamp,
              statusType: StatusType.completed,
              size: _selectedOrder.activeStatus != returnRequestApprovedKey
                  ? 8
                  : 14),
        if (_selectedOrder.statusNameList!
            .contains(returnRequestDeclineStatusType))
          OrderStatus(
              status: returnRequestDeclineKey,
              date: _selectedOrder.status!
                  .firstWhere(
                    (element) =>
                        element.status == returnRequestDeclineStatusType,
                    orElse: () => StatusEntry(status: '', timestamp: ''),
                  )
                  .timestamp,
              statusType: StatusType.completed,
              size: _selectedOrder.activeStatus != returnRequestDeclineKey
                  ? 8
                  : 14),
        if (_selectedOrder.statusNameList!.contains(returnedStatusType))
          OrderStatus(
              status: returnedKey,
              date: _selectedOrder.status!
                  .firstWhere(
                    (element) => element.status == returnedStatusType,
                    orElse: () => StatusEntry(status: '', timestamp: ''),
                  )
                  .timestamp,
              statusType: StatusType.completed,
              size: _selectedOrder.activeStatus != returnedKey ? 8 : 14),
      ],
      if (_selectedOrder.productType == digitalProductType &&
          _selectedOrder.downloadAllowed == 1 &&
          !_selectedOrder.statusNameList!.contains(cancelledStatusType))
        OrderStatus(
            status: deliveredKey,
            date: _selectedOrder.status!
                .firstWhere(
                  (element) => element.status == deliveredStatusType,
                  orElse: () => StatusEntry(status: '', timestamp: ''),
                )
                .timestamp,
            statusType:
                _selectedOrder.statusNameList!.contains(deliveredStatusType)
                    ? StatusType.completed
                    : StatusType.pending,
            size: _selectedOrder.statusNameList!.contains(deliveredStatusType)
                ? 14
                : 8),
      if (_selectedOrder.productType == digitalProductType &&
          _selectedOrder.downloadAllowed != 1 &&
          !_selectedOrder.statusNameList!.contains(cancelledStatusType))
        OrderStatus(
            status: deliveredKey,
            subtitle: pleaseCheckYourMailKey,
            date: _selectedOrder.status!
                .firstWhere(
                  (element) => element.status == deliveredStatusType,
                  orElse: () => StatusEntry(status: '', timestamp: ''),
                )
                .timestamp,
            statusType:
                _selectedOrder.statusNameList!.contains(deliveredStatusType)
                    ? StatusType.completed
                    : StatusType.pending,
            size: _selectedOrder.statusNameList!.contains(deliveredStatusType)
                ? 14
                : 8),
    ];
    for (int i = 0; i < statuses.length; i++) {
      orderStatusItems.add(OrderStatusItem(
          statuses: statuses,
          index: i,
          status: statuses[i],
          activeStatus: _selectedOrder.activeStatus ?? orderConfirmedKey,
          isLast: i == statuses.length - 1));
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<UpdateOrderCubit, UpdateOrderState>(
      listener: (context, state) {
        if (state is UpdateOrderFetchSuccess) {
          setState(() {
            order = state.order;
          });
          changeSelectedItem(_selectedItemId);
          Utils.showSnackBar(message: state.successMessage, context: context);
        }
        if (state is UpdateOrderFetchFailure) {
          Utils.showSnackBar(message: state.errorMessage, context: context);
        }
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          buildSelectedItemContainer(order.orderItems!
              .firstWhere((element) => element.id == _selectedItemId)),
          DesignConfig.smallHeightSizedBox,
          if (order.orderItems!.length > 1)
            buildOtherItemsContainer(order.orderItems!
                .where((element) => element.id != _selectedItemId)
                .toList())
        ],
      ),
    );
  }

  buildSelectedItemContainer(OrderItems orderItem) {
    return Column(
      children: [
        Container(
          padding: const EdgeInsetsDirectional.symmetric(
              vertical: appContentVerticalSpace),
          color: Theme.of(context).colorScheme.primaryContainer,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsetsDirectional.symmetric(
                    horizontal: appContentHorizontalPadding),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    CustomTextContainer(
                      textKey: shipmentDetailsKey,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    Container(
                      height: 24,
                      alignment: Alignment.center,
                      padding:
                          const EdgeInsetsDirectional.symmetric(horizontal: 8),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(borderRadius),
                        color: Theme.of(context)
                            .colorScheme
                            .primary
                            .withValues(alpha: 0.8),
                      ),
                      child: CustomTextContainer(
                        textKey:
                            'OTP ${order.orderItems!.firstWhere((element) => element.id == _selectedItemId).otp}',
                        style: Theme.of(context)
                            .textTheme
                            .labelMedium!
                            .copyWith(
                              color: Theme.of(context).colorScheme.onPrimary,
                            ),
                      ),
                    )
                  ],
                ),
              ),
              const Divider(
                height: 12,
                thickness: 0.5,
              ),
              Container(
                  padding: const EdgeInsetsDirectional.symmetric(
                      horizontal: appContentHorizontalPadding),
                  color: Theme.of(context).colorScheme.primaryContainer,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      CustomImageWidget(
                          url: orderItem.image ?? "",
                          width: 86,
                          height: 100,
                          borderRadius: 8),
                      DesignConfig.smallWidthSizedBox,
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            //if we are in the selected item, show all the details
                            CustomTextContainer(
                              textKey: orderItem.storeName ?? "",
                              maxLines: 2,
                              style: Theme.of(context).textTheme.titleSmall!,
                            ),
                            DesignConfig.smallHeightSizedBox,
                            CustomTextContainer(
                              textKey: orderItem.productName ?? "",
                              maxLines: 2,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall!
                                  .copyWith(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .secondary
                                          .withValues(alpha: 0.67)),
                            ),
                            DesignConfig.smallHeightSizedBox,
                            buildVariantList(),
                            DesignConfig.smallHeightSizedBox,
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                CustomTextContainer(
                                  textKey: Utils.priceWithCurrencySymbol(
                                      price: double.tryParse(orderItem.price ??
                                              0.toString()) ??
                                          0,
                                      context: context),
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleMedium
                                      ?.copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .primary,
                                      ),
                                ),
                                buildCancelOrReturnOrderButton()
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  )),
              const Divider(
                height: 12,
                thickness: 0.5,
              ),
              orderStatusContainer(),
              //if the product is returnable and the return period has passed then show the note
              if (_selectedOrder.isReturnable == '1' &&
                  !canReturnProduct()) ...[
                const Divider(
                  height: 12,
                  thickness: 0.5,
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: appContentHorizontalPadding),
                  child: Text.rich(
                    TextSpan(
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium!
                          .copyWith(color: Theme.of(context).colorScheme.error),
                      children: [
                        TextSpan(
                          text: context
                              .read<SettingsAndLanguagesCubit>()
                              .getTranslatedValue(
                                labelKey: noteKey,
                              ),
                        ),
                        const TextSpan(
                          text: ' : ',
                        ),
                        TextSpan(
                          text: context
                              .read<SettingsAndLanguagesCubit>()
                              .getTranslatedValue(
                                labelKey: returnProductDueDateKey,
                              ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
              const Divider(
                height: 12,
                thickness: 0.5,
              ),
              sellerDetailContainer()
            ],
          ),
        ),

        //user can give rating only if the order is delivered and not returned
        if (_selectedOrder.status!.indexWhere(
                    (element) => element.status == deliveredStatusType) !=
                -1 &&
            _selectedOrder.status!
                    .indexWhere((element) => element.status == returnedKey) ==
                -1) ...[
          DesignConfig.smallHeightSizedBox,
          SetProductRatingContainer(
            key: _ratingKey,
            orderItem: _selectedOrder,
          ),
        ],
      ],
    );
  }

  orderStatusContainer() {
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(
          vertical: appContentHorizontalPadding,
          horizontal: appContentHorizontalPadding),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ...orderStatusItems,
          DesignConfig.defaultHeightSizedBox,
          _selectedOrder.downloadAllowed == 1 &&
                  _selectedOrder.activeStatus == deliveredStatusType
              ? downloadProductFile()
              : const SizedBox(),
        ],
      ),
    );
  }

  sellerDetailContainer() {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(
        context,
        Routes.sellerDetailScreen,
        arguments: {
          'sellerId': _selectedOrder.sellerId,
        },
      ),
      child: Container(
          alignment: Alignment.centerLeft,
          padding: const EdgeInsetsDirectional.fromSTEB(
              appContentHorizontalPadding,
              appContentHorizontalPadding / 2,
              appContentHorizontalPadding,
              0),
          color: Theme.of(context).colorScheme.primaryContainer,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text.rich(
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  TextSpan(
                    children: [
                      TextSpan(
                          text: context
                              .read<SettingsAndLanguagesCubit>()
                              .getTranslatedValue(labelKey: sellerDetailsKey),
                          style: Theme.of(context).textTheme.labelLarge),
                      TextSpan(
                          text: ' : ',
                          style: Theme.of(context).textTheme.labelLarge),
                      TextSpan(
                        text:
                            _selectedOrder.storeName.toString().capitalizeFirst,
                        style: Theme.of(context).textTheme.labelLarge!.copyWith(
                            color: Theme.of(context).colorScheme.primary),
                      )
                    ],
                  ),
                ),
              ),
              const Icon(Icons.arrow_forward_ios, size: 24)
            ],
          )),
    );
  }

  buildOtherItemsContainer(List<OrderItems> orderItemList) {
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(
          vertical: appContentVerticalSpace),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: CustomTextContainer(
              textKey: moreProductInThisOrderKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          const Divider(
            height: 12,
            thickness: 0.5,
          ),
          ListView.separated(
              separatorBuilder: (context, index) => const Divider(
                    height: 12,
                    thickness: 0.5,
                  ),
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: orderItemList.length,
              itemBuilder: (context, index) {
                OrderItems orderItem = orderItemList[index];
                return GestureDetector(
                  onTap: () {
                    setState(() {
                      changeSelectedItem(orderItem.id!);
                    });
                  },
                  child: Container(
                      padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: appContentHorizontalPadding),
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.transparent),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          CustomImageWidget(
                              url: orderItem.image ?? "",
                              width: 86,
                              height: 100,
                              borderRadius: 8),
                          DesignConfig.smallWidthSizedBox,
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                CustomTextContainer(
                                  textKey: orderItem.productName ?? "",
                                  maxLines: 2,
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium!
                                      .copyWith(fontWeight: FontWeight.bold),
                                ),
                                DesignConfig.smallHeightSizedBox,
                                Text.rich(
                                  TextSpan(
                                    style:
                                        Theme.of(context).textTheme.bodyMedium!,
                                    children: [
                                      TextSpan(
                                          text: context
                                              .read<SettingsAndLanguagesCubit>()
                                              .getTranslatedValue(
                                                  labelKey: orderItem
                                                      .status!.last.status)),
                                      const TextSpan(
                                        text: ' ',
                                      ),
                                      if (orderItem.status!.last.status ==
                                          deliveredStatusType)
                                        TextSpan(
                                            text: DateTimeUtils.formatDate(
                                                orderItem.status!.last.timestamp
                                                    .split(' ')[0],
                                                'dd MMMM, yyyy')),
                                    ],
                                  ),
                                  textAlign: TextAlign.center,
                                )
                              ],
                            ),
                          ),
                          const Icon(
                            Icons.arrow_forward_ios,
                            size: 24,
                          )
                        ],
                      )),
                );
              }),
        ],
      ),
    );
  }

  buildVariantList() {
    return Wrap(spacing: 8, runSpacing: 8, children: [
      if (attributeList.isNotEmpty)
        ...List.generate(attributeList.length, (index) {
          return Utils.buildVariantContainer(
              context,
              attributeList[index].trim().toString().capitalizeFirst!,
              variantValues[index]);
        }),
      if (_selectedOrder.quantity != 0)
        Utils.buildVariantContainer(
            context, 'Qty', _selectedOrder.quantity.toString())
    ]);
  }

  buildCancelOrReturnOrderButton() {
    return Row(
      children: [
        //if product  is cancellable and not cancelled and not delivered  then show cancel button
        if (_selectedOrder.status!.indexWhere(
                    (element) => element.status == deliveredStatusType) ==
                -1 &&
            _selectedOrder.status!.indexWhere(
                    (element) => element.status == returnedStatusType) ==
                -1 &&
            _selectedOrder.isCancelable == 1 &&
            _selectedOrder.isAlreadyCancelled == '0' &&
            curStatusIndex <= cancelableTillIndex)
          BlocBuilder<UpdateOrderCubit, UpdateOrderState>(
            builder: (context, state) {
              return CustomRoundedButton(
                  buttonTitle: cancelKey,
                  showBorder: false,
                  widthPercentage: 0.2,
                  horizontalPadding: 2,
                  height: 28,
                  child: state is UpdateOrderFetchInProgress
                      ? const CustomCircularProgressIndicator()
                      : null,
                  onTap: () {
                    Utils.openAlertDialog(context, onTapYes: () {
                      context.read<UpdateOrderCubit>().updateOrder(params: {
                        ApiURL.orderItemIdApiKey: _selectedOrder.id,
                        ApiURL.statusApiKey: cancelledStatusType,
                      });
                      Navigator.of(context).pop();
                    }, message: cancelProductWarningKey, yesLabel: cancelKey);
                  });
            },
          ),

        //if product is not digital and is returnable and delivered and return request not submitted yet then show return button
        if (_selectedOrder.isReturnable == '1' &&
            _selectedOrder.status!.indexWhere(
                    (element) => element.status == deliveredStatusType) !=
                -1 &&
            canReturnProduct() &&
            _selectedOrder.productType != digitalProductType &&
            _selectedOrder.isAlreadyReturned == '0' &&
            (_selectedOrder.status!.indexWhere((element) =>
                        element.status == returnRequestDeclineStatusType) ==
                    -1 &&
                _selectedOrder.status!.indexWhere((element) =>
                        element.status == returnRequestApprovedStatusType) ==
                    -1 &&
                _selectedOrder.status!.indexWhere((element) =>
                        element.status == returnRequestPendingStatusType) ==
                    -1))
          BlocBuilder<UpdateOrderCubit, UpdateOrderState>(
            builder: (context, state) {
              return CustomRoundedButton(
                  buttonTitle: returnProductKey,
                  showBorder: false,
                  widthPercentage: 0.35,
                  horizontalPadding: 4,
                  height: 28,
                  style: Theme.of(context)
                      .textTheme
                      .bodyMedium!
                      .copyWith(color: Theme.of(context).colorScheme.onPrimary),
                  child: state is UpdateOrderFetchInProgress
                      ? const CustomCircularProgressIndicator()
                      : null,
                  onTap: () {
                    Utils.openAlertDialog(context, onTapYes: () {
                      context.read<UpdateOrderCubit>().updateOrder(params: {
                        ApiURL.orderItemIdApiKey: _selectedOrder.id,
                        ApiURL.statusApiKey: returnedStatusType,
                      });
                      Navigator.of(context).pop();
                    },
                        message: returnProductWarningKey,
                        yesLabel: returnProductKey);
                  });
            },
          ),
      ],
    );
  }

  bool canReturnProduct() {
    if (_selectedOrder.status!
            .indexWhere((element) => element.status == deliveredStatusType) !=
        -1) {
      DateTime deliveryDate = DateFormat('dd-MM-yyyy').parse(
        _selectedOrder.status!
            .firstWhere((element) => element.status == deliveredStatusType)
            .timestamp
            .split(' ')[0],
      );
      final DateTime lastReturnDate = deliveryDate.add(Duration(
          days: int.parse(context
              .read<SettingsAndLanguagesCubit>()
              .getSettings()
              .systemSettings!
              .maxDaysToReturnItem
              .toString())));
      final DateTime currentDate = DateTime.now();

      return currentDate.isBefore(lastReturnDate) ||
          currentDate.isAtSameMomentAs(lastReturnDate);
    } else {
      return true;
    }
  }

  Widget _buildProgressContainer(
      {required double width, required Color color}) {
    return Container(
      width: width,
      decoration:
          BoxDecoration(color: color, borderRadius: BorderRadius.circular(3.0)),
    );
  }

  downloadProductFile() {
    return MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => DownloadFileCubit(),
          ),
          BlocProvider(
            create: (context) => ProductFileDownloadLinkCubit(),
          ),
        ],
        child: BlocConsumer<ProductFileDownloadLinkCubit,
            ProductFileDownloadLinkState>(
          listener: (context, state) {
            if (state is ProductFileDownloadLinkSuccess) {
              context.read<DownloadFileCubit>().downloadFile(
                    fileUrl: state.downloadLink,
                  );
            }
            if (state is ProductFileDownloadLinkFailure) {
              Utils.showSnackBar(message: state.errorMessage, context: context);
            }
          },
          builder: (context, state) {
            return Padding(
              padding: const EdgeInsetsDirectional.only(top: 28),
              child: BlocConsumer<DownloadFileCubit, DownloadFileState>(
                listener: (context, downloadstate) {
                  if (downloadstate is DownloadFileSuccess) {
                    Utils.showSnackBar(
                      message: fileDownloadedKey,
                      context: context,
                      duration: const Duration(seconds: 3),
                      action: SnackBarAction(
                        label: context
                            .read<SettingsAndLanguagesCubit>()
                            .getTranslatedValue(labelKey: viewKey),
                        textColor: Theme.of(context).colorScheme.onSecondary,
                        onPressed: () async {
                          await OpenFilex.open(
                              downloadstate.downloadedFilePath);
                        },
                      ),
                    );
                  } else if (downloadstate is DownloadFileFailure) {
                    Utils.showSnackBar(
                        message: downloadstate.errorMessage, context: context);
                  }
                },
                builder: (context, downloadstate) {
                  if (downloadstate is! DownloadFileInProgress) {
                    return CustomRoundedButton(
                        buttonTitle: downloadKey,
                        showBorder: false,
                        widthPercentage: 0.35,
                        horizontalPadding: 2,
                        height: 28,
                        child: state is ProductFileDownloadLinkProgress
                            ? const CustomCircularProgressIndicator()
                            : null,
                        onTap: () {
                          context
                              .read<ProductFileDownloadLinkCubit>()
                              .getProductFileDownloadLink(
                                orderItemId: _selectedOrder.id!,
                              );
                        });
                  }
                  return SizedBox(
                    height: 30,
                    child: LayoutBuilder(builder: (context, boxConstraints) {
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Stack(
                            children: [
                              _buildProgressContainer(
                                  width: boxConstraints.maxWidth,
                                  color: Theme.of(context)
                                      .colorScheme
                                      .primary
                                      .withValues(alpha: 0.2)),
                              _buildProgressContainer(
                                  width: boxConstraints.maxWidth *
                                      downloadstate.uploadedPercentage *
                                      0.01,
                                  color: Theme.of(context).colorScheme.primary),
                            ],
                          ),
                          DesignConfig.smallHeightSizedBox,
                          Text(
                            "${Utils.formatDouble(downloadstate.uploadedPercentage)} %",
                          )
                        ],
                      );
                    }),
                  );
                },
              ),
            );
          },
        ));
  }
}
