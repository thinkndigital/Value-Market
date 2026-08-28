import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshopplus_seller/features/notification/blocs/notification_cubit.dart';

import 'package:eshopplus_seller/features/notification/models/notification.dart';

import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';

import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';

import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';

import '../../../utils/utils.dart';

class NotificationScreen extends StatefulWidget {
  const NotificationScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => NotificationCubit(),
        child: const NotificationScreen(),
      );
  @override
  _NotificationScreenState createState() => _NotificationScreenState();
}

class _NotificationScreenState extends State<NotificationScreen> {
  List<Notifications> newNotifications = [];
  bool _isExpanded = false;
  List<Notifications> earlierNotifications = [];
  DateFormat inputFormat = DateFormat('yyyy-MM-dd');
  List<Widget> notificationItemWidgets = [];
  @override
  void initState() {
    super.initState();

    Future.delayed(Duration.zero, () {
      getNotifications();
    });
  }

  getNotifications() {
    context
        .read<NotificationCubit>()
        .getNotification(context.read<StoresCubit>().getDefaultStore().id!);
  }

  void loadMoreNotifications() {
    context
        .read<NotificationCubit>()
        .loadMore(context.read<StoresCubit>().getDefaultStore().id!);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        appBar: const CustomAppbar(titleKey: notificationsKey),
        body: SafeAreaWithBottomPadding(
          child: BlocConsumer<NotificationCubit, NotificationState>(
            listener: (context, state) {
              if (state is NotificationFetchSuccess) {
                notificationItemWidgets.clear();
                newNotifications.clear();
                earlierNotifications.clear();
                newNotifications
                    .addAll(state.notifications.where((notification) {
                  return DateTime.now()
                          .difference(
                              inputFormat.parse(notification.createdAt!))
                          .inHours <=
                      24;
                }).toList());
                earlierNotifications
                    .addAll(state.notifications.where((notification) {
                  return DateTime.now()
                          .difference(
                              inputFormat.parse(notification.createdAt!))
                          .inHours >
                      24;
                }).toList());
                if (newNotifications.isNotEmpty) {
                  notificationItemWidgets.addAll([
                    Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: CustomTextContainer(
                          textKey: newKey,
                          style: Theme.of(context).textTheme.titleMedium,
                        )),
                    ...newNotifications.map((notification) =>
                        buildNotificationContainer(notification)),
                  ]);
                }
                if (earlierNotifications.isNotEmpty) {
                  notificationItemWidgets.addAll([
                    Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: CustomTextContainer(
                          textKey: earlierKey,
                          style: Theme.of(context).textTheme.titleMedium,
                        )),
                    ...earlierNotifications.map((notification) =>
                        buildNotificationContainer(notification)),
                  ]);
                }
              }
            },
            builder: (context, state) {
              if (state is NotificationFetchSuccess) {
                return NotificationListener<ScrollUpdateNotification>(
                  onNotification: (notification) {
                    if (notification.metrics.pixels >=
                        notification.metrics.maxScrollExtent) {
                      if (context.read<NotificationCubit>().hasMore()) {
                        loadMoreNotifications();
                      }
                    }
                    return true;
                  },
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      RefreshIndicator(
                        onRefresh: () async {
                          getNotifications();
                        },
                        child: ListView.separated(
                          separatorBuilder: (context, index) =>
                              DesignConfig.smallHeightSizedBox,
                          padding: const EdgeInsetsDirectional.all(
                              appContentHorizontalPadding),
                          itemCount: 1,
                          shrinkWrap: true,
                          itemBuilder: (context, index) {
                            if (context.read<NotificationCubit>().hasMore()) {
                              if (index == state.notifications.length - 1) {
                                if (context
                                    .read<NotificationCubit>()
                                    .fetchMoreError()) {
                                  return Center(
                                    child: CustomTextButton(
                                        buttonTextKey: retryKey,
                                        onTapButton: () {
                                          loadMoreNotifications();
                                        }),
                                  );
                                }

                                return Center(
                                  child: CustomCircularProgressIndicator(
                                      indicatorColor: Theme.of(context)
                                          .colorScheme
                                          .primary),
                                );
                              }
                            }

                            return Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: notificationItemWidgets);
                          },
                        ),
                      ),
                    ],
                  ),
                );
              }
              if (state is NotificationFetchFailure) {
                return ErrorScreen(
                    onPressed: getNotifications,
                    text: state.errorMessage,
                    image: state.errorMessage == noInternetKey
                        ? "no_internet"
                        : 'no_notification',
                    child: state is NotificationFetchInProgress
                        ? CustomCircularProgressIndicator(
                            indicatorColor:
                                Theme.of(context).colorScheme.primary,
                          )
                        : null);
              }
              return Center(
                child: CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.primary),
              );
            },
          ),
        ));
  }

  buildNotificationContainer(Notifications notification) {
    return StatefulBuilder(builder: (context, setState) {
      return GestureDetector(
        onTap: () {
          if (notification.type == 'notification' &&
              notification.link!.isNotEmpty) {
            Utils.launchURL(notification.link.toString());
          }
        },
        child: Column(
          children: [
            CustomDefaultContainer(
                borderRadius: 8,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    if (notification.image != null &&
                        notification.image!.isNotEmpty) ...[
                      CustomImageWidget(
                          url: notification.image ?? "",
                          width: 48,
                          height: 48,
                          borderRadius: 4),
                      const SizedBox(
                        width: 12,
                      ),
                    ],
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            notification.title ?? "",
                            style: Theme.of(context).textTheme.titleSmall,
                            maxLines: _isExpanded ? null : 2,
                            overflow: _isExpanded
                                ? TextOverflow.visible
                                : TextOverflow.ellipsis,
                          ),
                          const SizedBox(
                            height: 4,
                          ),
                          Text.rich(
                            TextSpan(
                              text: _isExpanded
                                  ? notification.message
                                  : notification.message?.substring(
                                          0,
                                          notification.message!.length > 100
                                              ? 100
                                              : notification.message!.length) ??
                                      "",
                              children: [
                                if (notification.message != null &&
                                    notification.message!.length > 100)
                                  TextSpan(
                                    text: _isExpanded
                                        ? " ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: readLessKey)}"
                                        : "... ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: readMoreKey)}",
                                    style: TextStyle(
                                      color:
                                          Theme.of(context).colorScheme.primary,
                                      fontWeight: FontWeight.bold,
                                    ),
                                    recognizer: TapGestureRecognizer()
                                      ..onTap = () {
                                        setState(() {
                                          _isExpanded = !_isExpanded;
                                        });
                                      },
                                  ),
                              ],
                            ),
                            style:
                                Theme.of(context).textTheme.bodySmall!.copyWith(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .secondary
                                          .withValues(alpha: 0.8),
                                    ),
                          ),
                          const SizedBox(
                            height: 4,
                          ),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              Text(
                                formatDate(
                                    notification.createdAt!.split(' ')[0]),
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall!
                                    .copyWith(
                                        color: Theme.of(context)
                                            .colorScheme
                                            .secondary
                                            .withValues(alpha: 0.8)),
                              ),
                              if (notification.type == 'notification' &&
                                  notification.link!.isNotEmpty)
                                CustomTextButton(
                                  buttonTextKey: context
                                      .read<SettingsAndLanguagesCubit>()
                                      .getTranslatedValue(
                                          labelKey: openLinkKey),
                                  onTapButton: () => Utils.launchURL(
                                      notification.link.toString()),
                                  textStyle: TextStyle(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .primary),
                                )
                            ],
                          )
                        ],
                      ),
                    ),
                  ],
                )),
            DesignConfig.smallHeightSizedBox
          ],
        ),
      );
    });
  }

  String formatDate(String dateStr) {
    DateTime dateTime = DateTime.parse(dateStr);
    DateFormat dateFormat = DateFormat('MMM dd, yyyy');
    return dateFormat.format(dateTime);
  }
}
