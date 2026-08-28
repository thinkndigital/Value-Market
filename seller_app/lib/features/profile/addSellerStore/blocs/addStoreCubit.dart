import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/commons/repositories/storeRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class AddStoreState {}

class AddStoreInitial extends AddStoreState {}

class AddStoreProgress extends AddStoreState {
  AddStoreProgress();
}

class AddStoreSuccess extends AddStoreState {
  final UserDetails userDetails;
  final String message;

  AddStoreSuccess(this.userDetails, this.message);
}

class AddStoreFailure extends AddStoreState {
  final String errorMessage;

  AddStoreFailure(this.errorMessage);
}

class AddStoreCubit extends Cubit<AddStoreState> {
  final StoreRepository storeRepository = StoreRepository();

  AddStoreCubit() : super(AddStoreInitial());

  void addSellerStore({required Map<String, dynamic> params}) {
    emit(AddStoreProgress());

    storeRepository.addSellerStore(params: params).then((value) {
      emit(AddStoreSuccess(
        value.userDetails,
        value.successMessage,
      ));
    }).catchError((e) {
      emit(AddStoreFailure(e.toString()));
    });
  }
}
