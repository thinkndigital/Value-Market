import 'dart:async';

import 'package:eshop_plus/commons/widgets/customTextButton.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';

class ResendOtpTimerContainer extends StatefulWidget {
  final Function enableResendOtpButton;
  ResendOtpTimerContainer({Key? key, required this.enableResendOtpButton})
      : super(key: key);

  @override
  ResendOtpTimerContainerState createState() => ResendOtpTimerContainerState();
}

class ResendOtpTimerContainerState extends State<ResendOtpTimerContainer> {
  Timer? resendOtpTimer;
  int resendOtpTimeInSeconds = otpTimeOutSeconds - 1;

  void setResendOtpTimer() {
    setState(() {
      resendOtpTimeInSeconds = otpTimeOutSeconds - 1;
    });
    resendOtpTimer = Timer.periodic(Duration(seconds: 1), (timer) {
      if (resendOtpTimeInSeconds == 0) {
        timer.cancel();
        widget.enableResendOtpButton();
      } else {
        resendOtpTimeInSeconds--;
        setState(() {});
      }
    });
  }

  void cancelOtpTimer() {
    resendOtpTimer?.cancel();
  }

  @override
  void dispose() {
    cancelOtpTimer();
    super.dispose();
  }

//to get time to display in text widget
  String getTime() {
    String secondsAsString = resendOtpTimeInSeconds < 10
        ? " 0$resendOtpTimeInSeconds"
        : resendOtpTimeInSeconds.toString();
    return " $secondsAsString";
  }

  @override
  Widget build(BuildContext context) {
    return CustomTextButton(
      buttonTextKey: 'Resend OTP in${getTime()}',
      onTapButton: () {},
      textStyle: Theme.of(context)
          .textTheme
          .labelLarge!
          .copyWith(color: Theme.of(context).colorScheme.primary),
    );
  }
}
