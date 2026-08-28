import 'package:eshop_plus/ui/profile/faq/repositories/faqRepository.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

import '../models/faq.dart';

abstract class FAQState {}

class FAQInitial extends FAQState {}

class FAQFetchInProgress extends FAQState {}

class FAQFetchSuccess extends FAQState {
  final int total;
  final List<FAQ> faqs;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  FAQFetchSuccess({
    required this.faqs,
    required this.fetchMoreError,
    required this.fetchMoreInProgress,
    required this.total,
  });

  FAQFetchSuccess copyWith({
    bool? fetchMoreError,
    bool? fetchMoreInProgress,
    int? total,
    List<FAQ>? faqs,
  }) {
    return FAQFetchSuccess(
      faqs: faqs ?? this.faqs,
      fetchMoreError: fetchMoreError ?? this.fetchMoreError,
      fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
      total: total ?? this.total,
    );
  }
}

class FAQFetchFailure extends FAQState {
  final String errorMessage;

  FAQFetchFailure(this.errorMessage);
}

class FAQCubit extends Cubit<FAQState> {
  final FaqRepository _faqRepository = FaqRepository();

  FAQCubit() : super(FAQInitial());

  void getFAQ({
    required Map<String, dynamic> params,
    required String api,
  }) async {
    emit(FAQFetchInProgress());
    try {
      final result = await _faqRepository.getFaqs(
        params: params,
        api: api,
      );
      emit(FAQFetchSuccess(
        faqs: result.faqs,
        fetchMoreError: false,
        fetchMoreInProgress: false,
        total: result.total,
      ));
    } catch (e) {
      emit(FAQFetchFailure(e.toString()));
    }
  }

  List<FAQ> getFAQList() {
    if (state is FAQFetchSuccess) {
      return (state as FAQFetchSuccess).faqs;
    }
    return [];
  }

  bool fetchMoreError() {
    if (state is FAQFetchSuccess) {
      return (state as FAQFetchSuccess).fetchMoreError;
    }
    return false;
  }

  bool hasMore() {
    if (state is FAQFetchSuccess) {
      return (state as FAQFetchSuccess).faqs.length <
          (state as FAQFetchSuccess).total;
    }
    return false;
  }

  void loadMore({
    required Map<String, dynamic> params,
    required String api,
  }) async {
    if (state is FAQFetchSuccess) {
      if ((state as FAQFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as FAQFetchSuccess).copyWith(fetchMoreInProgress: true));
        params.addAll(
            {ApiURL.offsetApiKey: (state as FAQFetchSuccess).faqs.length});
        final moreFAQ = await _faqRepository.getFaqs(
          params: params,
          api: api,
        );

        final currentState = (state as FAQFetchSuccess);

        List<FAQ> faqs = currentState.faqs;

        faqs.addAll(moreFAQ.faqs);

        emit(FAQFetchSuccess(
          fetchMoreError: false,
          fetchMoreInProgress: false,
          total: moreFAQ.total,
          faqs: faqs,
        ));
      } catch (e) {
        emit((state as FAQFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }

  emisSuccessState(List<FAQ> faqs) {
    if (state is FAQFetchSuccess) {
      emit((state as FAQFetchSuccess).copyWith(faqs: faqs));
      return;
    }
    emit(FAQFetchSuccess(
      fetchMoreError: false,
      fetchMoreInProgress: false,
      total: faqs.length,
      faqs: faqs,
    ));
    return;
  }

  resetState() {
    emit(FAQInitial());
  }
}
