import 'package:eshop_plus/ui/profile/orders/blocs/downloadFileCubit.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/getInvoiceCubit.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:open_filex/open_filex.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../models/order.dart';

class OrderDetailsContainer extends StatelessWidget {
  final Order order;
  const OrderDetailsContainer({Key? key, required this.order})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    DateFormat dateFormat = DateFormat('dd-MMM yyyy, hh:mm');
    return Container(
      width: MediaQuery.of(context).size.width,
      padding: const EdgeInsetsDirectional.symmetric(
          vertical: appContentVerticalSpace),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: CustomTextContainer(
              textKey: orderDetailsKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          const Divider(thickness: 0.5),
          buildLabelAndText(context, orderDateTimeKey,
              dateFormat.format(DateTime.parse(order.createdAt!))),
          DesignConfig.smallHeightSizedBox,
          buildLabelAndText(context, orderIDKey, order.id.toString()),
          DesignConfig.smallHeightSizedBox,
          buildLabelAndText(
            context,
            orderTotalKey,
            '${Utils.priceWithCurrencySymbol(price: order.finalTotal ?? 0, context: context)} (${order.orderItems!.length} ${order.orderItems!.length > 1 ? 'items' : 'item'})',
          ),
          const Divider(
            thickness: 0.5,
          ),
          BlocProvider(
            create: (context) => DownloadFileCubit(),
            child: BlocConsumer<GetInvoiceCubit, GetInvoiceState>(
              listener: (context, state) async {
                if (state is GetInvoiceSuccess &&
                    state.invoiceUrl.trim().isNotEmpty) {
                  context.read<DownloadFileCubit>().downloadFile(
                        fileUrl: state.invoiceUrl,
                        fName: 'Invoice_${order.id!}.pdf',
                      );
                }
              },
              builder: (context, state) {
                return BlocConsumer<DownloadFileCubit, DownloadFileState>(
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
                          message: downloadstate.errorMessage,
                          context: context);
                    }
                  },
                  builder: (context, downloadstate) {
                    return Padding(
                      padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: appContentHorizontalPadding),
                      child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: <Widget>[
                            CustomTextContainer(
                              textKey: downloadInvoiceKey,
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                            IconButton(
                              visualDensity: const VisualDensity(vertical: -4),
                              onPressed: () async {
                                if (await isInvoiceExists(order.id!)) {
                                  String downloadFilePath =
                                      await Utils.getDowanloadFilePath(
                                          'Invoice_${order.id!}.pdf');
                                  OpenFilex.open(downloadFilePath);
                                  return;
                                }
                                if (state is! GetInvoiceProgress) {
                                  context
                                      .read<GetInvoiceCubit>()
                                      .getInvoice(orderId: order.id!);
                                }
                              },
                              icon: downloadstate is DownloadFileInProgress ||
                                      state is GetInvoiceProgress
                                  ? CustomCircularProgressIndicator(
                                      indicatorColor:
                                          Theme.of(context).colorScheme.primary,
                                    )
                                  : FutureBuilder(
                                      future: isInvoiceExists(order.id!),
                                      builder: (context, snapshot) {
                                        return snapshot.hasData &&
                                                snapshot.data == true
                                            ? Icon(
                                                Icons.remove_red_eye_outlined,
                                                size: 18,
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .secondary)
                                            : Icon(Icons.file_download_outlined,
                                                size: 18,
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .secondary);
                                      }),
                            )
                          ]),
                    );
                  },
                );
              },
            ),
          )
        ],
      ),
    );
  }

  Future<bool> isInvoiceExists(int orderId) async {
    String fileName = 'Invoice_${order.id!}.pdf';
    String downloadFilePath = await Utils.getDowanloadFilePath(fileName);
    if (await Utils.fileExists(downloadFilePath)) {
      return true;
    }
    return false;
  }

  buildLabelAndText(BuildContext context, String title, String value) {
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(
          horizontal: appContentHorizontalPadding),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          CustomTextContainer(
            textKey: title,
            style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.8)),
          ),
          CustomTextContainer(
              textKey: value,
              style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.8)))
        ],
      ),
    );
  }
}
