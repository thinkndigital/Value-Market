import 'package:value_market_customer/ui/home/featuredSection/sectionRepository.dart';

import 'package:flutter_bloc/flutter_bloc.dart';

import 'fesaturedSection.dart';

abstract class FeaturedSectionState {}

class FeaturedSectionInitial extends FeaturedSectionState {}

class FeaturedSectionFetchInProgress extends FeaturedSectionState {}

class FeaturedSectionFetchSuccess extends FeaturedSectionState {
  final List<FeaturedSection> sections;

  FeaturedSectionFetchSuccess(this.sections);
}

class FeaturedSectionFetchFailure extends FeaturedSectionState {
  final String errorMessage; 

  FeaturedSectionFetchFailure(this.errorMessage);
}

class FeaturedSectionCubit extends Cubit<FeaturedSectionState> {
  final SectionRepository _sectionRepository = SectionRepository();

  FeaturedSectionCubit() : super(FeaturedSectionInitial());

  void getSections({required int storeId, String? zipcode}) {
    emit(FeaturedSectionFetchInProgress());

    _sectionRepository
        .getSections(storeId: storeId, zipcode: zipcode)
        .then((value) {
      if (!isClosed) emit(FeaturedSectionFetchSuccess(value));
    }).catchError((e) {
      if (!isClosed) emit(FeaturedSectionFetchFailure(e.toString()));
    });
  }
}
