import 'dart:io';

import 'package:dio/dio.dart';
import 'package:value_market_customer/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/ui/auth/cubits/authCubit.dart';
import 'package:value_market_customer/commons/blocs/updateUserCubit.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customRoundedButton.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/commons/widgets/customTextFieldContainer.dart';
import 'package:value_market_customer/commons/widgets/error_screen.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';

import '../../commons/blocs/userDetailsCubit.dart';
import '../../core/localization/labelKeys.dart';
import '../../utils/utils.dart';
import '../../utils/validator.dart';

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({
    super.key,
  });
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => UpdateUserCubit(),
        child: const EditProfileScreen(),
      );
  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  Map<String, TextEditingController> controllers = {};
  Map<String, FocusNode> focusNodes = {};
  File? _selectedImage;
  String _mimeType = '';
  final ImagePicker _picker = ImagePicker();

  final List formFields = [
    usernameKey,
    phoneNumberKey,
    emailKey,
    referralCodeKey
  ];
  @override
  void initState() {
    super.initState();
    _selectedImage = null;
    formFields.forEach((key) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    });
    setFormFieldValues();
  }

  @override
  void dispose() {
    controllers.forEach((key, controller) {
      controller.dispose();
    });
    focusNodes.forEach((key, focusNode) {
      focusNode.dispose();
    });
    super.dispose();
  }

  void setFormFieldValues() {
    Future.delayed(Duration.zero, () {
      controllers[usernameKey]!.text =
          context.read<UserDetailsCubit>().getUserName() ?? '';
      controllers[emailKey]!.text =
          context.read<UserDetailsCubit>().getUserEmail();
      controllers[phoneNumberKey]!.text =
          context.read<UserDetailsCubit>().getUserMobile();
      controllers[referralCodeKey]!.text =
          context.read<UserDetailsCubit>().getReferalCode();
    });
  }

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
        appBar: const CustomAppbar(titleKey: profileKey),
        body: BlocBuilder<UserDetailsCubit, UserDetailsState>(
          bloc: context.read<UserDetailsCubit>(),
          builder: (context, state) {
            if (state is UserDetailsFetchSuccess) {
              return ListView(
                  padding: const EdgeInsetsDirectional.symmetric(
                      horizontal: appContentHorizontalPadding,
                      vertical: appContentHorizontalPadding * 2),
                  children: [
                    userImageWidget(state.userDetails.image ?? ''),
                    const SizedBox(
                      height: 24,
                    ),
                    formWidget(),
                  ]);
            } else if (state is UserDetailsFetchFailure) {
              return ErrorScreen(
                  text: state.errorMessage,
                  onPressed: () => context
                      .read<UserDetailsCubit>()
                      .fetchUserDetails(
                          params: Utils.getParamsForVerifyUser(context)));
            }
            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  formWidget() {
    return Form(
        key: _formKey,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          CustomTextFieldContainer(
            labelKey: usernameKey,
            hintTextKey: usernameKey,
            textEditingController: controllers[usernameKey]!,
            focusNode: focusNodes[usernameKey],
            textInputAction: TextInputAction.next,
            validator: (v) => Validator.validateName(context, v),
            isFieldValueMandatory: true,
            onFieldSubmitted: (v) {
              FocusScope.of(context).requestFocus(focusNodes[emailKey]);
            },
          ),
          CustomTextFieldContainer(
            labelKey: emailKey,
            hintTextKey: emailKey,
            readOnly: context.read<AuthCubit>().getUserDetails().type !=
                    phoneLoginType
                ? true
                : false,
            textEditingController: controllers[emailKey]!,
            focusNode: focusNodes[emailKey],
            textInputAction: TextInputAction.next,
            keyboardType: TextInputType.emailAddress,
            validator: (v) => Validator.validateEmail(context, v),
            isFieldValueMandatory: true,
            onFieldSubmitted: (v) {
              FocusScope.of(context).requestFocus(focusNodes[phoneNumberKey]);
            },
          ),
          CustomTextFieldContainer(
            labelKey: phoneNumberKey,
            hintTextKey: phoneNumberKey,
            readOnly: context.read<AuthCubit>().getUserDetails().type ==
                    phoneLoginType
                ? true
                : false,
            textEditingController: controllers[phoneNumberKey]!,
            focusNode: focusNodes[phoneNumberKey],
            textInputAction: TextInputAction.next,
            keyboardType: TextInputType.phone,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly, // Allow only digits
              LengthLimitingTextInputFormatter(15), // Limit to 15 digits
            ],
            validator: (v) => Validator.validatePhoneNumber(v, context),
            isFieldValueMandatory: true,
            onFieldSubmitted: (v) {
              focusNodes[phoneNumberKey]!.unfocus();
            },
          ),
          CustomTextFieldContainer(
            labelKey: referralCodeKey,
            hintTextKey: referralCodeKey,
            textEditingController: controllers[referralCodeKey]!,
            focusNode: focusNodes[referralCodeKey],
            textInputAction: TextInputAction.done,
            keyboardType: TextInputType.text,
            isFieldValueMandatory: false,
            readOnly: true,
            onFieldSubmitted: (v) {
              focusNodes[referralCodeKey]!.unfocus();
            },
          ),
          const SizedBox(
            height: appContentVerticalSpace * 2,
          ),
          BlocConsumer<UpdateUserCubit, UpdateUserState>(
            listener: (context, state) {
              if (state is UpdateUserFetchFailure) {
                Utils.showSnackBar(
                    context: context, message: state.errorMessage);
              }
              if (state is UpdateUserFetchSuccess) {
                Utils.showSnackBar(
                    context: context, message: state.successMessage);
                context.read<UserDetailsCubit>().emitUserSuccessState(
                    state.userDetails.toJson(),
                    (context.read<UserDetailsCubit>().state
                            as UserDetailsFetchSuccess)
                        .token);
                Navigator.of(context).pop();
              }
            },
            builder: (context, state) {
              return CustomRoundedButton(
                  widthPercentage: 1.0,
                  buttonTitle: saveKey,
                  showBorder: false,
                  child: state is UpdateUserFetchInProgress
                      ? const CustomCircularProgressIndicator()
                      : null,
                  onTap: () async {
                    if (state is! UpdateUserFetchInProgress) {
                      if (_formKey.currentState!.validate()) {
                        Map<String, dynamic> params = {
                          ApiURL.userIdApiKey:
                              context.read<UserDetailsCubit>().getUserId(),
                          ApiURL.usernameApiKey:
                              controllers[usernameKey]!.text.trim(),
                          ApiURL.emailApiKey:
                              controllers[emailKey]!.text.trim(),
                          ApiURL.mobileApiKey:
                              controllers[phoneNumberKey]!.text.trim(),
                          ApiURL.referralCodeApiKey:
                              controllers[referralCodeKey]!.text.trim(),
                        };
                        if (_selectedImage != null) {
                          MultipartFile file = await MultipartFile.fromFile(
                            _selectedImage!.path,
                            contentType: DioMediaType('image', _mimeType),
                          );
                          params.addAll({'image': file});
                        }
                        if (Utils.isDemoUser(context)) {
                          Utils.showSnackBar(
                              context: context,
                              message: 'Modification not allowed in demo app');
                          return;
                        }
                        context
                            .read<UpdateUserCubit>()
                            .updateUser(params: params);
                      }
                    }
                  });
            },
          )
        ]));
  }

  userImageWidget(String imageUrl) {
    return Center(
      child: Stack(
        children: [
          ClipOval(
              child: Utils.buildProfilePicture(context, 124, imageUrl,
                  selectedFile: _selectedImage,
                  assetImage: _selectedImage != null,
                  outerBorderColor: Colors.transparent)),
          Positioned(
            bottom: 0,
            right: 10,
            child: GestureDetector(
              onTap: _showPicker,
              child: Container(
                decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                        width: 2,
                        color: Theme.of(context).colorScheme.onPrimary)),
                child: CircleAvatar(
                  radius: 17,
                  backgroundColor: Theme.of(context).colorScheme.primary,
                  child: Icon(
                    Icons.camera_alt_outlined,
                    size: 24,
                    color: Theme.of(context).colorScheme.onPrimary,
                  ),
                ),
              ),
            ),
          )
        ],
      ),
    );
  }

  Future<void> _pickImage(ImageSource source) async {
    final XFile? pickedFile = await _picker.pickImage(source: source);

    if (pickedFile != null) {
      String fileName = pickedFile.path.split('/').last;
      String fileExtension = fileName.split('.').last.toLowerCase();

      switch (fileExtension) {
        case 'jpeg':
        case 'jpg':
          _mimeType = 'image/jpeg';
          break;
        case 'png':
          _mimeType = 'image/png';
          break;
        case 'gif':
          _mimeType = 'image/gif';
          break;
        default:
          // Handle unsupported file types
          Utils.showSnackBar(
              message: 'Unsupported file type', context: context);
          _mimeType = '';
          return;
      }

      setState(() {
        _selectedImage = File(pickedFile.path);
      });
    } else {
      // User canceled the picker
    }
  }

  /// Selection dialog that prompts the user to select an existing photo or take a new one
  void _showPicker() {
    Utils.openModalBottomSheet(
        context,
        SafeArea(
          child: Wrap(
            children: <Widget>[
              ListTile(
                leading: const Icon(Icons.photo_library),
                title: const CustomTextContainer(textKey: 'Photo Library'),
                onTap: () {
                  _pickImage(ImageSource.gallery);
                  Navigator.of(context).pop();
                },
              ),
              ListTile(
                leading: const Icon(Icons.photo_camera),
                title: const CustomTextContainer(textKey: 'Camera'),
                onTap: () {
                  _pickImage(ImageSource.camera);
                  Navigator.of(context).pop();
                },
              ),
            ],
          ),
        ),
        isScrollControlled: false,
        staticContent: true);
  }
}
