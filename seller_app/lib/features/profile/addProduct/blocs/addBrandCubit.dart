import 'package:flutter_bloc/flutter_bloc.dart';
import '../repositories/addBrandRepository.dart';

abstract class AddBrandState {}

class AddBrandInitial extends AddBrandState {}

class AddBrandInProgress extends AddBrandState {}

class AddBrandSuccess extends AddBrandState {
  final String message;
  AddBrandSuccess(this.message);
}

class AddBrandFailure extends AddBrandState {
  final String error;
  AddBrandFailure(this.error);
}

class AddBrandCubit extends Cubit<AddBrandState> {
  AddBrandCubit() : super(AddBrandInitial());

  Future<void> addBrand({
    required String storeId,
    required Map<String, String> names,
    required String imagePath,
 
  }) async {
    emit(AddBrandInProgress());
    try {
      final result = await AddBrandRepository().addBrand(
        storeId: storeId,
        names: names,
        imagePath: imagePath,
    
      );
      emit(AddBrandSuccess(result));
    } catch (e) {
      emit(AddBrandFailure(e.toString()));
    }
  }
}
