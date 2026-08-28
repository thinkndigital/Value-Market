import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

import '../../../../core/localization/labelKeys.dart';

class AddProductConfirmation extends StatelessWidget {
  final bool isEdit;
  const AddProductConfirmation({super.key, required this.isEdit});

  @override
  Widget build(BuildContext context) {
    return Center(
        child: CustomTextContainer(
            textKey: isEdit
                ? updateProductConfirmationKey
                : addProductConfirmationKey));
  }
}
