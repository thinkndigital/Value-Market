import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:eshopplus_seller/features/product/blocs/deleteProductCubit.dart';
import 'package:eshopplus_seller/features/profile/faq/blocs/faqCubit.dart';
import 'package:eshopplus_seller/features/product/blocs/getProductRatingCubit.dart';
import 'package:eshopplus_seller/commons/blocs/productCubit.dart';
import 'package:eshopplus_seller/features/product/blocs/updateProductSatusCubit.dart';
import 'package:eshopplus_seller/features/mainScreen.dart';
import 'package:eshopplus_seller/features/product/widgets/productDetailsTab.dart';
import 'package:eshopplus_seller/features/product/widgets/ratingContainer.dart';
import 'package:eshopplus_seller/features/profile/faq/screens/faqScreen.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import '../../../commons/models/product.dart';
import '../../../utils/designConfig.dart';
import '../../../commons/widgets/customAppbar.dart';
import '../../../commons/widgets/customTabbar.dart';

class ProductDetailsScreen extends StatefulWidget {
  final Product product;
  final ProductsCubit? productsCubit;
  const ProductDetailsScreen(
      {Key? key, required this.product, this.productsCubit})
      : super(key: key);
  static Widget getRouteInstance() => MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => DeleteProductCubit(),
          ),
          BlocProvider(
            create: (context) => UpdateProductStatusCubit(),
          ),
        ],
        child: ProductDetailsScreen(
          product: Get.arguments['product'],
          productsCubit: Get.arguments['productsCubit'],
        ),
      );
  @override
  _ProductDetailsScreenState createState() => _ProductDetailsScreenState();
}

class _ProductDetailsScreenState extends State<ProductDetailsScreen> {
  int _currentPage = 0;
  late Product product;
  final List<String> _tabTitles = const [
    productsDetailsKey,
    reviewsKey,
    productFAQKey
  ];
  GlobalKey _productDetailsKey = GlobalKey(),
      _ratingKey = GlobalKey(),
      _faqsKey = GlobalKey();
  @override
  initState() {
    super.initState();
    product = widget.product;
  }

  @override
  Widget build(BuildContext context) {
    return MultiBlocListener(
      listeners: [
        BlocListener<ProductsCubit, ProductsState>(
          bloc: regularProductsCubit,
          listener: (context, state) {
            if (widget.product.type != comboProductType &&
                state is ProductsFetchSuccess) {
              int index = state.products.indexWhere((p) => p.id == product.id);
              if (index != -1) {
                product = state.products[index];
              }

              setState(() {
                _productDetailsKey = GlobalKey();
              });
            }
          },
        ),
        BlocListener<ProductsCubit, ProductsState>(
          bloc: comboProductsCubit,
          listener: (context, state) {
            if (widget.product.type == comboProductType &&
                state is ProductsFetchSuccess) {
              int index = state.products.indexWhere((p) => p.id == product.id);
              if (index != -1) {
                product = state.products[index];
              }

              setState(() {
                _productDetailsKey = GlobalKey();
              });
            }
          },
        ),
      ],
      child: DefaultTabController(
        length: 3,
        child: Scaffold(
          appBar: const CustomAppbar(
            titleKey: productsDetailsKey,
            elevation: 0,
          ),
          body: SafeAreaWithBottomPadding(
            child: GestureDetector(
              /// we have used manual tabbar, so need to add gesture detector when user swipe to change tabs
              onHorizontalDragEnd: (dragEndDetails) {
                // Swiping in right direction.
                if (dragEndDetails.primaryVelocity! > 0) {
                  if (_currentPage > 0) {
                    setState(() {
                      _currentPage--;
                    });
                  }
                }

                // Swiping in left direction.
                if (dragEndDetails.primaryVelocity! < 0) {
                  if (_currentPage < _tabTitles.length - 1) {
                    setState(() {
                      _currentPage++;
                    });
                  }
                }
              },
              child: Column(
                children: <Widget>[
                  DesignConfig.smallHeightSizedBox,
                  CustomTabbar(
                      currentPage: _currentPage,
                      tabTitles: _tabTitles,
                      onTapTitle: (int index) {
                        setState(() {
                          _currentPage = index;
                        });
                      }),
                  Expanded(
                      child: IndexedStack(
                    alignment: AlignmentDirectional.topStart,
                    index: _currentPage,
                    children: [
                      ProductDetailsTab(
                        key: _productDetailsKey,
                        product: product,
                        productsCubit: widget.productsCubit,
                      ),
                      // ProductDetailsTab(),
                      BlocProvider(
                        create: (context) => ProductRatingCubit(),
                        child: RatingContainer(
                          key: _ratingKey,
                          product: product,
                          isComboProduct:
                              product.type == comboProductType ? true : false,
                        ),
                      ),
                      BlocProvider(
                        create: (context) => ProductsCubit(),
                        child: BlocProvider(
                          create: (context) => FAQCubit(),
                          child: FaqScreen(
                            key: _faqsKey,
                            fromProductScreen: true,
                            product: product,
                          ),
                        ),
                      ),
                    ],
                  ))
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Tab buildTabLabel(String title) {
    return Tab(
        child: Text(
      context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: title),
    ));
  }
}
