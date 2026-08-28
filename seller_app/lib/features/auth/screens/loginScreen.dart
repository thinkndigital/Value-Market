import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/blocs/userDetailsCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/features/auth/blocs/authCubit.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/routes/routes.dart';
import '../blocs/signInCubit.dart';
import '../../../commons/blocs/storesCubit.dart';
import '../repositories/authRepository.dart';
import '../../../utils/utils.dart';
import '../../../commons/widgets/customCircularProgressIndicator.dart';
import '../../../commons/widgets/customTextFieldContainer.dart';
import '../../../commons/widgets/showHidePasswordButton.dart';
import '../widgets/loginContainer.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({Key? key}) : super(key: key);

  @override
  _LoginScreenState createState() => _LoginScreenState();
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => SignInCubit(),
        child: const LoginScreen(),
      );
}

class _LoginScreenState extends State<LoginScreen> {
  final TextEditingController _mobileController =
      TextEditingController(text: isDemoApp ? '8140535858' : null);
  final TextEditingController _passwordController =
      TextEditingController(text: isDemoApp ? '12345678' : null);
  bool _hidePassword = true;
  FocusNode? passwordFocus = FocusNode(), mobileFocus = FocusNode();

  final GlobalKey<FormState> _formkey = GlobalKey<FormState>();
  @override
  initState() {
    super.initState();
    context.read<StoresCubit>().resetState();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => FocusScope.of(context).unfocus(),
      child: Scaffold(
          body: BlocConsumer<SignInCubit, SignInState>(
        listener: (context, state) {
          if (state is SignInSuccess) {
            if (state.userDetails.active == '0') {
              Utils.showSnackBar(message: deactivatedErrorMessageKey);
              return;
            }

            context.read<AuthCubit>().authenticateUser(
                userDetails: state.userDetails, token: state.token);
            context
                .read<UserDetailsCubit>()
                .emitUserSuccessState(state.userDetails.toJson(), state.token);
            context.read<StoresCubit>().fetchStores();
            context
                .read<SettingsAndLanguagesCubit>()
                .fetchSettings(state.userDetails.id.toString());
            FocusScope.of(context).unfocus();
            Utils.navigateToScreen(context, Routes.mainScreen,
                replaceAll: true);
          }
          if (state is SignInFailure) {
            Utils.showSnackBar(message: state.errorMessage);
          }
        },
        builder: (context, state) {
          return LoginContainer(
            titleText: welcomeBackKey,
            descriptionText: pleaseEnterLoginDetailsKey,
            buttonText: signInKey,
            onTapButton: state is SignInProgress ? () {} : callSignInApi,
            buttonWidget: state is SignInProgress
                ? const CustomCircularProgressIndicator()
                : null,
            content: buildContentWidget(),
            footerWidget: buildFooterWidget(),
          );
        },
      )),
    );
  }

  Widget buildContentWidget() {
    return Form(
      key: _formkey,
      autovalidateMode: AutovalidateMode.onUserInteraction,
      child: Padding(
        padding: const EdgeInsets.only(top: 25),
        child: Column(
          children: <Widget>[
            CustomTextFieldContainer(
              hintTextKey: mobileNumberKey,
              textEditingController: _mobileController,
              labelKey: '',
              keyboardType: TextInputType.number,
              focusNode: mobileFocus,
              textInputAction: TextInputAction.next,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly, // Allow only digits
                LengthLimitingTextInputFormatter(15), // Limit to 15 digits
              ],
              validator: (v) => Validator.validatePhoneNumber(v, context),
              prefixWidget: const Icon(Icons.call_outlined),
              onFieldSubmitted: (v) {
                FocusScope.of(context).requestFocus(passwordFocus);
              },
            ),
            CustomTextFieldContainer(
              hintTextKey: passwordKey,
              textEditingController: _passwordController,
              labelKey: '',
              prefixWidget: const Icon(Icons.lock_outline),
              hideText: _hidePassword,
              keyboardType: TextInputType.text,
              focusNode: passwordFocus,
              textInputAction: TextInputAction.done,
              maxLines: 1,
              inputFormatters: [
                FilteringTextInputFormatter.deny(RegExp('[ ]')),
              ],
              suffixWidget: ShowHidePasswordButton(
                hidePassword: _hidePassword,
                onTapButton: () {
                  setState(() {
                    _hidePassword = !_hidePassword;
                  });
                },
              ),
              onFieldSubmitted: (v) {
                passwordFocus!.unfocus();
              },
            ),
            const SizedBox(
              height: 15,
            ),
            Align(
                alignment: Alignment.bottomRight,
                child: CustomTextButton(
                  buttonTextKey: forgotPasswordKey,
                  onTapButton: () {
                    Utils.navigateToScreen(
                        context, Routes.forgotPasswordScreen);
                  },
                  textStyle: Theme.of(context).textTheme.bodyLarge!.copyWith(
                      color: Theme.of(context).colorScheme.primary,
                      fontWeight: FontWeight.w400),
                ))
          ],
        ),
      ),
    );
  }

  void callSignInApi() async {
    FocusScope.of(context).unfocus();

    if (_formkey.currentState!.validate()) {
      {
        String fcmId = await AuthRepository.getFcmToken();
        context.read<SignInCubit>().login(params: {
          ApiURL.mobileApiKey: _mobileController.text.trim(),
          ApiURL.passwordApiKey: _passwordController.text.trim(),
          ApiURL.fcmIdApiKey: fcmId
        });
      }
    }
  }

  buildFooterWidget() {
    return Align(
      alignment: Alignment.center,
      child: Text.rich(
        TextSpan(
          children: [
            TextSpan(
              text: context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: dontHaveAccountKey),
            ),
            const TextSpan(
              text: ' ',
            ),
            TextSpan(
                text: context
                    .read<SettingsAndLanguagesCubit>()
                    .getTranslatedValue(labelKey: signUpKey),
                style: TextStyle(
                  color: Theme.of(context).colorScheme.primary,
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
                recognizer: TapGestureRecognizer()
                  ..onTap = () => Utils.navigateToScreen(
                      context, Routes.signupScreen,
                      arguments: false)),
          ],
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}
