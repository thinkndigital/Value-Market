import 'package:eshopplus_seller/features/profile/salesReport/repositories/salesRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'dart:convert';

abstract class MostSellingCategoryState {}

class MostSellingCategoryInitial extends MostSellingCategoryState {}

class MostSellingCategoryFetchInProgress extends MostSellingCategoryState {}

class MostSellingCategoryFetchSuccess extends MostSellingCategoryState {
  final MostSellingCategory yearly;
  final MostSellingCategory monthly;
  final MostSellingCategory weekly;

  MostSellingCategoryFetchSuccess({
    required this.yearly,
    required this.monthly,
    required this.weekly,
  });
}

class MostSellingCategoryFetchFailure extends MostSellingCategoryState {
  final String errorMessage;

  MostSellingCategoryFetchFailure(this.errorMessage);
}

class MostSellingCategoryCubit extends Cubit<MostSellingCategoryState> {
  final SalesRepository salesRepository = SalesRepository();

  MostSellingCategoryCubit() : super(MostSellingCategoryInitial());

  void getMostSellingCategory({required Map<String, dynamic> params}) {
    emit(MostSellingCategoryFetchInProgress());
    salesRepository
        .getMostSellingCategory(params: params)
        .then((value) => emit(MostSellingCategoryFetchSuccess(
              monthly: value.monthly,
              yearly: value.yearly,
              weekly: value.weekly,
            )))
        .catchError((e) {
      emit(MostSellingCategoryFetchFailure(e.toString()));
    });
  }
}

class MostSellingCategory {
  List<int> totalSold;
  List<String> categoryNames;

  MostSellingCategory({required this.totalSold, required this.categoryNames});

  factory MostSellingCategory.fromJson(Map<String, dynamic> json) {
    return MostSellingCategory(
      totalSold: json['total_sold'] != []
          ? List<int>.from(json['total_sold'].map((item) => int.parse(item)))
          : [],
      categoryNames: List<String>.from(json['category_names']),
    );
  }
}

Future<MostSellingCategory> parseMostSellingCategories(
    String responseBody) async {
  final parsed = jsonDecode(responseBody);
  return MostSellingCategory.fromJson(
      parsed['most_selling_categories']['monthly']);
}
