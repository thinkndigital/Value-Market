import 'package:eshop_plus/ui/profile/orders/widgets/shipmentDetailsContainer.dart';
import 'package:eshop_plus/core/theme/colors.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/dashedLinePainter.dart';
import 'package:eshop_plus/commons/widgets/solidLinePainter.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';

import 'package:intl/intl.dart';

class OrderStatusItem extends StatelessWidget {
  final List<OrderStatus> statuses;
  final OrderStatus status;
  final String activeStatus;
  final bool isLast;
  final int index;
  const OrderStatusItem(
      {Key? key,
      required this.statuses,
      required this.status,
      required this.activeStatus,
      this.isLast = false,
      required this.index})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.topLeft,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              Positioned(
                left: -(status.size / 2),
                child: SizedBox(
                  width: status.size,
                  height: status.size,
                  child: _getStatusIcon(status),
                ),
              ),
              !isLast
                  ? Container(
                      padding: EdgeInsets.only(
                        top: index + (status.size / 1.5),
                      ),
                      height: 40,
                      child: [StatusType.completed, StatusType.cancelled]
                              .contains(statuses[index + 1].statusType)
                          ? CustomPaint(
                              painter: SolidLinePainter(
                                color: const Color(0xFF0EAE00),
                              ),
                            )
                          : CustomPaint(
                              painter: DashedLinePainter(
                                  color: const Color(0xFFD9D9D9)
                                      .withValues(alpha: 0.67)),
                            ),
                    )
                  : const SizedBox(),
            ],
          ),
          Positioned(
            top: -5,
            left: 10,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  verticalDirection: VerticalDirection.up,
                  children: [
                    CustomTextContainer(
                      textKey: status.status,
                      style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                            fontWeight: status.status == orderConfirmedKey
                                ? FontWeight.bold
                                : FontWeight.normal,
                            color: status.status == orderConfirmedKey
                                ? Theme.of(context).colorScheme.secondary
                                : Theme.of(context)
                                    .colorScheme
                                    .secondary
                                    .withValues(alpha: 0.67),
                          ),
                      maxLines: 1,
                    ),
                    const SizedBox(width: 8),
                    if (status.date.isNotEmpty)
                      Text(
                        formatDate(status.date.split(' ')[0]),
                        style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                              color: Theme.of(context)
                                  .colorScheme
                                  .secondary
                                  .withValues(alpha: 0.67),
                            ),
                      ),
                  ],
                ),
                if (status.subtitle.isNotEmpty)
                  CustomTextContainer(
                    textKey: status.subtitle,
                    style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.67),
                        ),
                    maxLines: 1,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _getStatusIcon(OrderStatus status) {
    if (status.status == orderConfirmedKey) {
      return createSolidCircle(const Color(0xFF0EAE00));
    } else if ((status.status == deliveredKey &&
        status.statusType == StatusType.completed)) {
      return createColoredCircle(const Color(0xFF0EAE00));
    } else if (status.statusType == StatusType.cancelled) {
      return createColoredCircle(errorColor);
    } else if (status.status == activeStatus) {
      return createColoredCircle(const Color(0xFF0EAE00));
    } else if (status.statusType == StatusType.completed) {
      return Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(
            color: const Color(0xFF0EAE00),
            border: Border.all(color: Colors.white),
            shape: BoxShape.circle,
          ));
    }
    return Container(
        width: 6,
        height: 6,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(color: const Color(0xFFD9D9D9), width: 2),
        ));
  }

  Widget createColoredCircle(Color color) {
    return Container(
      width: 14,
      height: 14,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(
          colors: [Colors.white, color.withValues(alpha: 0.4)],
          center: Alignment.center,
          radius: 0.8,
        ),
      ),
      child: Container(
        width: 6,
        height: 6,
        decoration: BoxDecoration(
          color: color,
          shape: BoxShape.circle,
        ),
      ),
    );
  }

  Widget createSolidCircle(Color color) {
    return Container(
      width: 8,
      height: 8,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color,
        shape: BoxShape.circle,
      ),
    );
  }

  String formatDate(String date) {
    // Define the input format
    DateFormat inputFormat = DateFormat('dd-MM-yyyy');
    // Parse the date string into a DateTime object
    DateTime dateTime = inputFormat.parse(date);
    final daySuffix = Utils.getDayOfMonthSuffix(dateTime.day);
    final DateFormat dateFormat = DateFormat('E, d');
    final String formattedDate = dateFormat.format(dateTime) +
        daySuffix +
        DateFormat(' MMM yyyy').format(dateTime);
    return formattedDate;
  }
}
