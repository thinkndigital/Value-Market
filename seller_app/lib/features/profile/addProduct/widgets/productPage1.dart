import 'package:dio/dio.dart';
import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/models/language.dart';
import 'package:eshopplus_seller/commons/models/product.dart';
import 'package:eshopplus_seller/commons/models/productVariant.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customImageWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/brand.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/category.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/country.dart';
import 'package:eshopplus_seller/features/profile/addProduct/widgets/aiPromptField.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'package:eshopplus_seller/commons/widgets/customDropDownContainer.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:get/get.dart' hide MultipartFile;

import '../blocs/brandListCubit.dart';
import '../blocs/categoryListCubit.dart';
import '../blocs/countryListCubit.dart';
import '../blocs/getProductByTypeCubit.dart';
import '../../../../commons/blocs/storesCubit.dart';

import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import '../../../../../utils/designConfig.dart';
import '../../../../core/theme/colors.dart';
import '../../../../commons/widgets/customTextFieldContainer.dart';
import '../screens/addProductScreen.dart';
import 'helper/brandSelectionDialog.dart';
import 'helper/categorySelectionDialog.dart';
import 'helper/comboProductSelectionDialog.dart';
import 'helper/countrySelectionDialog.dart';
import 'helper/helperWidgets.dart';
import 'helper/productSelectionDialog.dart';
import 'package:flutter/services.dart';
import 'package:flex_color_picker/flex_color_picker.dart';

class ProductPage1 extends StatefulWidget {
  final Map<String, TextEditingController> controllers;
  final Map<String, TextEditingController> nameControllers;
  final Map<String, TextEditingController> descControllers;
  final Map<String, dynamic> files;
  final Map<String, FocusNode> focusNodes;
  final Map<String, ProductVariant> selectedProducts;
  final Map<String, Product> selectedSimilarProducts;
  final Function? changeProductType;
  final Product? product;
  const ProductPage1(
      {super.key,
      required this.files,
      required this.selectedSimilarProducts,
      required this.selectedProducts,
      required this.controllers,
      required this.nameControllers,
      required this.descControllers,
      required this.focusNodes,
      required this.changeProductType,
      this.product});

  @override
  _ProductPage1State createState() => _ProductPage1State();
}

class _ProductPage1State extends State<ProductPage1> {
  late BuildContext dialogContext;
  TextEditingController edttags = TextEditingController();
  FocusNode tagfocus = FocusNode();
  late Language selectedLang;

  @override
  void initState() {
    super.initState();

    selectedLang = context
        .read<SettingsAndLanguagesCubit>()
        .getLanguages()
        .firstWhere((e) => e.code == 'en');
    // Initialize

    if (widget.controllers[tagsKey]!.text.trim().isNotEmpty) {
      taglist.clear();
      taglist.addAll(widget.controllers[tagsKey]!.text.split(","));
    }
  }

