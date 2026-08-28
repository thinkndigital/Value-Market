import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/features/orders/blocs/parcel/createOrderParcelCubit.dart';
import 'package:eshopplus_seller/features/orders/blocs/parcel/deleteOrderParcelCubit.dart';
import 'package:eshopplus_seller/features/orders/blocs/parcel/getParcelCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/features/orders/models/order.dart';
import 'package:eshopplus_seller/features/orders/models/parcel.dart';

import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';

import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/utils/dateTimeUtils.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ParcelContainer extends StatefulWidget {
  final Order order;
  final Function? callback;
  const ParcelContainer({Key? key, required this.order, this.callback})
      : super(key: key);

  @override
  _ParcelContainerState createState() => _ParcelContainerState();
}

class _ParcelContainerState extends State<ParcelContainer> {
  List<Parcel> parcels = [];

  List<int> selectedOrders = [];
  @override
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<ParcelCubit, ParcelState>(
      listener: (context, state) {
        if (state is ParcelFetchSuccess) {
          parcels = state.parcels;
        }
      },
      child: BlocBuilder<ParcelCubit, ParcelState>(builder: (context, state) {
        if (state is ParcelFetchSuccess && parcels.isNotEmpty) {
          return BlocProvider(
            create: (context) => DeleteParcelCubit(),
            child: BlocListener<DeleteParcelCubit, DeleteParcelState>(
              listener: (context, deletestate) {
                if (deletestate is DeleteParcelSuccess) {
                  Parcel parcelToBeDeleted =
                      parcels.firstWhere((e) => e.id == deletestate.parcelId);
                  for (int i = 0; i < parcelToBeDeleted.items!.length; i++) {
                    widget.order.orderItems!
                        .firstWhere((element) =>
                            element.id ==
                            parcelToBeDeleted.items![i].orderItemId)
                        .activeStatus = receivedStatusType;
                  }
                  widget.callback!(widget.order);
                  parcels.remove(parcelToBeDeleted);
                  setState(() {});

                  Utils.showSnackBar(
                    message: deletestate.successMessage,
                  
                  );
                } else if (deletestate is DeleteParcelFailure) {
                  Utils.showSnackBar(
                      message: deletestate.errorMessage);
                }
              },
              child:
                  BlocListener<CreateOrderParcelCubit, CreateOrderParcelState>(
                listener: (context, state) {
                  if (state is CreateOrderParcelSuccess) {
                    Navigator.of(context).pop();
                    parcels.add(state.parcel);
                    state.parcel.items!.forEach((e) {
                      widget.order.orderItems!
                          .firstWhere((element) => element.id == e.orderItemId)
                          .activeStatus = processedStatusType;
                    });
                    widget.callback!(widget.order);

                    setState(() {});
                    Utils.showSnackBar(
                        message: state.successMessage);
                  }
                  if (state is CreateOrderParcelFailure) {
                    Navigator.of(context).pop();
                    Utils.showSnackBar(
                        message: state.errorMessage);
                  }
                },
                child: Container(
                  color: Theme.of(context).colorScheme.primaryContainer,
                  margin: EdgeInsets.only(bottom: 8),
                  padding:
                      EdgeInsets.symmetric(vertical: appContentVerticalSpace),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Padding(
                        padding: const EdgeInsets.symmetric(
                            horizontal: appContentHorizontalPadding),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            CustomTextContainer(
                              textKey: allParcelsKey,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ],
                        ),
                      ),
                      const Divider(
                        height: 8,
                        thickness: 0.5,
                      ),
                      ListView.separated(
                          separatorBuilder: (context, index) =>
                              DesignConfig.smallHeightSizedBox,
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          padding: const EdgeInsets.symmetric(
                              vertical: 8,
                              horizontal: appContentHorizontalPadding),
                          itemCount: parcels.length,
                          itemBuilder: (context, index) {
                            Parcel parcel = parcels[index];
                            return Container(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                    color: Theme.of(context)
                                        .colorScheme
                                        .secondary
                                        .withValues(alpha: 0.1)),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  Padding(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 12),
                                    child: Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: <Widget>[
                                        Text.rich(
                                          style: Theme.of(context)
                                              .textTheme
                                              .labelLarge!,
                                          TextSpan(
                                            children: [
                                              TextSpan(
                                                text: context
                                                    .read<
                                                        SettingsAndLanguagesCubit>()
                                                    .getTranslatedValue(
                                                        labelKey: createdOnKey),
                                              ),
                                              const TextSpan(
                                                text: ' ',
                                              ),
                                              TextSpan(
                                                  text: DateTimeUtils
                                                      .getFormattedDateTime(
                                                          parcel.createdDate!,
                                                          isReturnOnlyDate:
                                                              true))
                                            ],
                                          ),
                                          textAlign: TextAlign.start,
                                        ),
                                        Utils.buildStatusWidget(
                                            context, parcel.activeStatus!,
                                            showBorder: false),
                                      ],
                                    ),
                                  ),
                                  Divider(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .secondary
                                          .withValues(alpha: 0.1)),
                                  Padding(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 12),
                                    child: CustomTextContainer(
                                      textKey: parcel.parcelName ?? '',
                                      style:
                                          Theme.of(context).textTheme.bodyLarge,
                                    ),
                                  ),
                                  Divider(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .secondary
                                          .withValues(alpha: 0.1)),
                                  Padding(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 12),
                                    child: Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: <Widget>[
                                        Text.rich(
                                          style: Theme.of(context)
                                              .textTheme
                                              .labelLarge!,
                                          TextSpan(
                                            children: [
                                              TextSpan(
                                                text: context
                                                    .read<
                                                        SettingsAndLanguagesCubit>()
                                                    .getTranslatedValue(
                                                        labelKey:
                                                            totalItemsKey),
                                              ),
                                              const TextSpan(
                                                text: ' ',
                                              ),
                                              TextSpan(
                                                  text: parcel.items!.length
                                                      .toString())
                                            ],
                                          ),
                                          textAlign: TextAlign.start,
                                        ),
                                        Row(
                                          children: <Widget>[
                                            buildUpdateStatusIcon(parcel),
                                            DesignConfig.smallWidthSizedBox,
                                            buildDeleteButton(parcel),
                                          ],
                                        )
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            );
                          }),
                    ],
                  ),
                ),
              ),
            ),
          );
        }

        if (state is ParcelFetchFailure) {}
        if (state is ParcelFetchInProgress) {
          return CustomCircularProgressIndicator(
              indicatorColor: Theme.of(context).colorScheme.primary);
        }
        return SizedBox();
      }),
    );
  }

  buildUpdateStatusIcon(Parcel parcel) {
    return Container(
      decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(4)),
      child: IconButton(
        padding: EdgeInsets.zero,
        visualDensity: const VisualDensity(vertical: -4, horizontal: -4),
        icon: Icon(Icons.edit_outlined,
            color: Theme.of(context).colorScheme.primary),
        onPressed: () {
          Utils.navigateToScreen(
            context,
            Routes.parcelDetailsScreen,
            arguments: {
              'parcel': parcel,
              'order': widget.order,
              'callback': (parcel) {
                setState(() {});
                if (widget.callback != null) {
                  widget.callback!(widget.order);
                }
              }
            },
          );
        },
      ),
    );
  }

  buildDeleteButton(Parcel parcel) {
    return BlocBuilder<DeleteParcelCubit, DeleteParcelState>(
      builder: (context, state) {
        return Container(
          decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.error.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(4)),
          child: IconButton(
            padding: EdgeInsets.zero,
            visualDensity: const VisualDensity(vertical: -4, horizontal: -4),
            icon: state is DeleteParcelProgress && state.parcelId == parcel.id
                ? CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.error,
                  )
                : Icon(
                    Icons.delete_outline,
                    size: 18,
                    color: Theme.of(context).colorScheme.error,
                  ),
            onPressed: () {
              if (state is! DeleteParcelProgress) {
                Utils.openAlertDialog(context, onTapYes: () {
                  context
                      .read<DeleteParcelCubit>()
                      .deleteParcel(parcelId: parcel.id!);
                  Navigator.of(context).pop();
                }, message: deleteParcelConfirmationKey);
              }
            },
          ),
        );
      },
    );
  }
}
