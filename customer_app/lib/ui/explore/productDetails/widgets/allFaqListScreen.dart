import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/ui/profile/faq/blocs/addProductFaqCubit.dart';
import 'package:eshop_plus/ui/profile/faq/blocs/faqCubit.dart';
import 'package:eshop_plus/ui/profile/faq/models/faq.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/ui/explore/productDetails/widgets/productFaqWidget.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customSearchContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextButton.dart';
import 'package:eshop_plus/commons/widgets/customTextFieldContainer.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';
import 'package:eshop_plus/commons/widgets/filterContainerForBottomSheet.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class AllFaqListScreen extends StatefulWidget {
  final Product product;
  const AllFaqListScreen({Key? key, required this.product}) : super(key: key);
  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => FAQCubit(),
        ),
      ],
      child: AllFaqListScreen(
        product: arguments['product'] as Product,
      ),
    );
  }

  @override
  _AllFaqListScreenState createState() => _AllFaqListScreenState();
}

class _AllFaqListScreenState extends State<AllFaqListScreen> {
  TextEditingController _questionTextController = TextEditingController();
  final TextEditingController _searchController = TextEditingController();
  List<FAQ> _faqList = [];
  var prevVal;
  @override
  void initState() {
    super.initState();

    Future.delayed(Duration.zero, () {
      getProductFaqs();
    });
  }

  getProductFaqs({String searchval = ""}) {
    context.read<FAQCubit>().getFAQ(params: {
      ApiURL.productIdApiKey: widget.product.id,
      ApiURL.productTypeApiKey:
          widget.product.type == comboProductType ? "combo" : "regular",
      ApiURL.searchApiKey: searchval,
    }, api: ApiURL.getProductFaqs);
  }

  @override
  void dispose() {
    super.dispose();
    _questionTextController.dispose();
    _searchController.dispose();
  }

  void loadMoreFaqs({String searchval = ""}) {
    context.read<FAQCubit>().loadMore(params: {
      ApiURL.productIdApiKey: widget.product.id,
      ApiURL.productTypeApiKey:
          widget.product.type == comboProductType ? "combo" : "regular",
      ApiURL.searchApiKey: searchval,
    }, api: ApiURL.getProductFaqs);
  }

  searchChange(String val) {
    if (val.trim() != prevVal) {
      Future.delayed(Duration.zero, () {
        prevVal = val.trim();
        Future.delayed(const Duration(seconds: 2), () {
          getProductFaqs(searchval: val);
        });
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(titleKey: questionsAndAnswersKey),
      bottomNavigationBar:
          buildPostQuestionButton(context.read<FAQCubit>(), context),
      body: Column(
        children: [
          buildSearchWidget(),
          DesignConfig.smallHeightSizedBox,
          Expanded(
            child: BlocConsumer<FAQCubit, FAQState>(
              bloc: context.read<FAQCubit>(),
              listener: (context, state) {
                if (state is FAQFetchSuccess) {
                  setState(() {
                    _faqList = List.from(state.faqs);
                  });
                }
              },
              builder: (context, state) {
                if (state is FAQFetchSuccess) {
                  return NotificationListener<ScrollUpdateNotification>(
                    onNotification: (notification) {
                      if (notification.metrics.pixels >=
                          notification.metrics.maxScrollExtent) {
                        if (context.read<FAQCubit>().hasMore()) {
                          loadMoreFaqs();
                        }
                      }
                      return true;
                    },
                    child: Container(
                      color: Theme.of(context).colorScheme.primaryContainer,
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
                        itemCount: _faqList.length,
                        itemBuilder: (context, index) {
                          if (context.read<FAQCubit>().hasMore()) {
                            if (index == _faqList.length - 1) {
                              if (context.read<FAQCubit>().fetchMoreError()) {
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
                                    indicatorColor:
                                        Theme.of(context).colorScheme.primary),
                              );
                            }
                          }
                          return ProductFaqWidget(faq: _faqList[index]);
                        },
                      ),
                    ),
                  );
                }
                if (state is FAQFetchFailure) {
                  return ErrorScreen(
                      onPressed: getProductFaqs,
                      text: state.errorMessage,
                      child: state is FAQFetchInProgress
                          ? CustomCircularProgressIndicator(
                              indicatorColor:
                                  Theme.of(context).colorScheme.primary,
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
        ],
      ),
    );
  }

  CustomDefaultContainer buildSearchWidget() {
    return CustomDefaultContainer(
      child: CustomSearchContainer(
          textEditingController: _searchController,
          showVoiceIcon: false,
          autoFocus: false,
          suffixWidget: IconButton(
            icon: Icon(
              Icons.close,
              color: Theme.of(context).colorScheme.secondary,
            ),
            onPressed: () {
              setState(() {
                FocusScope.of(context).unfocus();
                _searchController.clear();
                getProductFaqs();
              });
            },
          ),
          onChanged: (val) {
            searchChange(val);
          }),
    );
  }

  buildPostQuestionButton(FAQCubit faqCubit, BuildContext context) {
    return CustomBottomButtonContainer(
        child: CustomRoundedButton(
      widthPercentage: 1.0,
      buttonTitle: postYourQuestionKey,
      showBorder: false,
      onTap: () => Utils.openModalBottomSheet(
              context,
              BlocProvider(
                create: (context) => AddProductFaqCubit(),
                child: BlocConsumer<AddProductFaqCubit, AddProductFaqState>(
                  listener: (context, state) {
                    if (state is AddProductFaqSuccess) {
                      Navigator.pop(context);
                      Utils.showSnackBar(
                          message: state.successMessage, context: context);
                    }
                    if (state is AddProductFaqFailure) {
                      Navigator.pop(context);
                      Utils.showSnackBar(
                          message: state.errorMessage, context: context);
                    }
                  },
                  builder: (context, state) {
                    return FilterContainerForBottomSheet(
                        title: writeQuestionKey,
                        borderedButtonTitle: '',
                        primaryButtonTitle: submitKey,
                        primaryChild: state is AddProductFaqProgress
                            ? CustomCircularProgressIndicator(
                                indicatorColor:
                                    Theme.of(context).colorScheme.primary)
                            : null,
                        borderedButtonOnTap: () {},
                        primaryButtonOnTap: () {
                          if (_questionTextController.text.isNotEmpty) {
                            if (state is! AddProductFaqProgress) {
                              context
                                  .read<AddProductFaqCubit>()
                                  .addProductFaq(params: {
                                ApiURL.productIdApiKey: widget.product.id,
                                ApiURL.productTypeApiKey:
                                    widget.product.type == comboProductType
                                        ? "combo"
                                        : "regular",
                                ApiURL.questionApiKey:
                                    _questionTextController.text.trim(),
                              });
                            }
                          }
                        },
                        content: Column(
                          children: <Widget>[
                            DesignConfig.defaultHeightSizedBox,
                            CustomTextFieldContainer(
                              hintTextKey: typeYourQuestionKey,
                              textEditingController: _questionTextController,
                              maxLines: 5,
                            ),
                          ],
                        ));
                  },
                ),
              ),
              isScrollControlled: true,
              staticContent: true)
          .then((value) => _questionTextController.clear()),
    ));
  }
}