  @override
  Widget build(BuildContext context) {
    final store = context.read<StoresCubit>().getDefaultStore();
    List<Widget> customFieldsWidgets = [];
    if (store.customFields.isNotEmpty) {
      customFieldsWidgets.addAll([
        ...store.customFields.map((field) {
          final controller = widget.controllers['custom_field_${field.id}'] ??=
              TextEditingController();
          switch (field.type) {
            case 'text':
              return CustomTextFieldContainer(
                labelKey: field.name,
                textEditingController: controller,
                isFieldValueMandatory: field.required,
                hintTextKey: '',
                validator: field.required
                    ? (v) => Validator.emptyValueValidation(v, context)
                    : null,
              );
            case 'number':
              return CustomTextFieldContainer(
                labelKey: field.name,
                textEditingController: controller,
                isFieldValueMandatory: field.required,
                keyboardType: TextInputType.number,
                hintTextKey: '',
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  TextInputFormatter.withFunction((oldValue, newValue) {
                    if (newValue.text.isEmpty) return newValue;
                    final intValue = int.tryParse(newValue.text);
                    if (intValue == null) return oldValue;
                    if (field.min != null && intValue < (field.min as int))
                      return oldValue;
                    if (field.max != null && intValue > (field.max as int))
                      return oldValue;
                    return newValue;
                  })
                ],
                validator: (value) {
                  if (field.required && (value == null || value.isEmpty)) {
                    return 'Required';
                  }
                  final intValue = int.tryParse(value ?? '');
                  // if (intValue == null ) return 'Invalid number';

                  if (intValue != null) {
                    if (field.min != null && intValue < (field.min as int))
                      return 'Min: ${field.min}';
                    if (field.max != null && intValue > (field.max as int))
                      return 'Max: ${field.max}';
                  }
                  return null;
                },
              );
            case 'textarea':
              return CustomTextFieldContainer(
                labelKey: field.name,
                textEditingController: controller,
                isFieldValueMandatory: field.required,
                minLines: 3,
                maxLines: field.fieldLength ?? 5,
                hintTextKey: '',
              );
            case 'color':
              Color? parseHexColor(String hex) {
                try {
                  String cleaned = hex.replaceAll('#', '').replaceAll('0x', '');
                  if (cleaned.length == 6) cleaned = 'FF$cleaned';
                  return Color(int.parse(cleaned, radix: 16));
                } catch (_) {
                  return Colors.black;
                }
              }
              Color? currentColor = controller.text.isNotEmpty
                  ? parseHexColor(controller.text)
                  : Colors.transparent;
              return Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Expanded(
                    child: CustomTextFieldContainer(
                      labelKey: field.name,
                      textEditingController: controller,
                      isFieldValueMandatory: field.required,
                      hintTextKey: '',
                      readOnly: true,
                      validator: field.required
                          ? (v) => Validator.emptyValueValidation(v, context)
                          : null,
                      onTap: () async {
                        final pickedColor = await showColorPickerDialog(
                          context,
                          currentColor ?? Colors.black,
                          title: const Text('Pick a color'),
                          enableOpacity: false,
                        );
                        controller.text =
                            '#${pickedColor.value32bit.toRadixString(16).padLeft(8, '0').substring(2)}';
                        setState(() {});
                      },
                    ),
                  ),
                  if (currentColor != null) ...[
                    DesignConfig.smallWidthSizedBox,
                    GestureDetector(
                      onTap: () async {
                        final pickedColor = await showColorPickerDialog(
                            context, currentColor,
                            enableOpacity: false,
                            backgroundColor:
                                Theme.of(context).colorScheme.primaryContainer);
                        controller.text =
                            '#${pickedColor.value32bit.toRadixString(16).padLeft(8, '0').substring(2)}';
                        setState(() {});
                      },
                      child: Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          color: currentColor,
                          border: Border.all(color: Colors.transparent),
                          borderRadius: BorderRadius.circular(18),
                        ),
                      ),
                    ),
                  ],
                  buildResetButton(controller)
                ],
              );
            case 'date':
              return GestureDetector(
                onTap: () async {
                  DateTime? picked = await showDatePicker(
                    context: context,
                    initialDate: DateTime.now(),
                    firstDate: DateTime(2000),
                    lastDate: DateTime(2100),
                  );
                  if (picked != null) {
                    // Parse the date string into a DateTime object
                    // DateTime dateTime = DateFormat('yyyy-MM-dd')
                    //     .parse(picked.toIso8601String().split('T').first);

                    controller.text = picked
                        .toIso8601String()
                        .split('T')
                        .first; //DateFormat('dd/MM/yyyy').format(dateTime);
                  }
                },
                child: AbsorbPointer(
                  child: CustomTextFieldContainer(
                    labelKey: field.name,
                    textEditingController: controller,
                    isFieldValueMandatory: field.required,
                    hintTextKey: '',
                    validator: field.required
                        ? (v) => Validator.emptyValueValidation(v, context)
                        : null,
                  ),
                ),
              );
            case 'file':
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CustomTextFieldContainer(
                    labelKey: field.name,
                    textEditingController: controller,
                    isFieldValueMandatory: field.required,
                    readOnly: true,
                    hintTextKey: '',
                    validator: field.required
                        ? (v) => Validator.emptyValueValidation(v, context)
                        : null,
                    suffixWidget: TextButton.icon(
                      icon: Icon(Icons.upload_file),
                      label: CustomTextContainer(
                        textKey: uploadKey,
                      ),
                      onPressed: () {
                        Utils.openFileExplorer().then((value) async {
                          if (value != null) {
                            String filePath = value.first!.path;
                            controller.text = filePath
                                .substring(filePath.lastIndexOf('/') + 1);
                            widget.files['custom_field_${field.id}'] =
                                await MultipartFile.fromFile(filePath);
                            setState(() {});
                          }
                        });
                      },
                    ),
                  ),
                  if (controller.text.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8.0),
                      child: Row(
                        children: [
                          Expanded(
                            child: InkWell(
                              onTap: () {
                                // Open the file URL in browser or file viewer
                                Utils.launchURL(
                                    widget.files['custom_field_${field.id}']);
                              },
                              child: Text(
                                controller.text,
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.primary,
                                  decoration: TextDecoration.underline,
                                ),
                              ),
                            ),
                          ),
                          IconButton(
                              visualDensity: VisualDensity(vertical: -4),
                              onPressed: () {
                                controller.clear();
                                widget.files.remove('custom_field_${field.id}');
                                setState(() {});
                              },
                              icon: Icon(Icons.close))
                        ],
                      ),
                    ),
                ],
              );
            case 'radio':
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 7.5),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: CustomTextContainer(
                            textKey: field.name,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                        buildResetButton(controller),
                      ],
                    ),
                    Row(
                      children: <Widget>[
                        ...field.options.map((option) => Expanded(
                              child: RadioListTile<String>(
                                visualDensity: VisualDensity(vertical: -4),
                                title: Text(option),
                                value: option,
                                groupValue: controller.text,
                                onChanged: (val) {
                                  controller.text = val ?? '';
                                  setState(() {});
                                },
                              ),
                            )),
                      ],
                    )
                  ],
                ),
              );
            case 'dropdown':
            case 'select':
              final options = (field.options )
                  .where((e) => e.isNotEmpty)
                  .toList();
              final displayOptions = ['Select', ...options];
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: CustomDropDownContainer(
                      labelKey: field.name,
                      dropDownDisplayLabels: displayOptions,
                      selectedValue: displayOptions.contains(controller.text)
                          ? controller.text
                          : 'Select',
                      onChanged: (val) {
                        if (val == 'Select') {
                          controller.clear();
                        } else {
                          controller.text = val ?? '';
                        }
                        setState(() {});
                      },
                      values: displayOptions,
                      isFieldValueMandatory: field.required,
                    ),
                  ),
                  buildResetButton(controller),
                ],
              );
            case 'checkbox':
              // Multi-select
              final selected = (controller.text.isNotEmpty)
                  ? controller.text.split(',')
                  : <String>[];
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 7.5),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: CustomTextContainer(
                            textKey: field.name,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                        buildResetButton(controller),
                      ],
                    ),
                    ...field.options.map((option) => CheckboxListTile(
                          visualDensity: VisualDensity(vertical: -4),
                          title: Text(option),
                          value: selected.contains(option),
                          onChanged: (val) {
                            if (val == true) {
                              selected.add(option);
                            } else {
                              selected.remove(option);
                            }
                            controller.text = selected.join(',');
                            setState(() {});
                          },
                        )),
                  ],
                ),
              );
            default:
              return SizedBox.shrink();
          }
        }).toList(),
      ]);
    }
    return SingleChildScrollView(
      padding: const EdgeInsets.all(
        appContentHorizontalPadding,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
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
              hintTextKey: productNameKey,
              textEditingController: widget.nameControllers[selectedLang.code]!,
              labelKey: selectedLang.code == englishLangCode
                  ? productNameKey
                  : '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: productNameKey)} (${selectedLang.language})',
              textInputAction: TextInputAction.next,
              focusNode: widget.focusNodes[productNameKey],
              isFieldValueMandatory:
                  selectedLang.code == englishLangCode ? true : false,
              isSetValidator:
                  selectedLang.code == englishLangCode ? true : false,
              onFieldSubmitted: (v) => FocusScope.of(context)
                  .requestFocus(widget.focusNodes[productTypeKey])),
          // if (context
          //         .read<SettingsAndLanguagesCubit>()
          //         .getSettings()
          //         .systemSettings!
          //         .AISetting !=
          //     null)
          AiPromptField(
            productNameController: widget.nameControllers[selectedLang.code]!,
            descriptionController: widget.descControllers[selectedLang.code]!,
            selectedLanguage: selectedLang.language!,
            isShortDescription: true,
            callback: (value) => setState(() {
              widget.descControllers[selectedLang.code]!.text = value;
            }),
          ),
          CustomTextFieldContainer(
              hintTextKey: productSortDesKey,
              textEditingController: widget.descControllers[selectedLang.code]!,
              labelKey: selectedLang.code == englishLangCode
                  ? productSortDesKey
                  : '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: productSortDesKey)} (${selectedLang.language})',
              textInputAction: TextInputAction.newline,
              keyboardType: TextInputType.multiline,
              isFieldValueMandatory:
                  selectedLang.code == englishLangCode ? true : false,
              isSetValidator:
                  selectedLang.code == englishLangCode ? true : false,
              minLines: 2,
              maxLines: 5,
              focusNode: widget.focusNodes[productSortDesKey],
              onFieldSubmitted: (v) =>
                  widget.focusNodes[productSortDesKey]!.unfocus()),
          CustomDropDownContainer(
              labelKey: productTypeKey,
              dropDownDisplayLabels: mainProductTypes.values.toList(),
              isReadOnly: widget.product != null,
              selectedValue:
                  widget.controllers[productTypeKey]!.text == digitalProductType
                      ? digitalProductType
                      : physicalProductType,
              onChanged: (value) {
                if (isAddEditComboProduct &&
                    context.read<GetProductByTypeCubit>().getType() != value) {
                  widget.selectedProducts.clear();
                  context.read<GetProductByTypeCubit>().getProductList(
                      context,
                      {
                        ApiURL.storeIdApiKey: context
                            .read<StoresCubit>()
                            .getDefaultStore()
                            .id!
                            .toString(),
                        "type": value,
                      },
                      isSetInitial: true);
                }
                setState(() {
                  widget.controllers[productTypeKey]!.text =
                      value == digitalProductType
                          ? digitalProductType
                          : productTypes.keys.first;
                });
              },
              values: mainProductTypes.keys.toList()),
          if (isAddEditComboProduct) productSelectionWidget(),
          if (!isAddEditComboProduct) ...[
            BlocBuilder<CategoryListCubit, CategoryListState>(
              builder: (context, state) {
                if (state is CategoryListFetchSuccess) {
                  return CustomTextFieldContainer(
                    hintTextKey: selectCategoryKey,
                    textEditingController:
                        widget.controllers[selectCategoryKey]!,
                    labelKey: selectCategoryKey,
                    textInputAction: TextInputAction.next,
                    onFieldSubmitted: null,
                    focusNode: AlwaysDisabledFocusNode(),
                    isSetValidator: true,
                    errmsg: selectCategoryKey,
                    suffixWidget: const Icon(Icons.arrow_drop_down),
                    onTap: () {
                      if (state.categoryList.isEmpty) return;
                      showDialog(
                        context: context,
                        barrierDismissible: false,
                        builder: (BuildContext mcontext) {
                          dialogContext = mcontext;

                          return AlertDialog(
                            insetPadding: const EdgeInsets.all(
                                appContentHorizontalPadding),
                            backgroundColor: white,
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 5),
                            shape:
                                DesignConfig.setRoundedBorder(white, 10, false),
                            content: CategorySelectionDialog(
                                categorylist: state.categoryList,
                                selectedId: widget.files[selectCategoryKey],
                                onCategorySelect: (List<Category> category) {
                                  if (widget.files[selectCategoryKey] !=
                                      category.first.id.toString()) {
                                    widget.files[selectCategoryKey] =
                                        category.first.id.toString();
                                    setState(() {
                                      widget.controllers[selectCategoryKey]!
                                          .text = category.first.name;
                                    });
                                  }
                                  Navigator.pop(dialogContext);
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
                            indicatorColor:
                                Theme.of(context).colorScheme.primary)
                        : null,
                    onPressed: () {
                      context
                          .read<CategoryListCubit>()
                          .getCategoryList(context, {
                        ApiURL.storeIdApiKey: context
                            .read<StoresCubit>()
                            .getDefaultStore()
                            .id
                            .toString(),
                      });
                    },
                  );
                } else {
                  return const SizedBox.shrink();
                }
              },
            ),
            BlocBuilder<BrandListCubit, BrandListState>(
              builder: (context, state) {
                if (state is BrandListFetchSuccess) {
                  return CustomTextFieldContainer(
                    hintTextKey: brandKey,
                    textEditingController: widget.controllers[brandKey]!,
                    labelKey: brandKey,
                    textInputAction: TextInputAction.next,
                    focusNode: AlwaysDisabledFocusNode(),
                    isFieldValueMandatory: false,
                    errmsg: brandKey,
                    suffixWidget: const Icon(Icons.arrow_drop_down),
                    onFieldSubmitted: (v) =>
                        FocusScope.of(context).requestFocus(tagfocus),
                    onTap: () {
                      if (state.brandList.isEmpty) return;
                      showDialog(
                        context: context,
                        barrierDismissible: false,
                        builder: (BuildContext mcontext) {
                          dialogContext = mcontext;
                          return AlertDialog(
                            insetPadding: const EdgeInsets.all(
                                appContentHorizontalPadding),
                            backgroundColor: white,
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 5),
                            shape:
                                DesignConfig.setRoundedBorder(white, 10, false),
                            content: BrandSelectionDialog(
                                brandListCubit: context.read<BrandListCubit>(),
                                onBrandSelect: (Brand brand) {
                                  if (widget.files[brandKey] !=
                                      brand.id.toString()) {
                                    widget.files[brandKey] =
                                        brand.id.toString();
                                    setState(() {
                                      widget.controllers[brandKey]!.text =
                                          brand.name ?? '';
                                    });
                                  }
                                  Navigator.pop(dialogContext);
                                }),
                          );
                        },
                      );
                    },
                  );
                } else {
                  return const SizedBox.shrink();
                }
              },
            ),
          ],
          CustomTextFieldContainer(
            hintTextKey: tagsKey,
            textEditingController: edttags,
            labelKey: tagsKey,
            isFieldValueMandatory: false,
            textInputAction: TextInputAction.next,
            focusNode: tagfocus,
            onFieldSubmitted: (v) => FocusScope.of(context)
                .requestFocus(widget.focusNodes[selectCategoryKey]),
            suffixWidget: Padding(
              padding: const EdgeInsetsDirectional.only(end: 3),
              child: TextButton(
                  style: TextButton.styleFrom(
                      minimumSize: const Size(50, 30),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      padding: EdgeInsets.zero,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(5)),
                      backgroundColor: Theme.of(context).colorScheme.primary,
                      foregroundColor: Colors.white),
                  onPressed: () {
                    if (edttags.text.trim().isNotEmpty &&
                        !taglist.contains(edttags.text.trim())) {
                      taglist.add(edttags.text.trim());
                      widget.controllers[tagsKey]!.text = taglist.join(",");
                      edttags.clear();
                      setState(() {});
                    }
                  },
                  child: const Icon(Icons.add, color: Colors.white)),
            ),
          ),
          tagsListWidget(),
          if (!isAddEditComboProduct) ...[
            BlocBuilder<CountryListCubit, CountryListState>(
              builder: (context, state) {
                if (state is CountryListFetchSuccess) {
                  return CustomTextFieldContainer(
                    hintTextKey: madeInKey,
                    textEditingController: widget.controllers[madeInKey]!,
                    labelKey: madeInKey,
                    textInputAction: TextInputAction.next,
                    focusNode: AlwaysDisabledFocusNode(),
                    isFieldValueMandatory: false,
                    errmsg: madeInKey,
                    suffixWidget: const Icon(Icons.arrow_drop_down),
                    onFieldSubmitted: (v) => FocusScope.of(context).unfocus(),
                    onTap: () {
                      if (state.countryList.isEmpty) return;
                      int? selectedCid;
                      if (widget.controllers[madeInKey]!.text
                          .trim()
                          .isNotEmpty) {
                        Country? country = state.countryList.firstWhereOrNull(
                          (product) =>
                              product.name!.trim().toLowerCase() ==
                              widget.controllers[madeInKey]!.text
                                  .trim()
                                  .toLowerCase(),
                        );
                        if (country != null) {
                          selectedCid = country.id;
                        }
                      }
                      showDialog(
                        context: context,
                        barrierDismissible: false,
                        builder: (BuildContext mcontext) {
                          dialogContext = mcontext;
                          return AlertDialog(
                            insetPadding: const EdgeInsets.all(
                                appContentHorizontalPadding),
                            backgroundColor: white,
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 5),
                            shape:
                                DesignConfig.setRoundedBorder(white, 10, false),
                            content: CountrySelectionDialog(
                                selectedId: selectedCid,
                                countryListCubit:
                                    context.read<CountryListCubit>(),
                                onCountrySelect: (Country country) {
                                  setState(() {
                                    widget.controllers[madeInKey]!.text =
                                        country.name ?? '';
                                  });
                                  Navigator.pop(dialogContext);
                                }),
                          );
                        },
                      );
                    },
                  );
                } else {
                  return const SizedBox.shrink();
                }
              },
            ),
          ],
          ...customFieldsWidgets,
        ],
      ),
    );
  }

  buildResetButton(TextEditingController controller) {
    return CustomTextButton(
        buttonTextKey: resetKey,
        textStyle: Theme.of(context)
            .textTheme
            .titleSmall!
            .copyWith(color: Theme.of(context).colorScheme.primary),
        onTapButton: () {
          controller.clear();
          setState(() {});
        });
  }

  tagsListWidget() {
    return Wrap(
        crossAxisAlignment: WrapCrossAlignment.start,
        alignment: WrapAlignment.start,
        runAlignment: WrapAlignment.start,
        spacing: 5,
        children: List.generate(
            taglist.length,
            (index) => Chip(
                  elevation: 10,
                  label: Text(
                    taglist[index],
                  ),
                  backgroundColor: Theme.of(context).colorScheme.onPrimary,
                  deleteIconColor: primaryColor,
                  deleteIcon: const Icon(
                    Icons.cancel_outlined,
                    size: 20,
                  ),
                  onDeleted: () {
                    taglist.removeAt(index);
                    widget.controllers[tagsKey]!.text = taglist.join(",");
                    setState(() {});
                  },
                )));
  }

  productSelectionWidget() {
    String producttype = "", productlbl = "";
    if (widget.controllers[productTypeKey]!.text == digitalProductType) {
      producttype = digitalProductType;
      productlbl = digitalProductKey;
    } else {
      producttype = physicalProductType;
      productlbl = physicalProductKey;
    }
    List selectedProductKeys = widget.selectedProducts.keys.toList();
    List selectedSimilarProductKeys =
        widget.selectedSimilarProducts.keys.toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        BlocBuilder<GetProductByTypeCubit, GetProductByTypeState>(
          builder: (context, state) {
            if (state is GetProductByTypeListSuccess) {
              return CustomTextFieldContainer(
                hintTextKey: selectProductKey,
                textEditingController: widget.controllers[productlbl]!,
                labelKey: selectComboProductKey,
                textInputAction: TextInputAction.next,
                isFieldValueMandatory: true,
                focusNode: AlwaysDisabledFocusNode(),
                errmsg: productlbl,
                suffixWidget: const Icon(Icons.arrow_drop_down),
                onTap: () {
                  if (state.productList.isEmpty) return;
                  showDialog(
                    context: context,
                    barrierDismissible: false,
                    builder: (BuildContext mcontext) {
                      dialogContext = mcontext;
                      return AlertDialog(
                        insetPadding:
                            const EdgeInsets.all(appContentHorizontalPadding),
                        backgroundColor: white,
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 5),
                        shape: DesignConfig.setRoundedBorder(white, 10, false),
                        content: ProductSelectionDialog(
                            selectedProduct: widget.selectedProducts,
                            type: producttype,
                            productListCubit:
                                context.read<GetProductByTypeCubit>(),
                            onProductSelect: (Map<String, ProductVariant> ids) {
                              widget.selectedProducts.clear();
                              widget.selectedProducts.addAll(ids);

                              setState(() {});

                              Navigator.pop(dialogContext);
                            }),
                      );
                    },
                  );
                },
              );
            }
            if (state is GetProductByTypeListFailure) {
              return CustomTextContainer(
                textKey: state.errorMessage,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              );
            } else {
              return const SizedBox.shrink();
            }
          },
        ),
        if (selectedProductKeys.isNotEmpty)
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(borderRadius),
                color: Theme.of(context).scaffoldBackgroundColor),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomTextContainer(
                    textKey: comboProductListKey,
                    style: Theme.of(context).textTheme.bodyMedium),
                DesignConfig.defaultHeightSizedBox,
                Wrap(
                  runSpacing: 8,
                  children: List.generate(
                    widget.selectedProducts.length,
                    (index) {
                      String mkey = selectedProductKeys[index];
                      return ListTile(
                        leading: CustomImageWidget(
                          url:
                              widget.selectedProducts[mkey]!.productImage ?? '',
                          width: 48,
                          height: 48,
                          borderRadius: 4,
                        ),
                        title: CustomTextContainer(
                            textKey: widget.selectedProducts[mkey]!
                                            .variantValues !=
                                        null &&
                                    widget.selectedProducts[mkey]!
                                        .variantValues!.isNotEmpty
                                ? '${widget.selectedProducts[mkey]!.productName ?? ""} - ${widget.selectedProducts[mkey]!.variantValues}'
                                : widget.selectedProducts[mkey]!.productName ??
                                    "",
                            style: Theme.of(context).textTheme.bodyMedium!),
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        minVerticalPadding: 1,
                        trailing: IconButton(
                            onPressed: () {
                              widget.selectedProducts.remove(mkey);
                              setState(() {});
                            },
                            padding: EdgeInsets.zero,
                            icon: Icon(
                              Icons.close,
                              color: Theme.of(context).colorScheme.secondary,
                            )),
                      );
                    },
                  ),
                ),
              ],
            ),
          ),
        Align(
            alignment: AlignmentDirectional.centerStart,
            child: HelperWidgets.taxWithSwitchWidget(
                context,
                hasSimilarProductsKey,
                widget.controllers[hasSimilarProductsKey]!.text == "1",
                textstyle: Theme.of(context).textTheme.bodyMedium!.copyWith(
                    fontWeight: FontWeight.bold), changeCallback: (bool value) {
              widget.controllers[hasSimilarProductsKey]!.text =
                  value ? "1" : "0";
              setState(() {});
            })),
        if (widget.controllers[hasSimilarProductsKey]!.text == "1")
          BlocBuilder<ProductsCubit, ProductsState>(
            builder: (context, state) {
              if (state is ProductsFetchSuccess) {
                return CustomTextFieldContainer(
                  hintTextKey: selectProductKey,
                  textEditingController: widget.controllers[selectProductKey]!,
                  labelKey: selectProductKey,
                  textInputAction: TextInputAction.next,
                  focusNode: AlwaysDisabledFocusNode(),
                  errmsg: selectProductKey,
                  suffixWidget: const Icon(Icons.arrow_drop_down),
                  onTap: () {
                    if (state.products.isEmpty) return;
                    showDialog(
                      context: context,
                      barrierDismissible: false,
                      builder: (BuildContext mcontext) {
                        dialogContext = mcontext;

                        return AlertDialog(
                          insetPadding:
                              const EdgeInsets.all(appContentHorizontalPadding),
                          backgroundColor: white,
                          contentPadding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 5),
                          shape:
                              DesignConfig.setRoundedBorder(white, 10, false),
                          content: ComboProductSelectionDialog(
                              selectedProduct: widget.selectedSimilarProducts,
                              productListCubit: context.read<ProductsCubit>(),
                              onProductSelect: (Map<String, Product> ids) {
                                widget.selectedSimilarProducts.clear();
                                widget.selectedSimilarProducts.addAll(ids);

                                setState(() {});

                                Navigator.pop(dialogContext);
                              }),
                        );
                      },
                    );
                  },
                );
              } else {
                return const SizedBox.shrink();
              }
            },
          ),
        if (widget.selectedSimilarProducts.isNotEmpty)
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(borderRadius),
                color: Theme.of(context).scaffoldBackgroundColor),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CustomTextContainer(
                    textKey: similarProductListKey,
                    style: Theme.of(context).textTheme.bodyMedium),
                DesignConfig.defaultHeightSizedBox,
                Wrap(
                  runSpacing: 8,
                  children: List.generate(
                    widget.selectedSimilarProducts.length,
                    (index) {
                      String mkey = selectedSimilarProductKeys[index];
                      return ListTile(
                        leading: CustomImageWidget(
                          url:
                              widget.selectedSimilarProducts[mkey]!.image ?? '',
                          width: 48,
                          height: 48,
                          borderRadius: 4,
                        ),
                        title: CustomTextContainer(
                            textKey:
                                widget.selectedSimilarProducts[mkey]!.name!,
                            style: Theme.of(context).textTheme.bodyMedium!),
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        minVerticalPadding: 1,
                        trailing: IconButton(
                            onPressed: () {
                              widget.selectedSimilarProducts.remove(mkey);
                              setState(() {});
                            },
                            padding: EdgeInsets.zero,
                            icon: Icon(
                              Icons.close,
                              color: Theme.of(context).colorScheme.secondary,
                            )),
                      );
                    },
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
