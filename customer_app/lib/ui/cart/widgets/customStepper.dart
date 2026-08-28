import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';

class CustomStepper extends StatelessWidget {
  final double width;
  final int totalSteps;
  final int curStep;
  final int startIndex;
  final Color stepCompleteColor;
  final Color currentStepColor;
  final Color inactiveColor;
  final double lineWidth;

  CustomStepper({
    Key? key,
    required this.width,
    required this.curStep,
    required this.startIndex,
    required this.stepCompleteColor,
    required this.totalSteps,
    required this.inactiveColor,
    required this.currentStepColor,
    required this.lineWidth,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      width: width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: _steps(context),
            ),
          ),
          const SizedBox(
              height: 8), // Add some spacing between steps and labels
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: _stepText(context),
          ),
        ],
      ),
    );
  }

  List<Widget> _stepText(BuildContext context) {
    var list = <Widget>[];
    for (int i = startIndex; i <= totalSteps; i++) {
      list.add(
        Padding(
          padding: const EdgeInsetsDirectional.only(start: 8),
          child: Center(
            child: CustomTextContainer(
              textKey: i == 1
                  ? addressKey
                  : i == 2
                      ? paymentKey
                      : placeOrderKey,
              style: i == curStep
                  ? Theme.of(context).textTheme.labelMedium!
                  : Theme.of(context).textTheme.bodySmall!.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .secondary
                          .withValues(alpha: 0.67)),
            ),
          ),
        ),
      );
      if (i != totalSteps) {
        list.add(
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              height: lineWidth,
              color: Colors.transparent,
            ),
          ),
        );
      }
    }

    return list;
  }

  List<Widget> _steps(BuildContext context) {
    var list = <Widget>[];

    for (int i = startIndex; i <= totalSteps; i++) {
      var lineColor = getLineColor(context, i);
      list.add(SizedBox(
        height: 25,
        width: 45,
        child: Center(child: getInnerElementOfStepper(context, i)),
      ));
      if (i != totalSteps) {
        list.add(
          Expanded(
            child: Container(height: lineWidth, color: lineColor),
          ),
        );
      }
    }

    return list;
  }

  Widget getInnerElementOfStepper(BuildContext context, index) {
    if (index < curStep) {
      return Icon(
        Icons.check_circle_outline,
        color: Theme.of(context).colorScheme.primary,
        size: 24.0,
      );
    } else if (index == curStep) {
      return Icon(
        Icons.radio_button_checked,
        color: Theme.of(context).colorScheme.primary,
        size: 24.0,
      );
    } else {
      return Container(
          width: 20,
          height: 20,
          decoration: BoxDecoration(
            border: Border.all(
              width: 2,
              color: Theme.of(context).colorScheme.secondary,
            ),
            shape: BoxShape.circle,
          ));
    }
  }

  getCircleColor(i) {
    if (i < curStep) {
      return stepCompleteColor;
    } else if (i == curStep) {
      return currentStepColor;
    } else {
      return Colors.white;
    }
  }

  getBorderColor(i) {
    if (i < curStep) {
      return stepCompleteColor;
    } else if (i == curStep) {
      return currentStepColor;
    } else {
      return inactiveColor;
    }
  }

  getLineColor(BuildContext context, i) {
    return curStep > i
        ? Theme.of(context).colorScheme.primary
        : Theme.of(context).inputDecorationTheme.iconColor;
  }
}
