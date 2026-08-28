import 'package:eshopplus_seller/features/profile/wallet/repositories/transactionRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/transaction.dart';

abstract class TransactionState {}

class TransactionInitial extends TransactionState {}

class TransactionFetchInProgress extends TransactionState {}

class TransactionFetchSuccess extends TransactionState {
  final List<Transaction> transactions;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;
  final int total;
  final double balance;

  TransactionFetchSuccess({
    required this.transactions,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
    required this.balance,
  });

  TransactionFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    double? balance,
    List<Transaction>? transactions,
  }) {
    return TransactionFetchSuccess(
      transactions: transactions ?? this.transactions,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
      balance: balance ?? this.balance,
    );
  }
}

class TransactionFetchFailure extends TransactionState {
  final String errorMessage;

  TransactionFetchFailure(this.errorMessage);
}

class TransactionCubit extends Cubit<TransactionState> {
  final TransactionRepository _transactionRepository = TransactionRepository();

  TransactionCubit() : super(TransactionInitial());

  void getTransaction({
    required int userId,
    String? type,
  }) async {
    emit(TransactionFetchInProgress());
    try {
      final result = await _transactionRepository.getTransactions(
        userId: userId,
        type: type,
      );
      emit(TransactionFetchSuccess(
        transactions: result.transactions,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
        balance: result.balance,
      ));
    } catch (e) {
      emit(TransactionFetchFailure(e.toString()));
    }
  }

  List<Transaction> getTransactionList() {
    if (state is TransactionFetchSuccess) {
      return (state as TransactionFetchSuccess).transactions;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is TransactionFetchSuccess) {
      return (state as TransactionFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is TransactionFetchSuccess) {
      return (state as TransactionFetchSuccess).transactions.length <
          (state as TransactionFetchSuccess).total;
    }
    return false;
  }

  void loadMore({
    required int userId,
    String? type,
  }) async {
    if (state is TransactionFetchSuccess) {
      if ((state as TransactionFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as TransactionFetchSuccess)
            .copyWith(fetchMoreInProgress: true));

        final moreTransaction = await _transactionRepository.getTransactions(
            userId: userId,
            type: type,
            offset: (state as TransactionFetchSuccess).transactions.length);

        final currentState = (state as TransactionFetchSuccess);

        List<Transaction> transactions = currentState.transactions;

        transactions.addAll(moreTransaction.transactions);

        emit(TransactionFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreTransaction.total,
          transactions: transactions,
          balance: moreTransaction.balance,
        ));
      } catch (e) {
        emit((state as TransactionFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  updateList(List<Transaction> list) {
    emit((state as TransactionFetchSuccess).copyWith(transactions: list));
  }
}
