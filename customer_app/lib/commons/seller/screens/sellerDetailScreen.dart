import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/commons/seller/widgets/sellerInfoContainer.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/ui/explore/cubits/comboProductsCubit.dart';
import 'package:value_market_customer/commons/product/blocs/productsCubit.dart';
import 'package:value_market_customer/commons/seller/blocs/sellersCubit.dart';
import 'package:value_market_customer/commons/blocs/storesCubit.dart';
import 'package:value_market_customer/commons/seller/models/seller.dart';
import 'package:value_market_customer/ui/explore/screens/exploreScreen.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';

import 'package:value_market_customer/commons/widgets/error_screen.dart';

import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:share_plus/share_plus.dart';

class SellerDetailScreen extends StatefulWidget {
  final Seller? seller;
  final int? sellerId;
  final int? storeId;
  const SellerDetailScreen({Key? key, this.seller, this.sellerId, this.storeId})
      : super(key: key);
  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return BlocProvider(
      create: (context) => SellersCubit(),
      child: SellerDetailScreen(
        seller: arguments['seller'] as Seller?,
        sellerId: arguments['sellerId'] as int?,
        storeId: arguments['storeId'] as int?,
      ),
    );
  }

  @override
  _SellerDetailScreenState createState() => _SellerDetailScreenState();
}

class _SellerDetailScreenState extends State<SellerDetailScreen> {
  Seller? seller;
  @override
  void initState() {
    super.initState();
    if (widget.seller != null) {
      seller = widget.seller!;
    } else {
      Future.delayed(Duration.zero, () => fetchSeller());
    }
  }

  fetchSeller() {
    context.read<SellersCubit>().getSellers(
        storeId:
            widget.storeId ?? context.read<StoresCubit>().getDefaultStore().id!,
        sellerIds: [widget.sellerId!]);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: CustomAppbar(
        titleKey: sellerDetailsKey,
        trailingWidget: _shareButton(),
      ),
      body: BlocListener<SellersCubit, SellersState>(listener:
          (context, state) {
        if (state is SellersFetchSuccess) {
          setState(() {
            seller = state.sellers.first;
          });
        }
      }, child:
          BlocBuilder<SellersCubit, SellersState>(builder: (context, state) {
        if (seller != null || state is SellersFetchSuccess) {
          return Column(
            children: <Widget>[
              const SizedBox(
                height: 12,
              ),
              SellerInfoContainer(
                seller: seller,
                sellerId: widget.sellerId,
              ),
              DesignConfig.smallHeightSizedBox,
              Expanded(
                child: MultiBlocProvider(
                  providers: [
                    BlocProvider(
                      create: (context) => ProductsCubit(),
                    ),
                    BlocProvider(
                      create: (context) => ComboProductsCubit(),
                    ),
                  ],
                  child: ExploreScreen(
                    sellerId: seller!.sellerId,
                    isExploreScreen: false,
                    forSellerDetailScreen: true,
                    storeId: widget.storeId ??
                        context.read<StoresCubit>().getDefaultStore().id!,
                  ),
                ),
              )
            ],
          );
        } else if (state is SellersFetchFailure) {
          return ErrorScreen(text: state.errorMessage, onPressed: fetchSeller);
        } else {
          return CustomCircularProgressIndicator(
            indicatorColor: Theme.of(context).colorScheme.primary,
          );
        }
      })),
    );
  }

  _shareButton() {
    return IconButton(
      icon: Icon(Icons.share, color: Theme.of(context).colorScheme.secondary),
      onPressed: () {
        final String sellerUrl =
            "$baseUrl/seller/${widget.seller != null ? widget.seller!.sellerId : widget.sellerId}/${widget.storeId ?? context.read<StoresCubit>().getDefaultStore().id!}";

        SharePlus.instance.share(ShareParams(
          text:
              '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: shareThisSellerKey)} : $sellerUrl',
          subject:
              "${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: sellerDetailsKey)}: $sellerUrl",
        ));
      },
    );
  }
}
