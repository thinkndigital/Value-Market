import 'package:eshop_plus/ui/profile/transaction/models/transaction.dart';
import 'package:eshop_plus/ui/profile/transaction/repositories/transactionRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class SendWithdrawalRequestState {}

class SendWithdrawalRequestInitial extends SendWithdrawalRequestState {}

class SendWithdrawalRequestProgress extends SendWithdrawalRequestState {}

class SendWithdrawalRequestSuccess extends SendWithdrawalRequestState {
  final Transaction transaction;
  final String successMessage;
  SendWithdrawalRequestSuccess({
    required this.transaction,
    required this.successMessage,
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
      emit(SendWithdrawalRequestSuccess(
          transaction: value.transaction, successMessage: value.message));
    }).catchError((e) {
      emit(SendWithdrawalRequestFailure(e.toString()));
    });
  }
}
