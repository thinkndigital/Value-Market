import 'package:value_market_delivery_boy/features/orders/models/cashCollection.dart';
import 'package:value_market_delivery_boy/features/collections/repositories/cashCollectionRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class CashCollectionState {}

class CashCollectionInitial extends CashCollectionState {}

class CashCollectionFetchProgress extends CashCollectionState {}

class CashCollectionFetchSuccess extends CashCollectionState {
  final List<CashCollection> transactions;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;
  final int total;
  final double balance;

  CashCollectionFetchSuccess({
    required this.transactions,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
    required this.balance,
  });

  CashCollectionFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    double? balance,
    List<CashCollection>? transactions,
  }) {
    return CashCollectionFetchSuccess(
      transactions: transactions ?? this.transactions,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
      balance: balance ?? this.balance,
    );
  }
}

class CashCollectionFetchFailure extends CashCollectionState {
  final String errorMessage;

  CashCollectionFetchFailure(this.errorMessage);
}

class CashCollectionCubit extends Cubit<CashCollectionState> {
  final CashCollectionRepository _CashCollectionRepository =
      CashCollectionRepository();

  CashCollectionCubit() : super(CashCollectionInitial());

  void getTransaction(
    String type,
  ) async {
    emit(CashCollectionFetchProgress());
    try {
      final result = await _CashCollectionRepository.getCollections(
        status: type,
      );
      if (!isClosed)
        emit(CashCollectionFetchSuccess(
          transactions: result.transactions,
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: result.total,
          balance: result.balance,
        ));
    } catch (e) {
      if (!isClosed) emit(CashCollectionFetchFailure(e.toString()));
    }
  }

  List<CashCollection> getTransactionList() {
    if (state is CashCollectionFetchSuccess) {
      return (state as CashCollectionFetchSuccess).transactions;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is CashCollectionFetchSuccess) {
      return (state as CashCollectionFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is CashCollectionFetchSuccess) {
      return (state as CashCollectionFetchSuccess).transactions.length <
          (state as CashCollectionFetchSuccess).total;
    }
    return false;
  }

  void loadMore(
    String type,
  ) async {
    if (state is CashCollectionFetchSuccess) {
      if ((state as CashCollectionFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as CashCollectionFetchSuccess)
            .copyWith(fetchMoreInProgress: true));

        final moreTransaction = await _CashCollectionRepository.getCollections(
            status: type,
            offset: (state as CashCollectionFetchSuccess).transactions.length);

        final currentState = (state as CashCollectionFetchSuccess);

        List<CashCollection> transactions = currentState.transactions;

        transactions.addAll(moreTransaction.transactions);

        emit(CashCollectionFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreTransaction.total,
          transactions: transactions,
          balance: moreTransaction.balance,
        ));
      } catch (e) {
        emit((state as CashCollectionFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }
}
