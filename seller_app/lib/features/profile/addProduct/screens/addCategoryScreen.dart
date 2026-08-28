import 'dart:io';

import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/addCategoryCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/categoryListCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/blocs/mediaListCubit.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/category.dart';
import 'package:eshopplus_seller/features/profile/addProduct/widgets/helper/categorySelectionDialog.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/models/language.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';

class AddCategoryScreen extends StatefulWidget {
  const AddCategoryScreen({Key? key}) : super(key: key);

  @override
  State<AddCategoryScreen> createState() => _AddCategoryScreenState();
  static Widget getRouteInstance() {
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => AddCategoryCubit(),
        ),
        BlocProvider(create: (context) => MediaListCubit()),
        BlocProvider(
          create: (context) => CategoryListCubit(),
        ),
      ],
      child: AddCategoryScreen(),
    );
  }
}

class _AddCategoryScreenState extends State<AddCategoryScreen> {
  late Language selectedLang;
  List<Language> langs = [];
  final Map<String, TextEditingController> nameControllers = {};
  final TextEditingController categoryController = TextEditingController();
  Category? selectedCategory;
  String? imagePath;
  String? bannerPath;
  final _formKey = GlobalKey<FormState>();
  Map selectedImage = {}, selectedBanner = {};

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
      context.read<CategoryListCubit>().getCategoryList(context, {
        ApiURL.storeIdApiKey:
            context.read<StoresCubit>().getDefaultStore().id.toString(),
      });
    });
  }

  @override
  void dispose() {
    for (var c in nameControllers.values) {
      c.dispose();
    }
    categoryController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
        appBar: CustomAppbar(titleKey: addCategoryKey),
        bottomNavigationBar: buildBottomBar(),
        body: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(height: 12),
            Expanded(
              child: Form(
                key: _formKey,
                child: CustomDefaultContainer(
                  child: SingleChildScrollView(
                    child: Column(
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
                          hintTextKey: nameKey,
                          textEditingController:
                              nameControllers[selectedLang.code]!,
                          labelKey: nameKey,
                          isFieldValueMandatory:
                              selectedLang.code == englishLangCode
                                  ? true
                                  : false,
                          isSetValidator: selectedLang.code == englishLangCode
                              ? true
                              : false,
                        ),
                        DesignConfig.defaultHeightSizedBox,
                        buildCategoryWidget(),
                        DesignConfig.defaultHeightSizedBox,
                        Row(
                          children: [
                            Expanded(
                              child: Utils.buildImageUploadWidget(
                                  context: context,
                                  labelKey: imageKey,
                                  file: null,
                                  imgurl: selectedImage.isNotEmpty
                                      ? selectedImage.values.first
                                      : '',
                                  onTapUpload: () {
                                    openImageMediaSelection(selectedImage);
                                  },
                                  onTapClose: () {
                                    selectedImage.clear();
                                    setState(() {});
                                  }),
                            ),
                            DesignConfig.defaultWidthSizedBox,
                            Expanded(
                              child: Utils.buildImageUploadWidget(
                                  context: context,
                                  labelKey: bannerKey,
                                  file: null,
                                  imgurl: selectedBanner.isNotEmpty
                                      ? selectedBanner.values.first
                                      : '',
                                  onTapUpload: () {
                                    openImageMediaSelection(selectedBanner);
                                  },
                                  onTapClose: () {
                                    selectedBanner.clear();
                                    setState(() {});
                                  }),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  openImageMediaSelection(Map imageMap) {
    FocusScope.of(context).unfocus();
    Utils.navigateToScreen(context, Routes.mediaListScreen, arguments: {
      'mediaListCubit': context.read<MediaListCubit>(),
      'mediaType': mediaTypeImage,
      'isMultipleSelect': false,
      "onMediaSelect": (Map<String, String> path) {
        if (path.isNotEmpty) {
          imageMap.addAll({path.keys.first: path.values.first});

          setState(() {});
        }
      }
    });
  }

  buildBottomBar() {
    return BlocConsumer<AddCategoryCubit, AddCategoryState>(
      listener: (context, state) {
        if (state is AddCategorySuccess) {
          Utils.showSnackBar(message: state.message);
          Navigator.of(context).pop();
        } else if (state is AddCategoryFailure) {
          Utils.showSnackBar(message: state.error);
        }
      },
      builder: (context, state) {
        return CustomBottomButtonContainer(
          bottomPadding:  Platform.isIOS ? 10 : 0,
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
                  onTap: state is AddCategoryInProgress
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

                            context.read<AddCategoryCubit>().addCategory(
                                  storeId: context
                                      .read<StoresCubit>()
                                      .getDefaultStore()
                                      .id
                                      .toString(),
                                  names: names,
                                  parentId: selectedCategory != null
                                      ? selectedCategory!.id.toString()
                                      : null,
                                  imagePath: selectedImage.values.first,
                                  bannerPath: selectedBanner.values.first,
                                );
                          } else {
                            Utils.showSnackBar(
                                message: pleaseEnterRequiredFieldsKey);
                          }
                        },
                  child: state is AddCategoryInProgress
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

  buildCategoryWidget() {
    return BlocBuilder<CategoryListCubit, CategoryListState>(
      builder: (context, state) {
        if (state is CategoryListFetchSuccess) {
          return CustomTextFieldContainer(
            hintTextKey: selectCategoryKey,
            textEditingController: categoryController,
            labelKey: selectCategoryForSubCatKey,
            textInputAction: TextInputAction.next,
            onFieldSubmitted: null,
            focusNode: AlwaysDisabledFocusNode(),
            isSetValidator: false,
            errmsg: selectCategoryKey,
            isFieldValueMandatory: false,
            suffixWidget: const Icon(Icons.arrow_drop_down),
            onTap: () {
              if (state.categoryList.isEmpty) return;
              showDialog(
                context: context,
                barrierDismissible: false,
                builder: (BuildContext mcontext) {
                  return AlertDialog(
                    insetPadding:
                        const EdgeInsets.all(appContentHorizontalPadding),
                    backgroundColor:
                        Theme.of(context).colorScheme.primaryContainer,
                    contentPadding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    shape: DesignConfig.setRoundedBorder(
                        Theme.of(context).colorScheme.primaryContainer,
                        10,
                        false),
                    content: CategorySelectionDialog(
                        categorylist: state.categoryList,
                        isMultiSelect: false,
                        selectedId: selectedCategory?.id.toString(),
                        onCategorySelect: (List<Category> category) {
                          selectedCategory = category.first;
                          categoryController.text = category.first.name;

                          Navigator.pop(context);
                        }),
                  );
                },
              );
            },
          );
        }
        if (state is CategoryListFetchFailure) {
          return ErrorScreen(
            text: categoryNotAddedToProfileKey,
            child: state is CategoryListFetchProgress
                ? CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.primary)
                : null,
            onPressed: () {
              context.read<CategoryListCubit>().getCategoryList(context, {
                ApiURL.storeIdApiKey:
                    context.read<StoresCubit>().getDefaultStore().id.toString(),
              });
            },
          );
        } else {
          return const SizedBox.shrink();
        }
      },
    );
  }
}
