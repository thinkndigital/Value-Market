import 'package:eshopplus_seller/features/profile/addProduct/models/tax.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';

abstract class TaxListState {}

class TaxListFetchInitial extends TaxListState {}

class TaxListFetchProgress extends TaxListState {
  TaxListFetchProgress();
}

class TaxListFetchSuccess extends TaxListState {
  List<Tax> taxList;
  TaxListFetchSuccess(this.taxList);
}

class TaxListFetchFailure extends TaxListState {
  final String errorMessage;
  TaxListFetchFailure(this.errorMessage);
}

class TaxListCubit extends Cubit<TaxListState> {
  TaxListCubit() : super(TaxListFetchInitial());
  setInitialState() {
    emit(TaxListFetchInitial());
  }

  getTaxList(
    BuildContext context,
    Map<String, String?> parameter,
  ) {
    emit(TaxListFetchProgress());
    getTaxListProcess(context, parameter).then((list) {
      emit(TaxListFetchSuccess(list));
    }).catchError((e) {
      emit(TaxListFetchFailure(e.toString()));
    });
  }

  Future<List<Tax>> getTaxListProcess(
      BuildContext context, Map<String, String?> parameter) async {
    try {
      final result = await Api.get(
          url: ApiURL.getTaxes, useAuthToken: true, queryParameters: parameter);
      List data = result[ApiURL.dataKey];

      return data.map((e) => Tax.fromJson(e)).toList();
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
