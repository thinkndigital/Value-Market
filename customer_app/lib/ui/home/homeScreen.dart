import 'dart:io';

import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/ui/home/categorySlider/categorySliderCubit.dart';

import 'package:eshop_plus/ui/home/offer/offerCubit.dart';
import 'package:eshop_plus/ui/home/mostSellingProduct/mostSellingProductCubit.dart';
import 'package:eshop_plus/commons/product/blocs/productsCubit.dart';
import 'package:eshop_plus/ui/home/featuredSection/featuredSectionCubit.dart';
import 'package:eshop_plus/ui/home/seller/bestSellerCubit.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/ui/home/slider/sliderCubit.dart';
import 'package:eshop_plus/commons/blocs/userDetailsCubit.dart';
import 'package:eshop_plus/ui/home/seller/bestSellerSection.dart';
import 'package:eshop_plus/ui/home/brand/brandSection.dart';
import 'package:eshop_plus/ui/home/category/categorySection.dart';
import 'package:eshop_plus/ui/home/widgets/addDeliveryLocationWidget.dart';
import 'package:eshop_plus/ui/home/categorySlider/categorySliderSection.dart';
import 'package:eshop_plus/ui/home/featuredSection/featuredSectionContainer.dart';
import 'package:eshop_plus/ui/home/seller/featuredSellerSection.dart';
import 'package:eshop_plus/ui/home/mostSellingProduct/mostSellingProductSection.dart';
import 'package:eshop_plus/ui/home/offer/offerSection.dart';

import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'brand/brandsCubit.dart';
import '../categoty/cubits/categoryCubit.dart';
import 'seller/featuredSellerCubit.dart';
import '../../commons/blocs/storesCubit.dart';
import 'widgets/homeAppBar.dart';
import 'slider/sliderSection.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  _HomeScreenState createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  GlobalKey _deliveryWidgetKey = GlobalKey();
  GlobalKey _sliderKey = GlobalKey();
  bool _isLoading = false;
  @override
  void initState() {
    super.initState();

    checkForAppUpdate();
    getApiData();
  }

  checkForAppUpdate() {
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (context.read<SettingsAndLanguagesCubit>().state
          is SettingsAndLanguagesFetchSuccess) {
        if (context.read<SettingsAndLanguagesCubit>().isUpdateRequired()) {
          openUpdateDialog();
        }
      }
    });
  }

  getApiData() {
    setState(() {
      _isLoading = true;
    });
    Future.delayed(Duration.zero).then((value) {
      int storeId = context.read<StoresCubit>().getDefaultStore().id!;
      if (mounted) {
        context.read<CategoryCubit>().fetchCategories(storeId: storeId);
        context
            .read<CategorySliderCubit>()
            .getCategoriesSliders(storeId: storeId);
        context.read<OfferCubit>().getOfferSliders(storeId: storeId);
        context.read<SliderCubit>().getSliders(storeId: storeId);
        context
            .read<FeaturedSellerCubit>()
            .fetchFeaturedSellers(storeId: storeId);
        context.read<MostSellingProductsCubit>().getMostSellingProducts(
            storeId: storeId,
            userId: context.read<UserDetailsCubit>().getUserId());
        context.read<FeaturedSectionCubit>().getSections(storeId: storeId);
        context.read<BestSellersCubit>().getBestSellers(storeId: storeId);
        context.read<BrandsCubit>().getBrands(storeId: storeId);
      }

      setState(() {
        _isLoading = false;
      });
    });
  }

  openUpdateDialog() {
    Utils.openAlertDialog(context, barrierDismissible: false, onTapNo: () {
      exit(0); // Forcefully close the app
    }, onTapYes: () {
      Utils.rateApp(context);
    },
        message: forceUpdateTitleKey,
        content: forceUpdateDescKey,
        noLabel: exitKey,
        yesLabel: updateKey);
  }

  @override
  Widget build(BuildContext context) {
    final categoryState = context.watch<CategoryCubit>().state;
    final sliderState = context.watch<SliderCubit>().state;
    final offerState = context.watch<OfferCubit>().state;
    final featuredSellerState = context.watch<FeaturedSellerCubit>().state;
    final brandsState = context.watch<BrandsCubit>().state;
    final mostSellingState = context.watch<MostSellingProductsCubit>().state;
    final featuredSectionState = context.watch<FeaturedSectionCubit>().state;

    bool _allApiFailed = categoryState is CategoryFetchFailure &&
        sliderState is SliderFetchFailure &&
        offerState is OfferFetchFailure &&
        featuredSellerState is FeaturedSellerFetchFailure &&
        brandsState is BrandsFetchFailure &&
        mostSellingState is MostSellingProductsFetchFailure &&
        featuredSectionState is FeaturedSectionFetchFailure;
    return Scaffold(
      appBar: HomeAppBar(setState: setState),
      body: RefreshIndicator(
        triggerMode: RefreshIndicatorTriggerMode.anywhere,
        onRefresh: () async {
          //this will clear the text edit controller value
          setState(() {
            _deliveryWidgetKey = GlobalKey();
            // we creating new slider key to refresh slider index to 0
            _sliderKey = GlobalKey();
          });

          getApiData();
        },
        child: _allApiFailed
            ? ErrorScreen(
                text: defaultErrorMessageKey,
                image: 'no_internet',
                onPressed: getApiData,
                child: _isLoading ? CustomCircularProgressIndicator() : null,
              )
            : ListView(
                children: <Widget>[
                  AddDeliveryLocationWidget(key: _deliveryWidgetKey),
                  CategorySection(),
                  SliderSection(key: _sliderKey),
                  const FeaturedSellerSection(),
                  BrandSection(),
                  const CategorySliderSection(),
                  const MostSellingProductSection(),
                  BlocProvider(
                    create: (context) => ProductsCubit(),
                    child: const OfferSection(),
                  ),
                  BlocProvider(
                    create: (context) => ProductsCubit(),
                    child: BestSellerSection(),
                  ),
                  const FeaturedSectionContainer(),
                ],
              ),
      ),
    );
  }


}
