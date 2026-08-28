import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';

class CustomStepper extends StatelessWidget {
  final double width;
  final totalSteps;
  final int curStep;
  final Color stepCompleteColor;
  final Color currentStepColor;
  final Color inactiveColor;
  final double lineWidth;
  CustomStepper({
    Key? key,
    required this.width,
    required this.curStep,
    required this.stepCompleteColor,
    required this.totalSteps,
    required this.inactiveColor,
    required this.currentStepColor,
    required this.lineWidth,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: appContentHorizontalPadding),
      width: width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: _steps(context),
          ),
          Row(
            children: _stepText(context),
          ),
        ],
      ),
    );
  }

  List<Widget> _stepText(BuildContext context) {
    var list = <Widget>[];
    for (int i = 1; i <= totalSteps; i++) {
      //colors according to state

      // step circles
      list.add(SizedBox(
        height: 25,
        width: 45,
        child: Center(
            child: Text(
          'Step $i',
          style: Theme.of(context).textTheme.labelMedium!.copyWith(
              color: i == curStep
                  ? Theme.of(context).colorScheme.secondary
                  : Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.67)),
        )),
      ));
      //line between step circles
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

    for (int i = 1; i <= totalSteps; i++) {
      var lineColor = getLineColor(context, i);
      // step circles
      list.add(SizedBox(
        height: 25,
        width: 45,
        child: Center(child: getInnerElementOfStepper(context, i)),
      ));
      //line between step circles
      if (i != totalSteps) {
        list.add(
          Expanded(
            child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                height: lineWidth,
                color: lineColor),
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
    var color;
    if (i < curStep) {
      color = stepCompleteColor;
    } else if (i == curStep)
      color = currentStepColor;
    else
      color = Colors.white;
    return color;
  }

  getBorderColor(i) {
    var color;
    if (i < curStep) {
      color = stepCompleteColor;
    } else if (i == curStep)
      color = currentStepColor;
    else
      color = inactiveColor;

    return color;
  }

  getLineColor(BuildContext context, i) {
    var color = curStep > i
        ? Theme.of(context).colorScheme.primary
        : Theme.of(context).inputDecorationTheme.iconColor;
    return color;
  }
}
