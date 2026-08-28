import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/ui/auth/cubits/otpVerificationCubit.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/ui/auth/repositories/authRepository.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:sms_autofill/sms_autofill.dart';

import '../../../core/routes/routes.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customCircularProgressIndicator.dart';
import '../../../commons/widgets/customTextButton.dart';
import '../../../commons/widgets/customTextContainer.dart';
import '../widgets/loginContainer.dart';
import '../widgets/resendOtpTimerContainer.dart';

class OtpVerificationScreen extends StatefulWidget {
  const OtpVerificationScreen(
      {Key? key,
      required this.mobileNumber,
      required this.countryCode,
      required this.verificationID})
      : super(key: key);
  final String mobileNumber, countryCode, verificationID;
  static Widget getRouteInstance() {
    Map<String, dynamic> arguments = Get.arguments ?? {};
    return BlocProvider(
      create: (context) => OtpVerificationCubit(),
      child: OtpVerificationScreen(
          mobileNumber: arguments['mobileNumber'],
          countryCode: arguments['countryCode'],
          verificationID: arguments['verificationID']),
    );
  }

  @override
  _OtpVerificationScreenState createState() => _OtpVerificationScreenState();
}

class _OtpVerificationScreenState extends State<OtpVerificationScreen> {
  String? _otp = "";
  late String _verificationCode = widget.verificationID;
  bool _enableResendOtpButton = false, _isLoading = false, _codeSent = true;
  bool _isSMSAuthMethod = false;
  final _pinPutController = TextEditingController();
  final GlobalKey<ResendOtpTimerContainerState> resendOtpTimerContainerKey =
      GlobalKey<ResendOtpTimerContainerState>();
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      _isSMSAuthMethod = !context
          .read<SettingsAndLanguagesCubit>()
          .getIsFirebaseAuthentication();
    });
    Future.delayed(const Duration(milliseconds: 75)).then((value) {
      resendOtpTimerContainerKey.currentState?.setResendOtpTimer();
    });
  }

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
        body: BlocConsumer<OtpVerificationCubit, OtpVerificationState>(
          listener: (context, state) {
            if (state is OtpVerificationSuccess) {
              Utils.showSnackBar(
                  context: context, message: state.sucsessMessage);
              Utils.navigateToScreen(context, Routes.createAccountScreen,
                  arguments: {
                    'mobileNumber': widget.mobileNumber,
                    'countryCode': widget.countryCode
                  },
                  replacePrevious: true);
            }
            if (state is OtpVerificationFailure) {
              Utils.showSnackBar(context: context, message: state.errorMessage);
            }
          },
          builder: (context, state) {
            return LoginContainer(
              titleText: enterVerificationCodeKey,
              descriptionText: weHaveSentVerificationCodeToKey,
              buttonText: verifyOtpKey,
              onTapButton: () => onTapVerifyOtpButton(state),
              content: buildContent(),
              footerWidget: buildFooterWidget(),
              buttonWidget: _isLoading || state is OtpVerificationProgress
                  ? const CustomCircularProgressIndicator()
                  : null,
            );
          },
        ),
      ),
    );
  }

  buildFooterWidget() {
    return Align(
      alignment: Alignment.center,
      child: Column(
        children: <Widget>[
          const CustomTextContainer(textKey: didntGetOtpKey),
          _codeSent ? _buildResendText() : const SizedBox.shrink(),
          DesignConfig.defaultHeightSizedBox
        ],
      ),
    );
  }

  buildContent() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text('${widget.countryCode} - ${widget.mobileNumber}',
            style: Theme.of(context).textTheme.labelLarge),
        Padding(
          padding: const EdgeInsetsDirectional.only(top: 25, bottom: 50),
          child: PinFieldAutoFill(
            controller: _pinPutController,
            decoration: BoxLooseDecoration(
              textStyle: Theme.of(context).textTheme.titleLarge,
              radius: Radius.circular(borderRadius),
              gapSpace: 15,
              strokeColorBuilder: PinListenColorBuilder(
                  Theme.of(context).colorScheme.primary,
                  Theme.of(context).inputDecorationTheme.iconColor!),
              bgColorBuilder: const FixedColorBuilder(Colors.transparent),
              strokeWidth: 1,
            ),
            currentCode: _otp,
            autoFocus: false,
            onCodeSubmitted: (String code) {
              _otp = code;
            },
            onCodeChanged: (code) {
              _otp = code;
              if (code!.length == 6) {
                FocusScope.of(context).requestFocus(FocusNode());
              }
            },
          ),
        ),
      ],
    );
  }

  Widget _buildResendText() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        !_enableResendOtpButton
            ? ResendOtpTimerContainer(
                key: resendOtpTimerContainerKey,
                enableResendOtpButton: () {
                  setState(() {
                    _enableResendOtpButton = true;
                  });
                })
            : const SizedBox.shrink(),
        _enableResendOtpButton
            ? CustomTextButton(
                buttonTextKey: resendOtpKey,
                onTapButton: _enableResendOtpButton
                    ? () async {
                        setState(() {
                          _isLoading = false;
                          _enableResendOtpButton = false;
                        });
                        if (_isSMSAuthMethod) {
                          AuthRepository().resendOtp(params: {
                            ApiURL.mobileApiKey: widget.mobileNumber,
                          });
                        } else {
                          signInWithPhoneNumber();
                        }
                      }
                    : null,
                textStyle: Theme.of(context)
                    .textTheme
                    .labelLarge!
                    .copyWith(color: Theme.of(context).colorScheme.primary),
              )
            : const SizedBox.shrink(),
      ],
    );
  }

  void signInWithPhoneNumber() async {
    try {
      await FirebaseAuth.instance
          .verifyPhoneNumber(
        timeout: const Duration(seconds: 10),
        phoneNumber: '${widget.countryCode}${widget.mobileNumber}',
        verificationCompleted: (PhoneAuthCredential credential) async {},
        verificationFailed: (FirebaseAuthException e) {
          //if otp code does not verify

          if (e.code == 'invalid-phone-number') {
            Utils.showSnackBar(
                context: context, message: invalidMobileErrorMsgKey);
          } else if (e.code == 'network-request-failed') {
            Utils.showSnackBar(context: context, message: noInternetKey);
          } else {
            Utils.showSnackBar(
                context: context, message: verificationErrorMessageKey);
          }
          setState(() {
            _isLoading = false;
          });
        },
        codeSent: (String verificationId, int? resendToken) {
          setState(() {
            _codeSent = true;
            _verificationCode = verificationId;
            _isLoading = false;
          });
          Future.delayed(const Duration(milliseconds: 75)).then((value) {
            resendOtpTimerContainerKey.currentState?.setResendOtpTimer();
          });
        },
        codeAutoRetrievalTimeout: (String verificationId) {},
      )
          .onError((error, stackTrace) {
        Utils.showSnackBar(context: context, message: defaultErrorMessageKey);

        setState(() {
          _isLoading = false;
        });
      });
    } catch (e) {}
  }

  void onTapVerifyOtpButton(OtpVerificationState state) async {
    if (_isSMSAuthMethod &&
        _pinPutController.text.isNotEmpty &&
        state is! OtpVerificationProgress) {
      context.read<OtpVerificationCubit>().verifyOtp(params: {
        ApiURL.otpApiKey: _pinPutController.text.trim(),
        ApiURL.mobileApiKey: widget.mobileNumber,
      });
    } else {
      if (_isLoading) return;

      if (_pinPutController.text.isNotEmpty) {
        setState(() {
          _isLoading = true;
        });

        await FirebaseAuth.instance
            .signInWithCredential(PhoneAuthProvider.credential(
                verificationId: _verificationCode,
                smsCode: _pinPutController.text))
            .then((value) async {
          try {
            resendOtpTimerContainerKey.currentState?.cancelOtpTimer();
            if (value.user != null) {
              setState(() {
                _isLoading = false;
              });
              Utils.navigateToScreen(context, Routes.createAccountScreen,
                  arguments: {
                    'mobileNumber': widget.mobileNumber,
                    'countryCode': widget.countryCode
                  },
                  replacePrevious: true);
            }
          } catch (e) {
            setState(() {
              _isLoading = false;
            });
            FocusScope.of(context).unfocus();
          }
        }).onError((error, stackTrace) {
          Utils.showSnackBar(
              context: context, message: invalidOtpMessageErrorMsgKey);

          setState(() {
            _isLoading = false;
            _pinPutController.text = '';
          });
        });
      }
    }
  }
}
