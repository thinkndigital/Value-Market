import 'package:flutter_bloc/flutter_bloc.dart';
import '../repositories/addCategoryRepository.dart';

abstract class AddCategoryState {}

class AddCategoryInitial extends AddCategoryState {}

class AddCategoryInProgress extends AddCategoryState {}

class AddCategorySuccess extends AddCategoryState {
  final String message;
  AddCategorySuccess(this.message);
}

class AddCategoryFailure extends AddCategoryState {
  final String error;
  AddCategoryFailure(this.error);
}

class AddCategoryCubit extends Cubit<AddCategoryState> {
  AddCategoryCubit() : super(AddCategoryInitial());

  Future<void> addCategory({
    required String storeId,
    required Map<String, String> names,
    required String imagePath,
    required String bannerPath,
    String? parentId,
  
  }) async {
    emit(AddCategoryInProgress());
    try {
      final result = await AddCategoryRepository().addCategory(
        storeId: storeId,
        names: names,
        imagePath: imagePath,
        bannerPath: bannerPath,
        parentId: parentId,
      
      );
      emit(AddCategorySuccess(result));
    } catch (e) {
      emit(AddCategoryFailure(e.toString()));
    }
  }
}
