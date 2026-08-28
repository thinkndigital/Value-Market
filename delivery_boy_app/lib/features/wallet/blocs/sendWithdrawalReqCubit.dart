import 'package:eshoppro_deliveryboy/features/wallet/repositories/transactionRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/transaction.dart';

abstract class SendWithdrawalRequestState {}

class SendWithdrawalRequestInitial extends SendWithdrawalRequestState {}

class SendWithdrawalRequestProgress extends SendWithdrawalRequestState {}

class SendWithdrawalRequestSuccess extends SendWithdrawalRequestState {
  final String successMessage;
  final Transaction transaction;
  SendWithdrawalRequestSuccess({
    required this.successMessage,
    required this.transaction,
  });
}

class SendWithdrawalRequestFailure extends SendWithdrawalRequestState {
  final String errorMessage;

  SendWithdrawalRequestFailure(this.errorMessage);
}

class SendWithdrawalRequestCubit extends Cubit<SendWithdrawalRequestState> {
  final TransactionRepository _transactionRepository = TransactionRepository();

  SendWithdrawalRequestCubit() : super(SendWithdrawalRequestInitial());

  void sendWithdrawalRequest({required Map<String, dynamic> params}) async {
    emit(SendWithdrawalRequestProgress());
    _transactionRepository.sendWithdrawalRequest(params: params).then((value) {
      Transaction transaction = Transaction.fromWithdrawJson(value['data']);
      emit(SendWithdrawalRequestSuccess(
          successMessage: value['message'], transaction: transaction));
    }).catchError((e) {
      emit(SendWithdrawalRequestFailure(e.toString()));
    });
  }
}
