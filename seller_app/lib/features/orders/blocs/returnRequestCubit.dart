import 'package:flutter_bloc/flutter_bloc.dart';
import '../models/return_request.dart';
import '../repositories/returnRequestRepository.dart';

abstract class ReturnRequestState {}

class ReturnRequestInitial extends ReturnRequestState {}

class ReturnRequestLoading extends ReturnRequestState {}

class ReturnRequestSuccess extends ReturnRequestState {
  final List<ReturnRequest> requests;
  final int total;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  ReturnRequestSuccess({
    required this.requests,
    required this.total,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
  });

  ReturnRequestSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<ReturnRequest>? requests,
  }) {
    return ReturnRequestSuccess(
      requests: requests ?? this.requests,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class ReturnRequestFailure extends ReturnRequestState {
  final String error;
  ReturnRequestFailure(this.error);
}

class ReturnRequestCubit extends Cubit<ReturnRequestState> {
  ReturnRequestCubit() : super(ReturnRequestInitial());

  Future<void> fetchReturnRequests(Map<String, dynamic> params) async {
    emit(ReturnRequestLoading());
    try {
      final result = await ReturnRequestRepository().getReturnRequests(params);
      final List<ReturnRequest> requests = (result['data'] as List)
          .map((e) => ReturnRequest.fromJson(e))
          .toList();
      emit(ReturnRequestSuccess(
        requests: requests,
        total: result['total'] ?? 0,
        fetchMoreError: false,
        fetchMoreInProgress: false,
      ));
    } catch (e) {
      emit(ReturnRequestFailure(e.toString()));
    }
  }

  List<ReturnRequest> getReturnRequestList() {
    if (state is ReturnRequestSuccess) {
      return (state as ReturnRequestSuccess).requests;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is ReturnRequestSuccess) {
      return (state as ReturnRequestSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is ReturnRequestSuccess) {
      return (state as ReturnRequestSuccess).requests.length <
          (state as ReturnRequestSuccess).total;
    }
    return false;
  }

  void loadMore(Map<String, dynamic> params) async {
    if (state is ReturnRequestSuccess) {
      if ((state as ReturnRequestSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as ReturnRequestSuccess)
            .copyWith(fetchMoreInProgress: true));

        final moreRequests = await ReturnRequestRepository().getReturnRequests(
            params,
            offset: (state as ReturnRequestSuccess).requests.length);

        final currentState = (state as ReturnRequestSuccess);

        List<ReturnRequest> requests = currentState.requests;

        final List<ReturnRequest> newRequests = (moreRequests['data'] as List)
            .map((e) => ReturnRequest.fromJson(e))
            .toList();

        requests.addAll(newRequests);

        emit(ReturnRequestSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreRequests['total'] ?? 0,
          requests: requests,
        ));
      } catch (e) {
        emit((state as ReturnRequestSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }
}
