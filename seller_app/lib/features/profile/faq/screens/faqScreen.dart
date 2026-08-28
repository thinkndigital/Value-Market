import 'dart:io';

import 'package:eshopplus_seller/commons/widgets/customDropDownContainer.dart';
import 'package:eshopplus_seller/commons/widgets/filterContainerForBottomSheet.dart';
import 'package:eshopplus_seller/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshopplus_seller/core/configs/appConfig.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/features/profile/faq/blocs/addProductFaqCubit.dart';
import 'package:eshopplus_seller/features/profile/faq/blocs/faqCubit.dart';
import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/commons/blocs/storesCubit.dart';
import 'package:eshopplus_seller/features/profile/faq/models/faq.dart';
import 'package:eshopplus_seller/commons/models/product.dart';
import 'package:eshopplus_seller/features/profile/faq/widgets/productFaqWidget.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customRoundedButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/errorScreen.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/inputValidators.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import '../../../../utils/utils.dart';
import '../../../../commons/widgets/customAppbar.dart';

class FaqScreen extends StatefulWidget {
  final bool? fromProductScreen;
  final Product? product;
  const FaqScreen({Key? key, this.fromProductScreen = false, this.product})
      : super(key: key);
  static Widget getRouteInstance() => MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => FAQCubit(),
          ),
          BlocProvider(
            create: (context) => ProductsCubit(),
          ),
        ],
        child: FaqScreen(
          fromProductScreen: Get.arguments != null
              ? Get.arguments['fromProductScreen']
              : false,
          product: Get.arguments != null ? Get.arguments['product'] : null,
        ),
      );

  @override
  _FaqScreenState createState() => _FaqScreenState();
}

