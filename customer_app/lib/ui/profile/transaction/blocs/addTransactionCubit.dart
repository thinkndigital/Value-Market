import 'package:eshop_plus/ui/profile/transaction/repositories/transactionRepository.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class AddTransactionState {}

class AddTransactionInitial extends AddTransactionState {}

class AddTransactionProgress extends AddTransactionState {}

class AddTransactionSuccess extends AddTransactionState {
  String orderId;
  AddTransactionSuccess({
    required this.orderId,
  });
}

class AddTransactionFailure extends AddTransactionState {
  final String errorMessage;

  AddTransactionFailure(this.errorMessage);
}

class AddTransactionCubit extends Cubit<AddTransactionState> {
  final TransactionRepository _transactionRepository = TransactionRepository();

  AddTransactionCubit() : super(AddTransactionInitial());

  void addTransaction({required Map<String, dynamic> params}) async {
    emit(AddTransactionProgress());
    _transactionRepository.addTransaction(params: params).then((value) {
      emit(AddTransactionSuccess(
        orderId: params[ApiURL.orderIdApiKey],
      ));
    }).catchError((e) {
      emit(AddTransactionFailure(e.toString()));
    });
  }
}
