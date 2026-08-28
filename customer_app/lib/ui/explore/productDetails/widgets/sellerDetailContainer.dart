import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/utils.dart';

class SellerDetailContainer extends StatelessWidget {
  final Product product;
  const SellerDetailContainer({Key? key, required this.product})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(
        context,
        Routes.sellerDetailScreen,
        arguments: {'sellerId': product.sellerId, 'storeId': product.storeId},
      ),
      child: CustomDefaultContainer(
          child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Text.rich(
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium,
              TextSpan(
                children: [
                  TextSpan(
                    text: context
                        .read<SettingsAndLanguagesCubit>()
                        .getTranslatedValue(labelKey: sellerDetailsKey),
                  ),
                  const TextSpan(text: ' : '),
                  TextSpan(
                    text: product.storeName != null &&
                            product.storeName!.isNotEmpty
                        ? product.storeName.toString().capitalizeFirst
                        : product.sellerName.toString().capitalizeFirst,
                    style: Theme.of(context)
                        .textTheme
                        .labelLarge!
                        .copyWith(color: Theme.of(context).colorScheme.primary),
                  )
                ],
              ),
            ),
          ),
          const Icon(Icons.arrow_forward_ios, size: 24)
        ],
      )),
    );
  }
}
