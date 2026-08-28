import 'package:eshop_plus/ui/categoty/repositories/categoryRepository.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/category.dart';

abstract class CategoryState {}

class CategoryInitial extends CategoryState {}

class CategoryFetchInProgress extends CategoryState {}

class CategoryFetchSuccess extends CategoryState {
  final int total;
  final List<Category> categories;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  CategoryFetchSuccess({
    required this.categories,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  CategoryFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<Category>? categories,
  }) {
    return CategoryFetchSuccess(
      categories: categories ?? this.categories,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class CategoryFetchFailure extends CategoryState {
  final String errorMessage;

  CategoryFetchFailure(this.errorMessage);
}

class CategoryCubit extends Cubit<CategoryState> {
  final CategoryRepository categoryRepository = CategoryRepository();

  CategoryCubit() : super(CategoryInitial());

  void fetchCategories(
      {required int storeId,
      String? search,
      int? categoryId,
      String? categoryIds}) {
    emit(CategoryFetchInProgress());

    categoryRepository
        .getCategories(
            storeId: storeId,
            search: search,
            categoryId: categoryId,
            categoryIds: categoryIds)
        .then((value) => emit(CategoryFetchSuccess(
              categories: value.categories,
              fetchMoreError: false,
              fetchMoreInProgress: false,
              total: value.total,
            )))
        .catchError((e) {
      emit(CategoryFetchFailure(e.toString()));
    });
  }

  bool fetchMoreError() {
    if (state is CategoryFetchSuccess) {
      return (state as CategoryFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is CategoryFetchSuccess) {
      return (state as CategoryFetchSuccess).categories.length <
          (state as CategoryFetchSuccess).total;
    }
    return false;
  }

  void loadMore({required int storeId, String? search, int? categoryId}) async {
    if (state is CategoryFetchSuccess) {
      if ((state as CategoryFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as CategoryFetchSuccess)
            .copyWith(fetchMoreInProgress: true));

        final moreCategories = await categoryRepository.getCategories(
            storeId: storeId,
            search: search,
            categoryId: categoryId,
            offset: (state as CategoryFetchSuccess).categories.length);

        final currentState = (state as CategoryFetchSuccess);

        List<Category> categories = currentState.categories;

        categories.addAll(moreCategories.categories);

        emit(CategoryFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreCategories.total,
          categories: categories,
        ));
      } catch (e) {
        emit((state as CategoryFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  List<Category> getCategoryList() {
    if (state is CategoryFetchSuccess) {
      return (state as CategoryFetchSuccess).categories;
    }
    return [];
  }
}
