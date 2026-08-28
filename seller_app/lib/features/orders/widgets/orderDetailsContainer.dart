import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/features/profile/chat/blocs/downloadFileCubit.dart';
import 'package:value_market_seller/features/orders/blocs/getInvoiceCubit.dart';
import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/widgets/customCircularProgressIndicator.dart';

import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';

import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/dateTimeUtils.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

import 'package:open_filex/open_filex.dart';

import '../models/order.dart';

class OrderDetailsContainer extends StatelessWidget {
  final Order order;
  const OrderDetailsContainer({Key? key, required this.order})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: MediaQuery.of(context).size.width,
      padding: EdgeInsets.symmetric(vertical: appContentVerticalSpace),
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.symmetric(
                horizontal: appContentHorizontalPadding),
            child: CustomTextContainer(
              textKey: orderDetailsKey,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          const Divider(
            thickness: 0.5,
          ),
          buildLabelAndText(
            context,
            orderDateTimeKey,
            DateTimeUtils.getFormattedDateTime(
              order.createdAt!,
            ),
          ),
          const SizedBox(height: 8),
          buildLabelAndText(
              context, orderIDKey, order.id.toString().withOrderSymbol()),
          const SizedBox(height: 8),
          buildLabelAndText(context, paymentMethodKey,
              paymentGatewayDisplayNames[order.paymentMethod.toString()] ?? ''),
          if (order.type == digitalProductType) ...[
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
                }, builder: (context, state) {
                  return BlocConsumer<DownloadFileCubit, DownloadFileState>(
                    listener: (context, downloadstate) {
                      if (downloadstate is DownloadFileSuccess) {
                        Utils.showSnackBar(
                          message: fileDownloadedKey,
                          msgDuration: const Duration(seconds: 3),
                          action: SnackBarAction(
                            label: context
                                .read<SettingsAndLanguagesCubit>()
                                .getTranslatedValue(labelKey: viewKey),
                            textColor:
                                Theme.of(context).colorScheme.onSecondary,
                            onPressed: () async {
                              await OpenFilex.open(
                                  downloadstate.downloadedFilePath);
                            },
                          ),
                        );
                      } else if (downloadstate is DownloadFileFailure) {
                        Utils.showSnackBar(
                          message: downloadstate.errorMessage,
                        );
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
                                  visualDensity:
                                      const VisualDensity(vertical: -4),
                                  onPressed: () {
                                    if (state is! GetInvoiceProgress) {
                                      context
                                          .read<GetInvoiceCubit>()
                                          .getInvoice(
                                              id: order.id!,
                                              apiUrl:
                                                  ApiURL.downloadOrderInvoice);
                                    }
                                  },
                                  icon:
                                      downloadstate is DownloadFileInProgress ||
                                              state is GetInvoiceProgress
                                          ? CustomCircularProgressIndicator(
                                              indicatorColor: Theme.of(context)
                                                  .colorScheme
                                                  .primary,
                                            )
                                          : Icon(
                                              Icons.file_download_outlined,
                                              size: 18,
                                              color: Theme.of(context)
                                                  .colorScheme
                                                  .secondary,
                                            ))
                            ]),
                      );
                    },
                  );
                }))
          ]
        ],
      ),
    );
  }

  buildLabelAndText(BuildContext context, String title, String value) {
    return Padding(
      padding:
          const EdgeInsets.symmetric(horizontal: appContentHorizontalPadding),
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
          Text(value,
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
