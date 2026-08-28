import 'package:eshopplus_seller/features/profile/faq/repositories/faqRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class DeleteFAQState {}

class DeleteFAQInitial extends DeleteFAQState {}

class DeleteFAQProgress extends DeleteFAQState {
  final int faqId;
  DeleteFAQProgress({required this.faqId});
}

class DeleteFAQFailure extends DeleteFAQState {
  final String errorMessage;
  DeleteFAQFailure(this.errorMessage);
}

class DeleteFAQSuccess extends DeleteFAQState {
  final int faqId;
  final String successMessage;
  DeleteFAQSuccess({required this.faqId, required this.successMessage});
}

class DeleteFAQCubit extends Cubit<DeleteFAQState> {
  final FaqRepository _faqRepository = FaqRepository();
  DeleteFAQCubit() : super(DeleteFAQInitial());
  void deleteFAQ({required int faqId, required String type}) async {
    emit(DeleteFAQProgress(faqId: faqId));
    var result;
    try {
      result = await _faqRepository.deleteFAQ(faqId: faqId, type: type);
      emit(DeleteFAQSuccess(faqId: faqId, successMessage: result));
    } catch (e) {
      emit(DeleteFAQFailure(e.toString()));
    }
  }
}
