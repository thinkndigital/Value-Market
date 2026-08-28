import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/models/language.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/mediaListCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/addBrandCubit.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';

class AddBrandScreen extends StatefulWidget {
  const AddBrandScreen({Key? key}) : super(key: key);

  @override
  State<AddBrandScreen> createState() => _AddBrandScreenState();
  static Widget getRouteInstance() {
    return MultiBlocProvider(
      providers: [
        BlocProvider(create: (context) => MediaListCubit()),
        BlocProvider(create: (context) => AddBrandCubit()),
      ],
      child: AddBrandScreen(),
    );
  }
}

class _AddBrandScreenState extends State<AddBrandScreen> {
  late Language selectedLang;
  final Map<String, TextEditingController> nameControllers = {};
  Map selectedImage = {};
  final _formKey = GlobalKey<FormState>();
  List<Language> langs = [];

  @override
  void initState() {
    super.initState();

    langs = context.read<SettingsAndLanguagesCubit>().getLanguages();
    for (var lang in langs) {
      nameControllers[lang.code!] = TextEditingController();
    }
    selectedLang =
        langs.firstWhere((e) => e.code == 'en', orElse: () => langs.first);
    Future.delayed(Duration.zero, () {
      context
          .read<MediaListCubit>()
          .getMediaList(context, {"type": mediaTypeImage}, isSetInitial: true);
    });
  }

  @override
  void dispose() {
    for (var c in nameControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppbar(titleKey: addBrandKey),
      bottomNavigationBar: buildBottomBar(),
      body: Column(
        children: [
          SizedBox(height: 12),
          Expanded(
            child: CustomDefaultContainer(
              child: Form(
                key: _formKey,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.max,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      DesignConfig.smallHeightSizedBox,
                      Utils.buildLanguagesWidget(
                          context: context,
                          selectedLang: selectedLang,
                          onSelect: (Language lang) {
                            setState(() {
                              selectedLang = lang;
                            });
                          }),
                      DesignConfig.defaultHeightSizedBox,
                      CustomTextFieldContainer(
                        hintTextKey: brandKey,
                        textEditingController:
                            nameControllers[selectedLang.code]!,
                        labelKey: nameKey,
                        isFieldValueMandatory:
                            selectedLang.code == englishLangCode ? true : false,
                        isSetValidator:
                            selectedLang.code == englishLangCode ? true : false,
                      ),
                      DesignConfig.defaultHeightSizedBox,
                      Utils.buildImageUploadWidget(
                          context: context,
                          labelKey: imageKey,
                          file: null,
                          imgurl: selectedImage.isNotEmpty
                              ? selectedImage.values.first
                              : '',
                          onTapUpload: () {
                            openImageMediaSelection();
                          },
                          onTapClose: () {
                            selectedImage.clear();
                            setState(() {});
                          }),
                      DesignConfig.defaultHeightSizedBox,
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  buildBottomBar() {
    return BlocConsumer<AddBrandCubit, AddBrandState>(
      listener: (context, state) {
        if (state is AddBrandSuccess) {
          Utils.showSnackBar(message: state.message);
          Navigator.of(context).pop();
        } else if (state is AddBrandFailure) {
          Utils.showSnackBar(message: state.error);
        }
      },
      builder: (context, state) {
        return CustomBottomButtonContainer(
          child: Row(
            children: <Widget>[
              Expanded(
                child: CustomRoundedButton(
                  widthPercentage: 1,
                  buttonTitle: resetKey,
                  showBorder: true,
                  backgroundColor: Theme.of(context).colorScheme.onPrimary,
                  borderColor: Theme.of(context).inputDecorationTheme.iconColor,
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium!
                      .copyWith(color: Theme.of(context).colorScheme.secondary),
                  onTap: () {
                    _formKey.currentState?.reset();
                  },
                ),
              ),
              DesignConfig.defaultWidthSizedBox,
              Expanded(
                child: CustomRoundedButton(
                  widthPercentage: 1,
                  buttonTitle: submitKey,
                  showBorder: false,
                  onTap: state is AddBrandInProgress
                      ? null
                      : () {
                          if (_formKey.currentState?.validate() ?? false) {
                            if (selectedImage.isEmpty) {
                              Utils.showSnackBar(
                                  message: uploadImageWarningKey);
                              return;
                            }
                            Map<String, String> names = {};
                            for (var lang in langs) {
                              names[lang.code!] =
                                  nameControllers[lang.code]?.text ?? '';
                            }

                            context.read<AddBrandCubit>().addBrand(
                                  storeId: context
                                      .read<StoresCubit>()
                                      .getDefaultStore()
                                      .id
                                      .toString(),
                                  names: names,
                                  imagePath: selectedImage.values.first,
                                );
                          } else {
                            Utils.showSnackBar(
                                message: pleaseEnterRequiredFieldsKey);
                          }
                        },
                  child: state is AddBrandInProgress
                      ? CustomCircularProgressIndicator()
                      : null,
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  openImageMediaSelection() {
    Utils.navigateToScreen(context, Routes.mediaListScreen, arguments: {
      'mediaListCubit': context.read<MediaListCubit>(),
      'mediaType': mediaTypeImage,
      'isMultipleSelect': false,
      "onMediaSelect": (Map<String, String> path) {
        if (path.isNotEmpty) {
          selectedImage.addAll({path.keys.first: path.values.first});

          setState(() {});
        }
      }
    });
  }
}
