import 'package:eshopplus_seller/features/profile/addProduct/models/category.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';

abstract class CategoryListState {}

class CategoryListFetchInitial extends CategoryListState {}

class CategoryListFetchProgress extends CategoryListState {
  CategoryListFetchProgress();
}

class CategoryListFetchSuccess extends CategoryListState {
  List<Category> categoryList;
  CategoryListFetchSuccess(this.categoryList);
}

class CategoryListFetchFailure extends CategoryListState {
  final String errorMessage;
  CategoryListFetchFailure(this.errorMessage);
}

class CategoryListCubit extends Cubit<CategoryListState> {
  CategoryListCubit() : super(CategoryListFetchInitial());
  setInitialState() {
    emit(CategoryListFetchInitial());
  }

  getCategoryList(BuildContext context, Map<String, String?> parameter,
      {bool getAllCategories = false}) {
    emit(CategoryListFetchProgress());
    getCategoryListProcess(context, parameter,
            getAllCategories: getAllCategories)
        .then((list) {
      if (!isClosed) emit(CategoryListFetchSuccess(list));
    }).catchError((e) {
      if (!isClosed) emit(CategoryListFetchFailure(e.toString()));
    });
  }

  Future<List<Category>> getCategoryListProcess(
      BuildContext context, Map<String, String?> parameter,
      {bool getAllCategories = false}) async {
    try {
      //when we use category API in signup ,then we need all categories of that store
      final result = await Api.get(
          url: getAllCategories
              ? ApiURL.getAllCategoryList
              : ApiURL.getCategoryList,
          useAuthToken: true,
          queryParameters: parameter);
      List data = result[ApiURL.dataKey];

      return data.map((e) => Category.fromJson(e)).toList();
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
