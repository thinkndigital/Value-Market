import 'package:flutter_bloc/flutter_bloc.dart';
import '../repositories/returnRequestRepository.dart';

abstract class ReturnRequestUpdateState {}

class ReturnRequestUpdateInitial extends ReturnRequestUpdateState {}

class ReturnRequestUpdateLoading extends ReturnRequestUpdateState {}

class ReturnRequestUpdateSuccess extends ReturnRequestUpdateState {
  final String message;
  ReturnRequestUpdateSuccess(this.message);
}

class ReturnRequestUpdateFailure extends ReturnRequestUpdateState {
  final String error;
  ReturnRequestUpdateFailure(this.error);
}

class ReturnRequestUpdateCubit extends Cubit<ReturnRequestUpdateState> {
  ReturnRequestUpdateCubit() : super(ReturnRequestUpdateInitial());

  Future<void> updateReturnRequestStatus({
    required int status,
    required int returnRequestId,
    required int orderItemId,
    int? deliverBy,
    String? remarks,
  }) async {
    emit(ReturnRequestUpdateLoading());
    try {
      final result = await ReturnRequestRepository().updateReturnRequestStatus(
          status: status,
          returnRequestId: returnRequestId,
          orderItemId: orderItemId,
          deliverBy: deliverBy,
          remarks: remarks);
      emit(ReturnRequestUpdateSuccess(
          result['message'] ?? 'Status updated successfully'));
    } catch (e) {
      emit(ReturnRequestUpdateFailure(e.toString()));
    }
  }
}
