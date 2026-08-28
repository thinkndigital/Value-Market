import 'package:value_market_seller/commons/models/product.dart';
import 'package:value_market_seller/core/api/apiEndPoints.dart';
import 'package:value_market_seller/core/api/apiService.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class AddProductState {}

class AddProductInitial extends AddProductState {}

class AddProductProgress extends AddProductState {
  AddProductProgress();
}

class AddProductSuccess extends AddProductState {
  final Product product;
  String successMsg;
  AddProductSuccess(this.product, this.successMsg);
}

class AddProductFailure extends AddProductState {
  final String errorMessage;
  AddProductFailure(this.errorMessage);
}

class AddProductCubit extends Cubit<AddProductState> {
  AddProductCubit() : super(AddProductInitial());

  addProduct(BuildContext context, Map<String, dynamic> parameter, bool isEdit,
      {bool isComboProduct = false}) {
    emit(AddProductProgress());
    addProductProcess(context, parameter, isEdit, isComboProduct).then((value) {
      emit(AddProductSuccess(
          Product.fromJson(Map.from(value[ApiURL.dataKey] ?? {}),
              isComboProduct: isComboProduct),
          value["message"]));
    }).catchError((e) {
      if (e is ApiException) {
        emit(AddProductFailure(e.toString()));
      } else {
        emit(AddProductFailure(defaultErrorMessageKey));
      }
    });
  }

  Future addProductProcess(BuildContext context,
      Map<String, dynamic> parameter, bool isEdit, bool isComboProduct) async {
    try {
      var result;
      if (isEdit) {
        result = await Api.post(
            body: parameter,
            url: isComboProduct
                ? ApiURL.updateComboProducts
                : ApiURL.updateProducts,
            useAuthToken: true);
      } else {
        result = await Api.post(
            body: parameter,
            url: isComboProduct ? ApiURL.addComboProducts : ApiURL.addProducts,
            useAuthToken: true);
      }

      return result;
    } catch (e) {
      Utils.throwApiException(e);
    }
  }
}
