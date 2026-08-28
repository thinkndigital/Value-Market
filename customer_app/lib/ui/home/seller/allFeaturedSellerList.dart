import 'package:eshop_plus/commons/seller/models/seller.dart';
import 'package:eshop_plus/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:eshop_plus/ui/explore/widgets/sellersContainer.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

class AllFeaturedSellerList extends StatefulWidget {
  String title;
  List<Seller> sellers;
  AllFeaturedSellerList({Key? key, required this.title, required this.sellers})
      : super(key: key);
  static Widget getRouteInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return AllFeaturedSellerList(
      title: arguments['title'] as String,
      sellers: arguments['sellers'] as List<Seller>? ?? [],
    );
  }

  static Map<String, dynamic> buildArguments({
    required String title,
    List<Seller>? sellers,
  }) {
    return {
      'title': title,
      'sellers': sellers,
    };
  }

  @override
  _AllFeaturedSellerListState createState() => _AllFeaturedSellerListState();
}

class _AllFeaturedSellerListState extends State<AllFeaturedSellerList> {
  @override
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return SafeAreaWithBottomPadding(
      child: Scaffold(
          appBar: CustomAppbar(titleKey: widget.title),
          body: SellersContainer(
            sellers: widget.sellers,
            sellerIds: widget.sellers.map((e) => e.sellerId!).toList(),
          )),
    );
  }
}
