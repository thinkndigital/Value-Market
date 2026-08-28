import 'package:eshopplus_seller/commons/widgets/customLabelContainer.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';

class CustomStatusContainer extends StatelessWidget {
  final Function getValueList;
  final String status;
  const CustomStatusContainer(
      {Key? key, required this.getValueList, required this.status})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return getValueList(status)[0].isEmpty
        ? const SizedBox()
        : Container(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: 12, vertical: 2),
            decoration: ShapeDecoration(
              color: getValueList(status)[1].withValues(alpha: 0.05),
              shape: RoundedRectangleBorder(
                side: BorderSide(width: 0.50, color: getValueList(status)[1]),
                borderRadius: BorderRadius.circular(borderRadius),
              ),
            ),
            child: CustomLabelContainer(
              textKey: getValueList(status)[0],
              isFieldValueMandatory: false,
              textAlign: TextAlign.center,
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium!
                  .copyWith(color: getValueList(status)[1]),
            )

            /* : Text(
        getStatusTextAndColor(status)[0],
        style: Theme.of(context)
            .textTheme
            .bodyMedium!
            .copyWith(color: getStatusTextAndColor(status)[1]),
      ), */
            );
  }
}