class _FaqScreenState extends State<FaqScreen> {
  final TextEditingController _searchController = TextEditingController();
  Map<String, TextEditingController> controllers = {};
  final GlobalKey<FormState> _formkey = GlobalKey<FormState>();
  Map<String, FocusNode> focusNodes = {};
  final List formFields = [
    productTypeKey,
    productNameKey,
    questionKey,
    answerKey,
    searchAllProductsKey
  ];
  bool _isSearchMode = false;
  List<FAQ> faqs = [];
  FocusNode _searchFocusNode = FocusNode();
  Product? _selectedProduct;
  var prevVal;
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      getProductFaqs();
    });
    formFields.forEach((key) {
      controllers[key] = TextEditingController();
      focusNodes[key] = FocusNode();
    });
    controllers[productNameKey]!.text = 'Product List';
    controllers[productTypeKey]!.text = regularProductType;
  }

  @override
  void dispose() {
    // Dispose all text editing controllers
    _searchController.dispose();
    controllers.forEach((key, controller) {
      controller.dispose();
    });
    focusNodes.forEach((key, focusNode) {
      focusNode.dispose();
    });
    _searchFocusNode.dispose();
    super.dispose();
  }

  getProductFaqs() {
    context.read<FAQCubit>().getFAQ(params: {
      if (widget.product != null) ApiURL.productIdApiKey: widget.product!.id!,
      if (widget.product != null)
        ApiURL.typeApiKey:
            widget.product!.type == comboProductType ? "combo" : "regular",
      ApiURL.searchApiKey: _searchController.text.trim(),
    }, api: ApiURL.getProductFaqs);
  }

  void loadMoreFaqs() {
    context.read<FAQCubit>().loadMore(params: {
      if (widget.product != null) ApiURL.productIdApiKey: widget.product!.id!,
      ApiURL.searchApiKey: _searchController.text.trim(),
    }, api: ApiURL.getProductFaqs);
  }

  searchChange(String val) {
    if (val.trim() != prevVal) {
      Future.delayed(Duration.zero, () {
        prevVal = val.trim();
        Future.delayed(const Duration(seconds: 2), () {
          getProductFaqs();
        });
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        appBar: widget.fromProductScreen == false ? buildAppbar() : null,
        body: buildFaqList(),
        bottomNavigationBar: buildPostQuestionButton(context));
  }

  CustomAppbar buildAppbar() {
    return CustomAppbar(
      titleKey: addFaqKey,
      showBackButton: _isSearchMode ? false : true,
      leadingWidget: _isSearchMode ? buildSearchField() : null,
      trailingWidget: IconButton(
        padding: EdgeInsets.zero,
        icon: Icon(_isSearchMode ? Icons.close : Icons.search_outlined),
        onPressed: _toggleSearchMode,
      ),
    );
  }

  buildFaqList() {
    return SafeAreaWithBottomPadding(
      child: RefreshIndicator(
        onRefresh: () async {
          _searchController.clear();
          getProductFaqs();
        },
        child: BlocConsumer<FAQCubit, FAQState>(
          bloc: context.read<FAQCubit>(),
          listener: (context, state) {
            if (state is FAQFetchSuccess) {
              faqs = state.faqs;
            }
          },
          builder: (context, state) {
            if (state is FAQFetchSuccess) {
              return faqs.isEmpty
                  ? const Center(
                      child: CustomTextContainer(textKey: dataNotAvailableKey))
                  : NotificationListener<ScrollUpdateNotification>(
                      onNotification: (notification) {
                        if (notification.metrics.pixels ==
                            notification.metrics.maxScrollExtent) {
                          if (context.read<FAQCubit>().hasMore()) {
                            loadMoreFaqs();
                          }
                        }
                        return true;
                      },
                      child: Column(
                        children: [
                          widget.fromProductScreen == true
                              ? DesignConfig.smallHeightSizedBox
                              : const SizedBox(
                                  height: 12,
                                ),
                          Expanded(
                            child: Container(
                              color: Theme.of(context)
                                  .colorScheme
                                  .primaryContainer,
                              child: ListView.separated(
                                separatorBuilder: (context, index) => Container(
                                    height: 1,
                                    margin: const EdgeInsets.symmetric(
                                        vertical: appContentHorizontalPadding),
                                    color: Theme.of(context)
                                        .inputDecorationTheme
                                        .iconColor),
                                padding: const EdgeInsets.symmetric(
                                    vertical: appContentHorizontalPadding),
                                itemCount: faqs.length,
                                itemBuilder: (context, index) {
                                  FAQ faq = faqs[index];
                                  if (context.read<FAQCubit>().hasMore()) {
                                    if (index == faqs.length - 1) {
                                      if (context
                                          .read<FAQCubit>()
                                          .fetchMoreError()) {
                                        return Center(
                                          child: CustomTextButton(
                                              buttonTextKey: retryKey,
                                              onTapButton: () {
                                                loadMoreFaqs();
                                              }),
                                        );
                                      }

                                      return Center(
                                        child: CustomCircularProgressIndicator(
                                            indicatorColor: Theme.of(context)
                                                .colorScheme
                                                .primary),
                                      );
                                    }
                                  }
                                  return GestureDetector(
                                    onTap: () => onTapFaqFunction(
                                      context.read<FAQCubit>(),
                                      true,
                                      context.read<ProductsCubit>(),
                                      productId: faq.productId,
                                      faq: faq,
                                    ),
                                    child: ProductFaqWidget(
                                      faq: faqs[index],
                                      faqCubit: context.read<FAQCubit>(),
                                      fromProductScreen:
                                          widget.fromProductScreen,
                                    ),
                                  );
                                },
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
            }
            if (state is FAQFetchFailure) {
              return ErrorScreen(
                  onPressed: getProductFaqs,
                  text: state.errorMessage,
                  child: state is FAQFetchInProgress
                      ? CustomCircularProgressIndicator(
                          indicatorColor: Theme.of(context).colorScheme.primary,
                        )
                      : null);
            }
            return Center(
              child: CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.primary),
            );
          },
        ),
      ),
    );
  }

  buildPostQuestionButton(BuildContext context) {
    return CustomBottomButtonContainer(
        bottomPadding: Platform.isIOS ? 10 : 0,
        child: CustomRoundedButton(
          widthPercentage: 1.0,
          buttonTitle:
              widget.fromProductScreen == true ? addProductFAQKey : addFaqKey,
          showBorder: false,
          onTap: () => onTapFaqFunction(
              context.read<FAQCubit>(), false, context.read<ProductsCubit>()),
        ));
  }

  Future<dynamic> onTapFaqFunction(
      FAQCubit faqCubit, bool isEditScreen, ProductsCubit productsCubit,
      {String? productId, String? productType, FAQ? faq}) {
    if (isEditScreen) {
      controllers[questionKey]!.text = faq!.question;
      controllers[answerKey]!.text = faq.answer;
    }

    return Utils.openModalBottomSheet(context, StatefulBuilder(
            builder: (BuildContext buildcontext, StateSetter setState) {
      return BlocProvider(
        create: (context) => AddProductFaqCubit(),
        child: BlocConsumer<AddProductFaqCubit, AddProductFaqState>(
          listener: (context, state) {
            if (state is AddProductFaqSuccess) {
              if (isEditScreen) {
                List<FAQ> faqs = [];
                faqs = (faqCubit.state as FAQFetchSuccess).faqs;
                int index =
                    faqs.indexWhere((element) => element.id == state.faq.id);
                if (index != -1) {
                  faqs[index] = state.faq;
                  faqCubit.emisSuccessState(faqs);
                }
              } else {
                List<FAQ> faqs = [];
                if (faqCubit.state is FAQFetchSuccess) {
                  faqs = (faqCubit.state as FAQFetchSuccess).faqs;
                  faqs.insert(0, state.faq);
                  faqCubit.emisSuccessState(faqs);
                } else {
                  faqs.insert(0, state.faq);
                  faqCubit.emisSuccessState(faqs);
                }
              }
              Navigator.pop(context);
              Utils.showSnackBar(message: state.successMessage);
            }
            if (state is AddProductFaqFailure) {
              Navigator.pop(context);
              Utils.showSnackBar(message: state.errorMessage);
            }
          },
          builder: (context, state) {
            return FilterContainerForBottomSheet(
                title: !isEditScreen ? addFaqKey : editProductFAQKey,
                borderedButtonTitle: cancelKey,
                primaryButtonTitle: !isEditScreen ? addFaqKey : updateKey,
                primaryChild: state is AddProductFaqProgress
                    ? const CustomCircularProgressIndicator()
                    : null,
                borderedButtonOnTap: Navigator.of(context).pop,
                primaryButtonOnTap: () {
                  if (_formkey.currentState!.validate()) {
                    if (state is AddProductFaqProgress) {
                      return;
                    }
                    Product product;
                    if (widget.product != null) {
                      product = widget.product!;
                    } else {
                      product = _selectedProduct!;
                    }
                    if (isDemoApp) {
                      Navigator.of(context).pop();
                      Utils.showSnackBar(message: demoModeOnKey);
                      return;
                    }
                    context.read<AddProductFaqCubit>().addProductFaq(
                        isEditFaq: isEditScreen,
                        params: isEditScreen
                            ? {
                                ApiURL.editIdApiKey: faq!.id,
                                ApiURL.answerApiKey:
                                    controllers[answerKey]!.text.trim(),
                                ApiURL.typeApiKey: faq.type
                              }
                            : {
                                ApiURL.productIdApiKey: product.id!,
                                ApiURL.questionApiKey:
                                    controllers[questionKey]!.text.trim(),
                                ApiURL.answerApiKey:
                                    controllers[answerKey]!.text.trim(),
                                ApiURL.productTypeApiKey:
                                    product.type == comboProductType
                                        ? 'combo'
                                        : 'regular',
                              });
                  }
                },
                content: Form(
                  key: _formkey,
                  child: SingleChildScrollView(
                    child: Column(
                      children: <Widget>[
                        if (!isEditScreen &&
                            widget.fromProductScreen == false) ...[
                          CustomDropDownContainer(
                              labelKey: '',
                              dropDownDisplayLabels: [
                                'Regular Product',
                                'Combo Product'
                              ],
                              selectedValue: controllers[productTypeKey]!.text,
                              isFieldValueMandatory: false,
                              onChanged: (value) {
                                setState(() {
                                  controllers[productTypeKey]!.text =
                                      value.toString();
                                  controllers[productNameKey]!.clear();
                                  productsCubit.getProducts(
                                    storeId: context
                                        .read<StoresCubit>()
                                        .getDefaultStore()
                                        .id!,
                                    isComboProduct: value == comboProductType,
                                  );
                                });
                              },
                              values: [regularProductType, comboProductType]),
                          CustomTextFieldContainer(
                            readOnly: true,
                            hintTextKey: productNameKey,
                            textEditingController: controllers[productNameKey]!,
                            focusNode: focusNodes[productNameKey],
                            textInputAction: TextInputAction.next,
                            isFieldValueMandatory: true,
                            suffixWidget: const Icon(Icons.keyboard_arrow_down),
                            validator: (v) =>
                                Validator.emptyValueValidation(v, context),
                            onFieldSubmitted: (v) {
                              FocusScope.of(context)
                                  .requestFocus(focusNodes[questionKey]);
                            },
                            onTap: () => selectProductBottomsheet(productsCubit,
                                controllers[productTypeKey]!.text),
                          ),
                        ],
                        CustomTextFieldContainer(
                            hintTextKey: questionKey,
                            textEditingController: controllers[questionKey]!,
                            labelKey: questionKey,
                            readOnly: isEditScreen == true,
                            textInputAction: TextInputAction.next,
                            validator: (v) =>
                                Validator.emptyValueValidation(v, context),
                            focusNode: focusNodes[questionKey],
                            onFieldSubmitted: (v) => FocusScope.of(context)
                                .requestFocus(focusNodes[answerKey])),
                        CustomTextFieldContainer(
                            hintTextKey: answerKey,
                            textEditingController: controllers[answerKey]!,
                            labelKey: answerKey,
                            keyboardType: TextInputType.multiline,
                            minLines: 3, // Display 3 lines initially
                            maxLines:
                                5, // Allow the field to expand as more lines are added
                            textInputAction: TextInputAction.newline,
                            focusNode: focusNodes[answerKey],
                            validator: (v) =>
                                Validator.emptyValueValidation(v, context),
                            onFieldSubmitted: (v) =>
                                focusNodes[answerKey]!.unfocus()),
                      ],
                    ),
                  ),
                ));
          },
        ),
      );
    }), isScrollControlled: false, staticContent: false)
        .then((value) {
      controllers[productNameKey]!.clear();
      controllers[questionKey]!.clear();
      controllers[answerKey]!.clear();
      setState(() {});
    });
  }

  selectProductBottomsheet(
    ProductsCubit productsCubit,
    String productType,
  ) {
    return Utils.openModalBottomSheet(context,
            StatefulBuilder(builder: (context, StateSetter setState) {
      if (productsCubit.state is! ProductsFetchSuccess ||
          productsCubit.state is! ProductsFetchInProgress) {
        productsCubit.getProducts(
          storeId: context.read<StoresCubit>().getDefaultStore().id!,
          isComboProduct: productType == comboProductType,
        );
      }
      return BlocConsumer<ProductsCubit, ProductsState>(
        bloc: productsCubit,
        listener: (context, state) {},
        builder: (context, state) {
          return Container(
              height: MediaQuery.of(context).size.height,
              padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding,
              ),
              child: Column(
                children: [
                  CustomTextFieldContainer(
                    hintTextKey: searchAllProductsKey,
                    textEditingController: controllers[searchAllProductsKey]!,
                    onChangeFun: (value) => productsCubit.getProducts(
                        storeId:
                            context.read<StoresCubit>().getDefaultStore().id!,
                        isComboProduct: productType == comboProductType,
                        searchText:
                            controllers[searchAllProductsKey]!.text.trim()),
                    suffixWidget: Padding(
                      padding: const EdgeInsetsDirectional.only(end: 10),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          GestureDetector(
                              onTap: () => productsCubit.getProducts(
                                  storeId: context
                                      .read<StoresCubit>()
                                      .getDefaultStore()
                                      .id!,
                                  isComboProduct:
                                      productType == comboProductType,
                                  searchText: controllers[searchAllProductsKey]!
                                      .text
                                      .trim()),
                              child: const Icon(Icons.search_outlined)),
                          GestureDetector(
                              onTap: () {
                                setState(() {
                                  controllers[searchAllProductsKey]!.clear();
                                  productsCubit.getProducts(
                                      storeId: context
                                          .read<StoresCubit>()
                                          .getDefaultStore()
                                          .id!,
                                      isComboProduct:
                                          productType == comboProductType);
                                });
                              },
                              child: const Icon(Icons.close)),
                        ],
                      ),
                    ),
                  ),
                  DesignConfig.smallHeightSizedBox,
                  state is ProductsFetchSuccess
                      ? Expanded(
                          child: NotificationListener<ScrollUpdateNotification>(
                            onNotification: (notification) {
                              if (notification.metrics.pixels ==
                                  notification.metrics.maxScrollExtent) {
                                if (productsCubit.hasMore()) {
                                  productsCubit.loadMore(
                                      storeId: context
                                          .read<StoresCubit>()
                                          .getDefaultStore()
                                          .id!,
                                      searchText:
                                          controllers[searchAllProductsKey]!
                                              .text
                                              .trim());
                                }
                              }
                              return true;
                            },
                            child: ListView.separated(
                              padding: const EdgeInsets.symmetric(vertical: 16),
                              shrinkWrap: true,
                              physics: const AlwaysScrollableScrollPhysics(),
                              itemCount: state.products.length,
                              itemBuilder: (_, index) {
                                Product product = state.products[index];

                                if (productsCubit.hasMore()) {
                                  if (index == state.products.length - 1) {
                                    return Center(
                                      child: CustomCircularProgressIndicator(
                                          indicatorColor: Theme.of(context)
                                              .colorScheme
                                              .primary),
                                    );
                                  }
                                }
                                return buildTile(
                                    _selectedProduct != null
                                        ? _selectedProduct!.id == product.id
                                        : false,
                                    product.name!, () {
                                  setState(() {
                                    _selectedProduct = product;
                                    controllers[productNameKey]!.text =
                                        product.name!;

                                    Navigator.of(context).pop();
                                  });
                                });
                              },
                              separatorBuilder:
                                  (BuildContext context, int index) {
                                return const Divider();
                              },
                            ),
                          ),
                        )
                      : state is ProductsFetchFailure
                          ? Center(
                              child: CustomTextContainer(
                                  textKey: state.errorMessage))
                          : CustomCircularProgressIndicator(
                              indicatorColor:
                                  Theme.of(context).colorScheme.primary),
                ],
              ));
        },
      );
    }), staticContent: false)
        .then((value) {});
  }

  buildTile(bool isSelected, String title, VoidCallback onTap) {
    return ListTile(
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(15.0)),
        ),
        selectedColor:
            Theme.of(context).colorScheme.primary.withValues(alpha: 0.1),
        title: CustomTextContainer(
          textKey: title,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: isSelected
                  ? Theme.of(context).colorScheme.primary
                  : Colors.black,
              fontWeight: isSelected ? FontWeight.w500 : FontWeight.w400,
              letterSpacing: 0.5),
        ),
        tileColor: isSelected
            ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)
            : Colors.transparent,
        onTap: onTap,
        trailing: isSelected
            ? Icon(Icons.check, color: Theme.of(context).colorScheme.primary)
            : null);
  }

  buildSearchField() {
    return Utils.buildSearchField(
        context: context,
        controller: _searchController,
        focusNode: _searchFocusNode,
        onChanged: (value) {
          getProductFaqs();
        });
  }

  void _toggleSearchMode() {
    setState(() {
      _isSearchMode = !_isSearchMode;
      if (!_isSearchMode) {
        setState(() {
          _searchController.clear();
          getProductFaqs();
        });
      } else {
        FocusScope.of(context).requestFocus(_searchFocusNode);
      }
    });
  }
}
